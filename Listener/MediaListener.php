<?php 

namespace Puzzle\Api\MediaBundle\Listener;

use Puzzle\Api\MediaBundle\Event\FileEvent;
use Puzzle\Api\MediaBundle\Entity\File;
use Puzzle\Api\MediaBundle\Event\FolderEvent;
use Puzzle\Api\MediaBundle\Service\MediaManager;
use Doctrine\ORM\EntityManagerInterface;
use Puzzle\Api\MediaBundle\Service\MediaUploader;
use Puzzle\Api\MediaBundle\Entity\Folder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Puzzle\Api\MediaBundle\Util\MediaUtil;
use Puzzle\OAuthServerBundle\PuzzleOAuthServerBundle;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class MediaListener
{
	/**
	 * @var EntityManagerInterface
	 */
	private $em;
	
	/**
	 * @var MediaManager
	 */
	private $mediaManager;
	
	/**
	 * @var MediaUploader
	 */
	private $mediaUploader;
	
	public function __construct(EntityManagerInterface $em, MediaManager $mediaManager, MediaUploader $mediaUploader){
		$this->em = $em;
		$this->mediaManager = $mediaManager;
		$this->mediaUploader = $mediaUploader;
	}
	
	/**
	 * Folder created on disk
	 *
	 * @param FolderEvent $event
	 * @return boolean
	 */
	public function onCreateFolder(FolderEvent $event) {
	    $folder = $event->getFolder();
	    $data = $event->getData();
	    $user = $data['user'];
	    
	    $parent = $folder->getParent();
	    $parent = $parent ??  $this->em->getRepository(Folder::class)->findOneBy([
	        'name'         => $user->getUsername(),
	        'createdBy'    => $user->getId()
	    ]);
	    
	    if (! $parent){
	        $parent = new Folder();
	        $parent->setOverwritable(false);
	        $parent->setName($user->getUsername());
	        
	        $this->em->persist($parent);
	        $this->em->flush($parent);
	    }
	    
	    $folder->setParent($parent);
	    $this->em->flush($folder);
	    
	    return $this->mediaManager->createFolder($folder, $user);
	}
	
	/**
	 * Rename folder on disk
	 *
	 * @param FolderEvent $event
	 * @return boolean
	 */
	public function onRenameFolder(FolderEvent $event) {
	    $folder = $event->getFolder();
	    $data = $event->getData();
	    if (file_exists($folder->getAbsolutePath()) === false) {
	        $this->mediaManager->renameFolder($folder, $data['oldAbsolutePath']);
	    }
	    
	    $this->em->flush();
	    return true;
	}
	
	/**
	 * Remove folder on disk
	 *
	 * @param FolderEvent $event
	 * @return boolean
	 */
	public function onRemoveFolder(FolderEvent $event) {
	    $folder = $event->getFolder();
	    $folderPath = $folder->getAbsolutePath();
	    if (file_exists($folderPath) === true) {
	        $this->mediaManager->removeFolder($folderPath);
	    }
	    
	    return true;
	}
	
	/**
	 * Add files to folder
	 *
	 * @param FolderEvent $event
	 */
	public function onAddFilesToFolder(FolderEvent $event) {
	    $folder = $event->getFolder();
	    $data = $event->getData();
	    
	    $filesToAdd = $data['files_to_add'] ?? null;
	    $preserveFiles = $data['preserve_files'];
	    
	    if ($filesToAdd !== null) {
	        $files = is_string($filesToAdd) ? explode(',', $filesToAdd) : $filesToAdd;
	        $er = $this->em->getRepository(File::class);
	        
	        foreach ($files as $item){
	            if ($file = $er->findOneBy(['path' => $item]) || $file = $er->find($item)) {
	                if ($file instanceof File && file_exists($file->getAbsolutePath())) {
	                    if ($preserveFiles === false) {
	                       $this->mediaManager->moveFile($file, $folder);
	                    }else {
	                        $overwritable = $data['overwritable'] ?? false;
	                        $this->mediaManager->copyFile($file, $folder, $overwritable);
	                    }
	                }
	            }else {
	                if (MediaUtil::isValidFromUrl($item)){
	                    $this->mediaUploader->uploadFromUrl($item, $folder->getId());
	                }
	            }
	        }
	    }
	    
	    return true;
	}
	
	/**
	 * Empty folder
	 *
	 * @param FolderEvent $event
	 */
	public function onRemoveFilesFromFolder(FolderEvent $event) {
	    $folder = $event->getFolder();
	    $data = $event->getData();
	    
	    if (isset($data['files_to_remove']) === true && $data['files_to_remove'] !== null) {
	        $filesId = $data['files_to_remove'];
	        $er = $this->em->getRepository(File::class);
	        foreach ($filesId as $fileId){
	            /** @var File $file */
	            $file = $er->find($fileId);
	            if ($file !== null && file_exists($file->getAbsolutePath())) {
	                unlink($folder->getPath().'/'.$file->getName());
	            }
	        }
	    }
	    
	    return true;
	}
	
	/**
	 * Post created on disk
	 * 
	 * @param FileEvent $event
	 */
	public function onCreateFile(FileEvent $event) {
	    $data = $event->getData();
	    
		if (file_exists($data['absolutePath']) === false) {
		    return mkdir($data['absolutePath'], 0777, true);
		}
		
		return true;
	}
	
	/**
	 * Rename post on disk
	 *
	 * @param FileEvent $event
	 */
	public function onRenameFile(FileEvent $event) {
	    $data = $event->getData();
	    if (file_exists($data['absolutePath']) === false) {
	        return rename($data['oldAbsolutePath'], $data['absolutePath']);
	    }
	    
	    return true;
	}
	
	/**
	 * Remove file on disk
	 *
	 * @param FileEvent $event
	 */
	public function onRemoveFile(FileEvent $event) {
	    $data = $event->getData();
	    if (file_exists($data['absolutePath']) === true) {
	        unlink($data['absolutePath']);
	    }
	    
	    return true;
	}
	
	
	/**
	 * Copy or upload file from API's other than File
	 *
	 * @param FileEvent $event
	 */
	public function onCopyFile(FileEvent $event) {
	    $data = $event->getData();
	    $user = $data['user'];
	    
	    $erFolder = $this->em->getRepository(Folder::class);
	    $criteria = [
	        'slug'         => $data['folder'],
	        'createdBy'    => $user->getId(),
	        'overwritable' => false // App created by system
	    ];
	    
	    if (isset($data['folder']) && $data['folder']) {
	        
	        $folder = $erFolder->find($data['folder']) ?? $erFolder->findOneBy($criteria);
	        
	        if ($folder === null){
	            $folder = new Folder();
	            $folder->setName($data['folder']);
	            $folder->setOverwritable(false);
	            
	            $this->em->persist($folder);
	            $this->em->flush($folder);
	            
	            $folder = self::onCreateFolder(new FolderEvent($folder, ['user' => $user]));
	        }
	    }else{
	        
	        $folder = $this->em->getRepository(Folder::class)->findOneBy($criteria);
	        
	        if ($folder === null) {
	            $folder = new Folder();
	            $folder->createDefault($user);
	            
	            $this->em->persist($folder);
	            $this->em->flush($folder);
	            
	            $folder = $this->mediaManager->createFolder($folder, $user);
	        }
	    }

	    if ($folder !== null && isset($data['path']) && $data['path'] !== null) {
	        $er = $this->em->getRepository(File::class);
	        if ($file = $er->findOneBy(['path' => $data['path']]) || $file = $er->find($data['path'])) {
	            if ($file !== null && file_exists($file->getAbsolutePath())) {
	                if (isset($data['preserve_files']) === true && $data['preserve_files'] === false) {
	                    $this->mediaManager->moveFile($file, $folder);
	                }else {
	                    $overwritable = isset($data['overwritable']) ? $data['overwritable'] : false;
	                    $this->mediaManager->copyFile($file, $folder, $overwritable);
	                }
	            }
	        }else {
	            if (MediaUtil::isValidFromUrl($data['path'])){
	                $response = $this->mediaUploader->uploadFromUrl($data['path'], $folder->getId());
	                
	                $file = new File();
	                $file->setName($response['name']);
	                $file->setExtension($response['extension']);
	                $file->setPath($folder->getPath().'/'.$response['name']);
	                $file->setSize(filesize($folder->getAbsolutePath().'/'.$response['name']));
	                
	                $this->em->persist($file);
	                
	                $folder->addFile($file->getId());
	                
	                $this->em->flush();
	            }
	        }
	        
	        $closure = $data['closure'] ?? null;
	        if ($closure) {
	            $closure($folder->getPath().'/'.$file->getName());
	            $this->em->flush();
	        }
	    }
	    
	    return true;
	}
}

?>

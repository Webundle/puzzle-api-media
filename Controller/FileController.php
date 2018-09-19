<?php

namespace Puzzle\Api\MediaBundle\Controller;

use JMS\Serializer\SerializerInterface;
use Puzzle\Api\MediaBundle\PuzzleApiMediaEvents;
use Puzzle\Api\MediaBundle\Entity\File;
use Puzzle\Api\MediaBundle\Entity\Folder;
use Puzzle\Api\MediaBundle\Event\FileEvent;
use Puzzle\Api\MediaBundle\Service\MediaManager;
use Puzzle\Api\MediaBundle\Service\MediaUploader;
use Puzzle\OAuthServerBundle\Controller\BaseFOSRestController;
use Puzzle\OAuthServerBundle\Service\ErrorFactory;
use Puzzle\OAuthServerBundle\Service\Repository;
use Puzzle\OAuthServerBundle\Service\Utils;
use Puzzle\OAuthServerBundle\Util\FormatUtil;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class FileController extends BaseFOSRestController
{
    /**
     * @var MediaManager
     */
    protected $mediaManager;
    
    /**
     * @var MediaUploader
     */
    protected $mediaUploader;
    
    /**
     * @param RegistryInterface         $doctrine
     * @param Repository                $repository
     * @param SerializerInterface       $serializer
     * @param EventDispatcherInterface  $dispatcher
     * @param ErrorFactory              $errorFactory
     * @param MediaManager              $mediaManager
     * @param MediaUploader             $mediaUploader
     */
    public function __construct(
        RegistryInterface $doctrine,
        Repository $repository,
        SerializerInterface $serializer,
        ErrorFactory $errorFactory,
        EventDispatcherInterface $dispatcher,
        MediaManager $mediaManager,
        MediaUploader $mediaUploader
    ){
        $this->mediaManager = $mediaManager;
        $this->mediaUploader = $mediaUploader;
        $this->fields = ['name', 'caption', 'path', 'size', 'extension'];
        
        parent::__construct($doctrine, $repository, $serializer, $dispatcher, $errorFactory);
    }
    
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Get("/files")
	 */
	public function getMediaFilesAction(Request $request) {
	    $query = Utils::blameRequestQuery($request->query, $this->getUser());
	    $response = $this->repository->filter($query, File::class, $this->connection);
	    
	    return $this->handleView(FormatUtil::formatView($request, $response));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Get("/files/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("user", class="PuzzleApiMediaBundle:File")
	 */
	public function getMediaFileAction(Request $request, File $file) {
	    if ($file->getCreatedBy()->getId() !== $this->getUser()->getId()) {
	        return $this->handleView($this->errorFactory->accessDenied($request));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, ['resources' => $file]));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Post("/files")
	 */
	public function postMediaFileAction(Request $request) {
	    /** @var Doctrine\ORM\EntityManager $em */
	    $em = $this->doctrine->getManager($this->connection);
	    
	    $data = $request->request->all();
	    $user = $this->getUser();
	    $folderId = $data['folder'] ?? null;
	    
	    if (! $folderId) {
	        $folder = $em->getRepository(Folder::class)->findOneBy([
	            'name'             => $user->getUsername(),
	            'createdBy'        => $user->getId(),
	            'overwritable'   => false
	        ]);
	        
	        if ($folder === null) {
	            $folder = new Folder();
	            $folder->setOverwritable(false);
	            $folder->setName($user->getUsername());
	            
	            $em->persist($folder);
	            $em->flush($folder);
	            
	            $folder = $this->mediaManager->createFolder($folder, $user);
	        }
	        
	        $folderId = $folder->getId();
	    }
	    
	    $dataUploadFromUrl = $this->mediaUploader->uploadFromUrl($data['url'], $folderId);
		$data = array_merge($data, $dataUploadFromUrl);
		
		/** @var File $file */
		$file = Utils::setter(new File(), $this->fields, $data);
		$em->persist($file);
		
		/* Classify file */
		$folder = $em->getRepository(Folder::class)->find($folderId);
		$folder->addFile($file->getId());
		
		$em->flush();
		
		return $this->handleView(FormatUtil::formatView($request, ['resources' => $file]));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Put("/files/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("file", class="PuzzleApiMediaBundle:File")
	 */
	public function putMediaFileAction(Request $request, File $file) {
	    if ($file->getCreatedBy()->getId() !== $this->getUser()->getId()) {
	        return $this->handleView($this->errorFactory->badRequest($request));
	    }
	    
	    $data = $request->request->all();
		
	    $oldAbsolutePath = null;
	    if (isset($data['name']) && $data['name'] !== null) {
	        $oldAbsolutePath = $file->getAbsolutePath();
	        $file->setName($data['name']);
	    }
	    
	    if (isset($data['caption']) && $data['caption'] !== null) {
	        $file->setName($data['caption']);
	    }
	    
	    /** @var Doctrine\ORM\EntityManager $em */
		$em = $this->doctrine->getManager($this->connection);
		$em->flush();
		
		if ($oldAbsolutePath !== $file->getAbsolutePath()) {
		    $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_RENAME_FILE, new FileEvent([
		        'oldAbsolutePath' => $oldAbsolutePath,
		        'absolutePath' => $file->getAbsolutePath()
		    ]));
		}
		
		return $this->handleView(FormatUtil::formatView($request, ['code' => 200]));	
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Delete("/files/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("file", class="PuzzleApiMediaBundle:File")
	 */
	public function deleteMediaFileAction(Request $request, File $file) {
	    if ($file->getCreatedBy()->getId() !== $this->getUser()->getId()) {
	        return $this->handleView($this->errorFactory->badRequest($request));
	    }
	    
	    $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_REMOVE_FILE, new FileEvent([
	        'absolutePath' => $file->getAbsolutePath()
	    ]));
	    
	    /** @var Doctrine\ORM\EntityManager $em */
		$em = $this->doctrine->getManager($this->connection);
		$em->remove($file);
		$em->flush();
		
		return $this->handleView(FormatUtil::formatView($request, ['code' => 200]));
	}
}
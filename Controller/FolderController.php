<?php

namespace Puzzle\Api\MediaBundle\Controller;

use JMS\Serializer\SerializerInterface;
use Puzzle\Api\MediaBundle\PuzzleApiMediaEvents;
use Puzzle\Api\MediaBundle\Entity\Folder;
use Puzzle\Api\MediaBundle\Event\FolderEvent;
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
class FolderController extends BaseFOSRestController
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
        $this->fields = ['name', 'description', 'overwritable', 'allowedExtensions', 'parent'];
        
        parent::__construct($doctrine, $repository, $serializer, $dispatcher, $errorFactory);
    }
    
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Get("/folders")
	 */
	public function getMediaFoldersAction(Request $request) {
	    $query = Utils::blameRequestQuery($request->query, $this->getUser());
	    $response = $this->repository->filter($query, Folder::class, $this->connection);
	    
	    return $this->handleView(FormatUtil::formatView($request, $response));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Get("/folders/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("user", class="PuzzleApiMediaBundle:Folder")
	 */
	public function getMediaFolderAction(Request $request, Folder $folder) {
	    if ($folder->getCreatedBy()->getId() !== $this->getUser()->getId()) {
	        return $this->handleView($this->errorFactory->accessDenied($request));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, ['resources' => $folder]));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Post("/folders")
	 */
	public function postMediaFolderAction(Request $request) {
	    /** @var Doctrine\ORM\EntityManager $em */
	    $em = $this->doctrine->getManager($this->connection);
	    
	    $data = $request->request->all();
	    $user = $this->getUser();
	    $parentId = $data['parent'] ?? null;
	    
	    if ($parentId === null) {
	        // Home folder
	        $parent = $em->getRepository(Folder::class)->findOneBy([
	            'name'             => $user->getUsername(),
	            'createdBy'        => $user->getId(),
	            'overwritable'     => false
	        ]);
	        
	        if ($parent === null) {
	            $parent = new Folder();
	            $parent->setOverwritable(false);
	            $parent->setName($user->getUsername());
	            
	            $em->persist($parent);
	            $em->flush($parent);
	            
	            $parent = $this->mediaManager->createFolder($parent, $user);
	        }
	        
	        $parentId = $parent->getId();
	    }else {
	        $parent = $em->getRepository(Folder::class)->find($data['parent']);
	    }
	    
	    $data['parent'] = $parent;
	    $data['overwritable'] = true;
	    /** @var Folder $folder */
	    $folder = Utils::setter(new Folder(), $this->fields, $data);
	    
	    $em->persist($folder);
	    $em->flush();
	    
	    $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_CREATE_FOLDER, new FolderEvent($folder, [
	        'user' => $this->getUser()
	    ]));
	    
	    return $this->handleView(FormatUtil::formatView($request, ['resources' => $folder]));
	}
	
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Put("/folders/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function putMediaFolderAction(Request $request, Folder $folder) {
	    $user = $this->getUser();
	    
	    if ($folder->getCreatedBy()->getId() !== $user->getId()) {
	        return $this->handleView($this->errorFactory->badRequest($request));
	    }
	    
	    /** @var Doctrine\ORM\EntityManager $em */
	    $em = $this->doctrine->getManager($this->connection);
	    
	    $data = $request->request->all();
	    $data['parent'] = isset($data['parent']) && $data['parent'] ? $em->getRepository(Folder::class)->find($data['parent']) : null;
	    $oldAbsolutePath = isset($data['name']) && $data['name'] !== null ? $folder->getAbsolutePath() : null;
	    
	    /** @var Folder $folder */
	    $folder = Utils::setter($folder, $this->fields, $data);
	    
	    $em = $this->doctrine->getManager($this->connection);
	    $em->flush();
	    
	    if ($oldAbsolutePath !== $folder->getAbsolutePath()){
	        $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_RENAME_FOLDER, new FolderEvent($folder, [
	            'oldAbsolutePath' => $oldAbsolutePath
	        ]));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, ['code' => 200]));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Put("/folders/{id}/add-files")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function putMediaFolderAddFilesAction(Request $request, Folder $folder) {
	    $user = $this->getUser();
	    
	    if ($folder->getCreatedBy()->getId() !== $user->getId()) {
	        return $this->handleView($this->errorFactory->badRequest($request));
	    }
	    
	    $data = $request->request->all();
	    if (isset($data['files_to_add']) && count($data['files_to_add']) > 0) {
	        $filesToAdd = $data['files_to_add'];
	        foreach ($filesToAdd as $file) {
	            $folder->addFile($file);
	        }
	        
	        /** @var Doctrine\ORM\EntityManager $em */
	        $em = $this->doctrine->getManager($this->connection);
	        $em->flush();
	        
	        $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_ADD_FILES_TO_FOLDER, new FolderEvent($folder, [
	            'files_to_add'  => $filesToAdd,
	            'user'          => $user
	        ]));
	        
	        return $this->handleView(FormatUtil::formatView($request, ['code' => 200]));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, ['code' => 304]));
	}
	
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Put("/folders/{id}/remove-files")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function putMediaFolderRemoveFilesAction(Request $request, Folder $folder) {
	    $user = $this->getUser();
	    
	    if ($folder->getCreatedBy()->getId() !== $user->getId()) {
	        return $this->handleView($this->errorFactory->badRequest($request));
	    }
	    
	    $data = $request->request->all();
	    if (isset($data['files_to_remove']) && count($data['files_to_remove']) > 0) {
	        $filesToRemove = $data['files_to_remove'];
	        foreach ($filesToRemove as $file) {
	            $folder->removeFile($file);
	        }
	        
	        /** @var Doctrine\ORM\EntityManager $em */
	        $em = $this->doctrine->getManager($this->connection);
	        $em->flush();
	        
	        $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_REMOVE_FILES_TO_FOLDER, new FolderEvent($folder, [
	            'files_to_remove'  => $filesToRemove,
	            'user'             => $user
	        ]));
	        
	        return $this->handleView(FormatUtil::formatView($request, ['code' => 200]));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, ['code' => 304]));
	}
	
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Delete("/folders/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function deleteMediaFolderAction(Request $request, Folder $folder) {
	    if ($folder->getCreatedBy()->getId() !== $this->getUser()->getId()) {
	        return $this->handleView($this->errorFactory->badRequest($request));
	    }
	    
	    $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_REMOVE_FOLDER, new FolderEvent($folder));
	    
	    /** @var Doctrine\ORM\EntityManager $em */
	    $em = $this->doctrine->getManager($this->connection);
	    $em->remove($folder);
	    $em->flush();
	    
	    return $this->handleView(FormatUtil::formatView($request, ['code' => 200]));
	}
}
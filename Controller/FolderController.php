<?php

namespace Puzzle\Api\MediaBundle\Controller;

use Puzzle\Api\MediaBundle\PuzzleApiMediaEvents;
use Puzzle\Api\MediaBundle\Entity\Folder;
use Puzzle\Api\MediaBundle\Event\FolderEvent;
use Puzzle\OAuthServerBundle\Controller\BaseFOSRestController;
use Puzzle\OAuthServerBundle\Service\Utils;
use Puzzle\OAuthServerBundle\Util\FormatUtil;
use Symfony\Component\HttpFoundation\Request;
use Puzzle\Api\MediaBundle\Entity\File;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class FolderController extends BaseFOSRestController
{
    public function __construct() {
        parent::__construct();
        $this->fields = ['name', 'description', 'overwritable', 'allowedExtensions', 'parent'];
    }
    
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Get("/folders")
	 */
	public function getMediaFoldersAction(Request $request) {
	    $query = Utils::blameRequestQuery($request->query, $this->getUser());
	    
	    /** @var Puzzle\OAuthServerBundle\Service\Repository $repository */
	    $repository = $this->get('papis.repository');
	    $response = $repository->filter($query, Folder::class, $this->connection);
	    
	    return $this->handleView(FormatUtil::formatView($request, $response));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Get("/folders/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("user", class="PuzzleApiMediaBundle:Folder")
	 */
	public function getMediaFolderAction(Request $request, Folder $folder) {
	    if ($folder->getCreatedBy()->getId() !== $this->getUser()->getId()) {
	        /** @var Puzzle\OAuthServerBundle\Service\ErrorFactory $errorFactory */
	        $errorFactory = $this->get('papis.error_factory');
	        return $this->handleView($errorFactory->accessDenied($request));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, $folder));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Post("/folders")
	 */
	public function postMediaFolderAction(Request $request) {
	    /** @var Doctrine\ORM\EntityManager $em */
	    $em = $this->get('doctrine')->getManager($this->connection);
	    
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
	            
	            $parent = $this->get('papis.media_manager')->createFolder($parent, $user);
	        }
	        
	        $parentId = $parent->getId();
	    }else {
	        $parent = $em->getRepository(Folder::class)->find($data['parent']);
	    }
	    
	    $data['parent'] = $parent;
	    $data['overwritable'] = true;
	    
	    /** @var Puzzle\Api\MediaBundle\Entity\Folder $folder */
	    $folder = Utils::setter(new Folder(), $this->fields, $data);
	    
	    $em->persist($folder);
	    $em->flush();
	    
	    /** @var Symfony\Component\EventDispatcher\EventDispatcher $dispatcher */
	    $dispatcher = $this->get('event_dispatcher');
	    $dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_CREATE_FOLDER, new FolderEvent($folder, [
	        'user' => $this->getUser()
	    ]));
	    
	    return $this->handleView(FormatUtil::formatView($request, $folder));
	}
	
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Put("/folders/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function putMediaFolderAction(Request $request, Folder $folder) {
	    $user = $this->getUser();
	    
	    if ($folder->getCreatedBy()->getId() !== $user->getId()) {
	        /** @var Puzzle\OAuthServerBundle\Service\ErrorFactory $errorFactory */
	        $errorFactory = $this->get('papis.error_factory');
	        return $this->handleView($errorFactory->badRequest($request));
	    }
	    
	    /** @var Doctrine\ORM\EntityManager $em */
	    $em = $this->get('doctrine')->getManager($this->connection);
	    
	    $data = $request->request->all();
	    $data['parent'] = isset($data['parent']) && $data['parent'] ? $em->getRepository(Folder::class)->find($data['parent']) : null;
	    $oldAbsolutePath = isset($data['name']) && $data['name'] !== null ? $folder->getAbsolutePath() : null;
	    
	    /** @var Puzzle\Api\MediaBundle\Entity\Folder $folder */
	    $folder = Utils::setter($folder, $this->fields, $data);
	    
	    if ($oldAbsolutePath !== $folder->getAbsolutePath()) {
	        /** @var Symfony\Component\EventDispatcher\EventDispatcher $dispatcher */
	        $dispatcher = $this->get('event_dispatcher');
	        $dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_RENAME_FOLDER, new FolderEvent($folder, [
	            'oldAbsolutePath' => $oldAbsolutePath
	        ]));
	    }
	    
	    $em->flush();
	    
	    return $this->handleView(FormatUtil::formatView($request, $folder));
	}
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Put("/folders/{id}/add-files")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function putMediaFolderAddFilesAction(Request $request, Folder $folder) {
	    $user = $this->getUser();
	    
	    if ($folder->getCreatedBy()->getId() !== $user->getId()) {
	        /** @var Puzzle\OAuthServerBundle\Service\ErrorFactory $errorFactory */
	        $errorFactory = $this->get('papis.error_factory');
	        return $this->handleView($errorFactory->badRequest($request));
	    }
	    
	    $data = $request->request->all();
	    $filesToAdd = $data['files_to_add'] ? explode(',', $data['files_to_add']) : null;
	    if ($filesToAdd !== null) {
	        /** @var Doctrine\ORM\EntityManager $em */
	        $em = $this->get('doctrine')->getManager($this->connection);
	        
	        foreach ($filesToAdd as $fileId) {
	            $file = $em->getRepository(File::class)->find($fileId);
	            $folder->addFile($file);
	        }
	        
	        $em->flush();
	        
	        $this->dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_ADD_FILES_TO_FOLDER, new FolderEvent($folder, [
	            'files_to_add'  => $filesToAdd,
	            'user'          => $user
	        ]));
	        
	        return $this->handleView(FormatUtil::formatView($request, $folder));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, null, 204));
	}
	
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Put("/folders/{id}/remove-files")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function putMediaFolderRemoveFilesAction(Request $request, Folder $folder) {
	    $user = $this->getUser();
	    
	    if ($folder->getCreatedBy()->getId() !== $user->getId()) {
	        /** @var Puzzle\OAuthServerBundle\Service\ErrorFactory $errorFactory */
	        $errorFactory = $this->get('papis.error_factory');
	        return $this->handleView($errorFactory->badRequest($request));
	    }
	    
	    $data = $request->request->all();
	    $filesToRemove = $data['files_to_remove'] ? explode(',', $data['files_to_remove']) : null;
	    if ($filesToRemove !== null) {
	        /** @var Doctrine\ORM\EntityManager $em */
	        $em = $this->get('doctrine')->getManager($this->connection);
	        
	        foreach ($filesToRemove as $fileId) {
	            $file = $em->getRepository(File::class)->find($fileId);
	            $folder->removeFile($file);
	        }
	        
	        $em->flush();
	        
	        /** @var Symfony\Component\EventDispatcher\EventDispatcher $dispatcher */
	        $dispatcher = $this->get('event_dispatcher');
	        $dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_REMOVE_FILES_TO_FOLDER, new FolderEvent($folder, [
	            'files_to_remove'  => $filesToRemove,
	            'user'             => $user
	        ]));
	        
	        return $this->handleView(FormatUtil::formatView($request, $folder));
	    }
	    
	    return $this->handleView(FormatUtil::formatView($request, null, 204));
	}
	
	
	/**
	 * @FOS\RestBundle\Controller\Annotations\View()
	 * @FOS\RestBundle\Controller\Annotations\Delete("/folders/{id}")
	 * @Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter("folder", class="PuzzleApiMediaBundle:Folder")
	 */
	public function deleteMediaFolderAction(Request $request, Folder $folder) {
	    if ($folder->getCreatedBy()->getId() !== $this->getUser()->getId()) {
	        /** @var Puzzle\OAuthServerBundle\Service\ErrorFactory $errorFactory */
	        $errorFactory = $this->get('papis.error_factory');
	        return $this->handleView($errorFactory->badRequest($request));
	    }
	    
	    /** @var Symfony\Component\EventDispatcher\EventDispatcher $dispatcher */
	    $dispatcher = $this->get('event_dispatcher');
	    $dispatcher->dispatch(PuzzleApiMediaEvents::MEDIA_REMOVE_FOLDER, new FolderEvent($folder));
	    
	    /** @var Doctrine\ORM\EntityManager $em */
	    $em = $this->get('doctrine')->getManager($this->connection);
	    $em->remove($folder);
	    $em->flush();
	    
	    return $this->handleView(FormatUtil::formatView($request, null, 204));
	}
}
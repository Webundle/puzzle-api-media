<?php

namespace Puzzle\Api\MediaBundle\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManager;
use Puzzle\OAuthServerBundle\Entity\User;
use Puzzle\Api\MediaBundle\Entity\File as MediaFile;
use Puzzle\Api\MediaBundle\Entity\Picture;
use Puzzle\Api\MediaBundle\Entity\Audio;
use Puzzle\Api\MediaBundle\Entity\Video;
use Puzzle\Api\MediaBundle\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Puzzle\Api\MediaBundle\Entity\Folder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 */
class MediaUploader
{
	/**
	 * @var EntityManager $em
	 */
	protected $em;
	
	/**
	 * @var MediaManager $mediaManager
	 */
	protected $mediaManager;
	
	/**
	 * @var string $name
	 */
	protected $name;
	
	/**
	 * @var string $extension
	 */
	protected $extension;
	
	/**
	 * @var string $filename
	 */
	protected $filename;
	
	/**
	 * @var Folder $folder
	 */
	protected $folder;
	
	/**
	 * @var User $user
	 */
	protected $user;
	
	/**
	 * @var int $maxSize
	 */
	protected $maxSize;
	
	/**
	 * @param EntityManagerInterface   $em
	 * @param MediaManager             $mediaManager
	 */
	public function __construct(EntityManagerInterface $em, MediaManager $mediaManager, int $maxSize){
		$this->em = $em;
		$this->mediaManager = $mediaManager;
		$this->maxSize = $maxSize;
	}
	
	/**
	 * Prepare Upload
	 *
	 * @param array        $globalFiles
	 * @param Folder  $folder
	 * @param User         $user
	 * @return array
	 */
	public function prepareUpload(array $globalFiles, Folder $folder, User $user)
	{
		$results = [];
		if(count($globalFiles) > 0 ){
			foreach ($globalFiles as $globalFile){
				$originalNames = $globalFile['name'];
				$mimeTypes = $globalFile['type'];
				$path = $globalFile['tmp_name'];
				$errors = $globalFile['error'];
				$size = $globalFile['size'];
			}
			
			if(! is_array($originalNames)){
				$originalNames = [$originalNames];
				$mimeTypes = [$mimeTypes];
				$path = [$path];
				$errors = [$errors];
				$size = [$size];
			}
	
			$length = count($originalNames);
			for ($i = 0; $i < $length; $i++){
				if($originalNames[$i] != null){
					$file = new UploadedFile($path[$i], $originalNames[$i], $mimeTypes[$i], $size[$i]);
					$results[] = $this->upload($file, $folder, $user);
				}
			}
		}
	
		return $results;
	}
	
	/**
	 * Upload file
	 * 
	 * @param UploadedFile     $file
	 * @param Folder      $folder
	 * @return null | MediaFile
	 */
	public function upload(UploadedFile $file, Folder $folder)
	{
	    $this->user = $user;
	    $this->folder = $folder;
	    $this->name = utf8_encode($file->getClientOriginalName());
		$this->extension = $file->getClientOriginalExtension();
		$this->filename = $folder->getAbsolutePath().'/'.$this->name;
		// Rename file if it already exists
		$this->filename = $this->mediaManager->rename($this->filename);
		$name = basename($this->filename);
		 // Upload File
		$file = $file->move($this->folder->getAbsolutePath(), $this->name);
		
		return [
		    'name' => $this->name,
		    'path' => $this->folder->getPath().'/'.$this->name,
		    'extension' => $this->extension,
		    'size' => filesize($this->filename)
		];
	}
	
	/**
	 * Upload File from remote url
	 * 
	 * @param User $user
	 * @param array $data
	 * @return string|number|MediaFile
	 */
	public function uploadFromUrl($url, $folderId)
	{
	    $url_ary = [];
	    $pattern = '#^(.*://)?([\w\-\.]+)\:?([0-9]*)/(.*)$#';
	    
	    if (preg_match($pattern, $url, $url_ary) && !empty($url_ary[4])) {
	        $parts = explode("?", substr($url_ary[4],strrpos($url_ary[4],"/")+1));
	        $this->name = $parts[0];
	        $base_get = '/' . $url_ary[4];
	        $port = ( !empty($url_ary[3]) ) ? $url_ary[3] : 80;
	        
	        if ($this->name == ""){
	            return self::ERROR_FILE_NAME;
	        }
	        
	        if (!($fsock = fsockopen($url_ary[2], $port))) {
	            return self::ERROR_NETWORK;
	        }
	        
	        fputs($fsock, "GET $base_get HTTP/1.1\r\n");
	        fputs($fsock, "Host: " . $url_ary[2] . "\r\n");
	        fputs($fsock, "Accept-Language: fr\r\n");
	        fputs($fsock, "Accept-Encoding: UTF-8\r\n");
	        fputs($fsock, "User-Agent: PHP\r\n");
	        fputs($fsock, "Connection: close\r\n\r\n");
	        
	        $data = null;
	        //    			unset($data);
	        while (!feof($fsock)) {
	            $data .= fread($fsock, $this->maxSize);
	        }
	        
	        fclose($fsock);
	        
	        $matchesContentLength = $matchesContentType  = [];
	        if (!preg_match('#Content-Length\: ([0-9]+)[^ /][\s]+#i', $data, $matchesContentLength)
// 	            ||!preg_match('#Content-Type\: image/[x\-]*([a-z]+)[\s]+#i', $data, $matchesContentType)
	        ){
	            return 13; //Error downloading file...https...No data
	        }
	        
	        $filesize = $matchesContentLength[1];
// 	        $filetype = $matchesContentType[1];
	        
	        if ($filesize > 0 && $filesize < $this->maxSize) {
	            $data = substr($data, strlen($data) - $filesize, $filesize);
	            $this->folder = $this->em->getRepository(Folder::class)->find($folderId);
	            if (! $this->folder){
	                throw new ResourceNotFoundException();
	            }
	            
	            $this->filename = $this->folder->getAbsolutePath().'/'.$this->name;
	            
	            if (! file_exists($this->folder->getAbsolutePath())) {
	                mkdir($this->folder->getAbsolutePath(), 0777, true);
	            }
	            
	            if (! file_exists($this->filename)){
	                goto WRITE_DATA;
	            }
	            
	            // Extract extension
	            $splitFilename = explode('.', $this->filename);
	            $this->extension = $splitFilename[count($splitFilename) - 1];
	            // Rename filename
	            $this->filename = $this->mediaManager->rename($this->filename);
	            $this->name = basename($this->filename);
	            
	            WRITE_DATA:
	            $fptr = fopen($this->filename, 'wb');
	            $bytes_written = fwrite($fptr, $data, $filesize);
	            fclose($fptr);
	            
	            if ($bytes_written != $filesize){
	                unlink($this->filename);
	                return self::ERROR_WRITING;
	            }
	            return [
	                'name' => $this->name,
	                'path' => $this->folder->getPath().'/'.$this->name,
	                'extension' => $this->extension,
	                'size' => filesize($this->filename)
	            ];
	        }
	        else {
	            return self::ERROR_FILE_SIZE;
	        }
	    }
	}
}

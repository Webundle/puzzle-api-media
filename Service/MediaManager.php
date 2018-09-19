<?php

namespace Puzzle\Api\MediaBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Puzzle\OAuthServerBundle\Entity\User;
use Puzzle\Api\MediaBundle\Entity\File;
use Puzzle\Api\MediaBundle\Entity\Picture;
use Puzzle\Api\MediaBundle\Entity\Audio;
use Puzzle\Api\MediaBundle\Entity\Video;
use Puzzle\Api\MediaBundle\Entity\Document;
use Puzzle\Api\MediaBundle\Entity\Folder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Puzzle\OAuthServerBundle\Service\ErrorFactory;

/**
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 */
class MediaManager {
    
    /**
     * @param EntityManagerInterface $em
     */
    protected $em;
    
    /**
     * @param ErrorFactory $errorFactory
     */
    protected $errorFactory;
    
    /**
     * @param EntityManagerInterface    $em
     * @param ErrorFactory              $errorFactory
     */
    public function __construct(EntityManagerInterface $em, ErrorFactory $errorFactory) {
        $this->em = $em;
        $this->errorFactory = $errorFactory;
    }
    
    /**
     * Create folder
     * 
     * @param string            $folderTarget
     * @param User              $user
     * @param bool              $apply
     * @return Folder
     */
    public function createFolder(Folder $folder, User $user, bool $apply = true) {
        if ($folder->isDefault($user) === false) {
            $folderName = $folder->getName();
            if (file_exists($folder->getAbsolutePath())) {
                if ($folder->isOverwritable() === false) {
                    $message = $this->translator->trans('message.400.media.folder_not_overwritable', [], 'error');
                    throw new BadRequestHttpException($message);
                }
                
                $filename = self::rename($folderName, dirname($folder->getAbsolutePath()), null, false);
                $folderName = basename($filename);
            }
            
            $folder->setName($folderName);
            $this->em->flush($folder);
        }
        
        if ($apply === true && file_exists($folder->getAbsolutePath()) === false) {
            mkdir($folder->getAbsolutePath(), 0777, true);
        }
        
        return $folder;
    }
    
    /**
     * Remove folder and its content
     * 
     * @param string $folderPath
     * @return boolean
     */
    public function removeFolder(string $folderPath) {
        $files = scandir($folderPath, SCANDIR_SORT_DESCENDING);
        foreach ($files as $file) {
            if ($file !== "." && $file !== "..") {
                $file = $folderPath.'/'.$file;
                is_dir($file) ? self::removeFolder($file) : unlink($file);
            }
        }
        
        return rmdir($folderPath);
    }
    
    /**
     * Rename directory
     *
     * @param string $name
     * @param string $folderPath
     * @param string $extension
     * @param bool $applyOnDisk
     * @return string
     */
    public function renameFolder(Folder $folder, $oldAbsolutePath, bool $applyOnDisk = true) {
        $childs = $folder->getChilds();
        
        foreach ($childs as $child) {
            $childOldAbsolutePath = $child->getAbsolutePath();
            $child->setPath();
            if ($child->getChilds()) {
                self::renameFolder($child, $childOldAbsolutePath, false);
            }
            
            if ($child->getFiles()){
                foreach ($child->getFiles() as $fileId){
                    $file = $this->em->getRepository(File::class)->find($fileId);
                    $file->setPath($child->getAbsolutePath().'/'.$file->getName());
                }
            }
        }
        
        if ($applyOnDisk === true) {
            $filename = $folder->getAbsolutePath();
            $filename = self::rename($filename);
            
            $name = basename($filename);
            $folder->setName($name);
            
            rename($oldAbsolutePath, $folder->getAbsolutePath());
        }
        
        $this->em->flush();
        return $folder->getAbsolutePath();
    }
    
    /**
     * Rename file 
     * 
     * @param string $name
     * @param string $folderPath
     * @param string $extension
     * @param bool $applyOnDisk
     * @return string
     */
    public function renameFile(File $file, $oldAbsolutePath, bool $applyOnDisk = true) {
        $filename = self::rename($file->getAbsolutePath());
        rename($oldAbsolutePath, $filename);
        
        $file->setName(basename($filename));
        $this->em->flush();
        
        return $filename;
    }
    
    /**
     * Rename by overwritting
     * 
     * @param string $filename
     * @return string
     */
    public function rename(string $filename){
        $count = 0;
        $extension = null;
        $dirname = dirname($filename);
        
        if (is_file($filename)) {
            $splitFilename = explode('.', $filename);
            $extension = $splitFilename[count($splitFilename) - 1];
        }
        // Rename duplicate file
        while (file_exists($filename)){
            $suffix = $extension !== null ? '.'.$extension : null;
            $filename = $dirname.'/'.basename($filename, $suffix);
            $basename = basename($filename, '('.$count.')');
            
            $count++;
            $name = $extension !== null ? $basename.'('.$count.').'.$extension : $basename.'('.$count.')';
            $filename = $dirname.'/'.$name;
        }
        
        return $filename;
    }
    
    /**
     * Copy file
     * 
     * @param File     $file
     * @param Folder   $folder
     * @return File
     */
    public function copyFile(File $file, Folder $folder, bool $overwritable = false)
    {
        $name= $file->getName();
        $extension = $file->getExtension();
        
        if ($overwritable === false){ // Rename file
            $filename = self::rename($name, $folder->getAbsolutePath(), $extension);
        }else { // Overwrite
            $filename = $folder->getPath().'/'.$name;
        }
        
        $name = basename($filename);
        //Copy file on local storage
        copy($file->getAbsolutePath(), $folder->getAbsolutePath().'/'.$name);
        // Save file on database
        $newFile = new File();
        $newFile->setName(utf8_encode($name));
        $newFile->setPath($folder->getPath().'/'.utf8_encode($name));
        $newFile->setExtension($extension);
        $newFile->setSize($file->getSize());
        $newFile->setUser($file->getUser());
        
        $this->em->persist($newFile);
        $folder->addFile($newFile->getId());
        
        // Categorized file
        if ($newFile->isPicture()) {
            $fileType = new Picture($filename);
        }elseif ($newFile->isAudio()) {
            $fileType = new Audio();
        }elseif ($newFile->isVideo()) {
            $fileType = new Video();
        }elseif ($newFile->isDocument()) {
            $fileType = new Document();
        }
        
        if ($fileType !== null) {
            $fileType->setFile($newFile);
            $this->em->persist($fileType);
        }
        
        $this->em->flush();
        return $newFile;
    }
    
    /**
     * Move file
     * 
     * @param File     $file
     * @param Folder   $folder
     * return File
     */
    public function moveFile(File $file, Folder $folder) {
        // Rename file if already exist
        $filename = self::rename($file->getName(), $folder->getAbsolutePath(), $file->getExtension());
        $name = basename($filename);
        //Copy file on local storage
        copy($file->getAbsolutePath(), $folder->getAbsolutePath().'/'.$name);
        // Delete old file
        unlink($file->getAbsolutePath());
        // Update file infos in database
        $file->setName($name);
        $file->setPath($folder->getPath().'/'.$name);
        
        $this->em->flush();
        return $file;
    }
    
    
    /**
     * Add files and sub-directories in a folder to zip file.
     * @param string        $folder
     * @param \ZipArchive   $zipFile
     * @param int           $exclusiveLength Number of text to be exclusived from the file path.
     */
    private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    self::folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    } 
    
    /**
     * Zip a folder (include itself).
     * Usage:
     *   HZip::zipDir('/path/to/sourceDir', '/path/to/out.zip');
     *
     * @param string $sourcePath Path of directory to be zip.
     * @param string $outZipPath Path of output zip file.
     * @return string
     */
    public function zipDir($source)
    {
        $outZipPath = $source . '.gz';
        $pathInfo = pathInfo($source);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];
        
        $z = new \ZipArchive();
        $z->open($outZipPath, \ZipArchive::CREATE);
        $z->addEmptyDir($dirName);
        self::folderToZip($source, $z, strlen("$parentPath/"));
        $z->close();
        
        return $outZipPath;
    }
}
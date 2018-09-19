<?php

namespace Puzzle\Api\MediaBundle\Util;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 * 
 */
class MediaUtil
{
    /**
     * Extract context
     * 
     * @param string $class
     * @return string
     */
    public static function extractFolderNameFromClass($class){
        $parts = explode('\\', $class);
        return strtolower($parts[count($parts) - 1]);
    }
    
    public static function isValidFromUrl(string $url) {
        $array = [];
        $pattern = '#^(.*://)?([\w\-\.]+)\:?([0-9]*)/(.*)$#';
        
        if(preg_match($pattern, $url, $array) && !empty($array[2])){
            return true;
        }
        
        return false;
    }
    
    /**
     * Supported audio extensions
     *
     * @return string
     */
    public static function supportedAudioExtensions() {
        return 'mp3|wav|m4a|m4r|ogg';
    }
    
    /**
     * Supported picture extensions
     *
     * @return string
     */
    public static function supportedPictureExtensions() {
        return 'jpg|jpeg|png|ico|bmp';
    }
    
    /**
     * Supported video extensions
     *
     * @return string
     */
    public static function supportedVideoExtensions() {
        return 'avi|mp4|webm|flv';
    }
    
    /**
     * Supported document extensions
     *
     * @return string
     */
    public static function supportedDocumentExtensions() {
        return 'doc|docx|ppt|pptx|xls|txt|pdf|html|twig';
    }
}
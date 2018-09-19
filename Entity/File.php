<?php

namespace Puzzle\Api\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as JMS;
use Hateoas\Configuration\Annotation as Hateoas;

use Puzzle\OAuthServerBundle\Util\MediaUtil;
use Puzzle\OAuthServerBundle\Traits\Nameable;
use function GuzzleHttp\Psr7\mimetype_from_extension;
use Puzzle\OAuthServerBundle\Traits\PrimaryKeyable;
use Knp\DoctrineBehaviors\Model\Timestampable\Timestampable;
use Knp\DoctrineBehaviors\Model\Blameable\Blameable;

/**
 * File
 *
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 * @ORM\Table(name="media_file")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 * @JMS\ExclusionPolicy("all")
 * @JMS\XmlRoot("file")
 * @Hateoas\Relation(
 * 		name = "self", 
 * 		href = @Hateoas\Route(
 * 			"get_media_file", 
 * 			parameters = {"id" = "expr(object.getId())"},
 * 			absolute = true,
 * ))
 * * @Hateoas\Relation(
 *     name = "size_converted",
 *     embedded = "expr(object.sizeConvert())",
 *     exclusion = @Hateoas\Exclusion(excludeIf = "expr(object.getSize() === null)")
 * ))
 */
class File
{
    use PrimaryKeyable,
        Nameable,
        Timestampable,
        Blameable;
    
    /**
     * @var string
     * @ORM\Column(name="caption", type="string", length=255, nullable=true)
     * @JMS\Expose
  	 * @JMS\Type("string")
     */
    private $caption;
    
    /**
     * @ORM\Column(name="path", type="string", length=255)
     * @var string
     * @JMS\Expose
  	 * @JMS\Type("string")
     */
    private $path;
    
    /**
     * @ORM\Column(name="type", type="string", length=255)
     * @var string
     * @JMS\Expose
     * @JMS\Type("string")
     */
    private $type;
    
    /**
     * @var string
     * @ORM\Column(name="extension", type="string", length=255, nullable=true)
     * @JMS\Expose
  	 * @JMS\Type("string")
     */
    private $extension;

    /**
     * @var int
     * @ORM\Column(name="size", type="integer", nullable=true)
     * @JMS\Expose
  	 * @JMS\Type("string")
     */
    private $size;
    
    /**
     * @ORM\OneToOne(targetEntity="Picture", mappedBy="file", cascade={"persist", "remove"})
     */
    private $picture;
    
    /**
     * @ORM\OneToOne(targetEntity="Audio", mappedBy="file", cascade={"persist", "remove"})
     */
    private $audio;
    
    /**
     * @ORM\OneToOne(targetEntity="Video", mappedBy="file", cascade={"persist", "remove"})
     */
    private $video;
    
    /**
     * @ORM\OneToOne(targetEntity="Document", mappedBy="file", cascade={"persist", "remove"})
     */
    private $document;
    
    
    public function __construct(array $properties = null) {
        if (isset($properties['name']) === true) {
            $this->name = $properties['name'];
        }
        
        if (isset($properties['context']) === true) {
            $this->context = $properties['context'];
        }
        
        if (isset($properties['path']) === true) {
            $this->path = $properties['path'];
        }
    }

    public function setName($name) : self {
        $this->name = utf8_encode($name);
        return $this;
    }

    public function getName() :? string {
        return utf8_decode($this->name);
    }
    
    public function getOriginalName() :? string {
        return $this->name;
    }
    
    public function setCaption($caption){
        $this->caption = $caption;
        return $this;
    }
    
    public function getCaption(){
        return $this->caption;
    }
    
    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function setType() {
        $mimeTypeParts = explode('/', mimetype_from_extension($this->extension));
        $this->type = $mimeTypeParts[0];
    }
    
    public function getType() {
        return $this->type;
    }

    public function setExtension($extension) : self {
        $this->extension = $extension;
        return $this;
    }

    public function getExtension() :? string {
        return $this->extension;
    }

    public function setSize($size) : self {
        $this->size = $size;
        return $this;
    }

    public function getSize() :? int {
        return $this->size;
    }
    
    public function setPath($path) : self {
    	$this->path = $path;
    	return $this;
    }
    
    public function getPath() :? string {
    	return $this->path;
    }
    
    public static function getBaseDir(){
        return __DIR__ . '/../../../../../web';
    }
    
    public static function getBasePath(){
    	return '/uploads';
    }
    
    public function getAbsolutePath(){
    	return self::getBaseDir().$this->path;
    }

    public function setPicture(Picture $picture = null) : self {
        $this->picture = $picture;
        return $this;
    }

    public function getPicture() :? Picture {
        return $this->picture;
    }

    public function setAudio(Audio $audio = null) : self {
        $this->audio = $audio;
        return $this;
    }

    public function getAudio() :? Audio {
        return $this->audio;
    }

    public function setVideo(Video $video = null) : self {
        $this->video = $video;
        return $this;
    }

    public function getVideo() :? Video {
        return $this->video;
    }

    public function setDocument(Document $document = null) : self {
        $this->document = $document;
        return $this;
    }

    public function getDocument() :? Document {
        return $this->document;
    }
    
    public function isPicture() {
        return true === in_array($this->extension, explode('|', MediaUtil::supportedPictureExtensions()));
    }
    
    public function isAudio() {
        return true === in_array($this->extension, explode('|', MediaUtil::supportedAudioExtensions()));
    }
    
    public function isVideo() {
        return true === in_array($this->extension, explode('|', MediaUtil::supportedVideoExtensions()));
    }
    
    public function isDocument() {
        return true === in_array($this->extension, explode('|', MediaUtil::supportedDocumentExtensions()));
    }
    
    /**
     * Converts bytes into human readable file size.
     *
     * @param string $bytes
     * @return string human readable file size (2,87 Мб)
     * @author Mogilev Arseny
     */
    public function sizeConvert() {
        $bytes = floatval($this->size);
        $arBytes = [
            ["UNIT" => "TB", "VALUE" => pow(1024, 4)],
            ["UNIT" => "GB", "VALUE" => pow(1024, 3)],
            ["UNIT" => "MB", "VALUE" => pow(1024, 2)],
            ["UNIT" => "KB", "VALUE" => 1024],
            ["UNIT" => "B", "VALUE" => 1]
        ];
        
        foreach ($arBytes as $arItem){
            if ($bytes >= $arItem["VALUE"]){
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(".", "," , strval(round($result, 2)))." ".$arItem["UNIT"];
                break;
            }
        }
        
        return $result;
    }
}

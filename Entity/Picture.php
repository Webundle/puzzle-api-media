<?php

namespace Puzzle\Api\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as JMS;
use Hateoas\Configuration\Annotation as Hateoas;

use Puzzle\OAuthServerBundle\Traits\PrimaryKeyable;

/**
 * Media Picture
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 * @ORM\Table(name="media_file_picture")
 * @ORM\Entity(repositoryClass="Puzzle\Api\MediaBundle\Repository\PictureRepository")
 * @ORM\HasLifecycleCallbacks()
 * @JMS\ExclusionPolicy("all")
 * @JMS\XmlRoot("picture")
 * @Hateoas\Relation(
 * 		name = "self", 
 * 		href = @Hateoas\Route(
 * 			"get_media_picture", 
 * 			parameters = {"id" = "expr(object.getId())"},
 * 			absolute = true,
 * ))
 */
class Picture
{
    use PrimaryKeyable;

    /**
     * @var int
     * @ORM\Column(name="width", type="integer")
     * @JMS\Expose
  	 * @JMS\Type("integer")
     */
    private $width;

    /**
     * @var int
     * @ORM\Column(name="height", type="integer")
     * @JMS\Expose
  	 * @JMS\Type("integer")
     */
    private $height;
    
    /**
     * @ORM\OneToOne(targetEntity="File", inversedBy="picture")
     * @ORM\JoinColumn(name="file_id", referencedColumnName="id")
     */
    private $file;
    
    public function __construct(string $filename = null) {
        $image = getimagesize($filename);
        $this->width = $image[0];
        $this->height = $image[1];
    }
    
    public function setFile(File $file = null) : self {
        $this->file = $file;
        return $this;
    }
    
    public function getFile() :? File {
        return $this->file;
    }

    public function setWidth($width) : self {
        $this->width = $width;
        return $this;
    }

    public function getWidth() :? int {
        return $this->width;
    }

    public function setHeight($height) : self {
        $this->height = $height;
        return $this;
    }

    public function getHeight() :? int {
        return $this->height;
    }
}

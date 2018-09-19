<?php

namespace Puzzle\Api\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as JMS;
use Hateoas\Configuration\Annotation as Hateoas;

use Puzzle\OAuthServerBundle\Traits\PrimaryKeyable;

/**
 * Video
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 * @ORM\Table(name="media_file_video")
 * @ORM\Entity(repositoryClass="Puzzle\OAuthServerBundle\Repository\VideoRepository")
 * @ORM\HasLifecycleCallbacks()
 * @JMS\XmlRoot("video")
 * @Hateoas\Relation(
 * 		name = "self", 
 * 		href = @Hateoas\Route(
 * 			"get_media_video", 
 * 			parameters = {"id" = "expr(object.getId())"},
 * 			absolute = true,
 * ))
 */
class Video
{   
    use PrimaryKeyable;

    /**
     * @ORM\OneToOne(targetEntity="File", inversedBy="video")
     * @ORM\JoinColumn(name="file_id", referencedColumnName="id")
     */
    private $file;

    public function setFile(File $file = null) : self {
        $this->file = $file;
        return $this;
    }

    public function getFile() :? File {
        return $this->file;
    }
}

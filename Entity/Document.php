<?php

namespace Puzzle\Api\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as JMS;
use Hateoas\Configuration\Annotation as Hateoas;
use Puzzle\OAuthServerBundle\Traits\PrimaryKeyable;


/**
 * Document
 *
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 * 
 * @ORM\Table(name="media_file_document")
 * @ORM\Entity(repositoryClass="Puzzle\Api\MediaBundle\Repository\DocumentRepository")
 * @ORM\HasLifecycleCallbacks()
 * @JMS\XmlRoot("document")
 * @Hateoas\Relation(
 * 		name = "self", 
 * 		href = @Hateoas\Route(
 * 			"get_media_document", 
 * 			parameters = {"id" = "expr(object.getId())"},
 * 			absolute = true,
 * ))
 */
class Document
{
    use PrimaryKeyable;

    /**
     * @ORM\OneToOne(targetEntity="File", inversedBy="document")
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

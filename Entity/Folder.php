<?php

namespace Puzzle\Api\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as JMS;
use Hateoas\Configuration\Annotation as Hateoas;

use Doctrine\Common\Collections\Collection;
use Puzzle\OAuthServerBundle\Entity\User;

use Puzzle\OAuthServerBundle\Traits\ExprTrait;
use Knp\DoctrineBehaviors\Model\Timestampable\Timestampable;
use Knp\DoctrineBehaviors\Model\Blameable\Blameable;
use Knp\DoctrineBehaviors\Model\Sluggable\Sluggable;

use Puzzle\OAuthServerBundle\Traits\PrimaryKeyable;
use Puzzle\OAuthServerBundle\Traits\Describable;
use Puzzle\OAuthServerBundle\Traits\Nameable;
use Puzzle\OAuthServerBundle\Traits\Taggable;

/**
 * Folder
 *
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 * @ORM\Table(name="media_folder")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 * @JMS\ExclusionPolicy("all")
 * @JMS\XmlRoot("folder")
 * @Hateoas\Relation(
 * 		name = "self", 
 * 		href = @Hateoas\Route(
 * 			"get_media_folder", 
 * 			parameters = {"id" = "expr(object.getId())"},
 * 			absolute = true,
 * ))
 * @Hateoas\Relation(
 *     name = "parent",
 *     embedded = "expr(object.getParent())",
 *     exclusion = @Hateoas\Exclusion(excludeIf = "expr(object.getParent() === null)"),
 *     href = @Hateoas\Route(
 * 			"get_media_folder", 
 * 			parameters = {"id" = "expr(object.getParent().getId())"},
 * 			absolute = true,
 * ))
 * @Hateoas\Relation(
 *     name = "childs",
 *     embedded = "expr(object.getChilds())",
 *     exclusion = @Hateoas\Exclusion(excludeIf = "expr(object.getChilds() === null)")
 * ))
 * @Hateoas\Relation(
 * 		name = "files", 
 *      exclusion = @Hateoas\Exclusion(excludeIf = "expr(object.getFiles() === null)"),
 * 		href = @Hateoas\Route(
 * 			"get_media_files", 
 * 			parameters = {"id" = "=:~expr(object.stringify(',',object.getFiles()))"},
 * 			absolute = true,
 * ))
 * 
 */
class Folder
{
    use PrimaryKeyable,
        Timestampable,
        Blameable,
        Describable,
        Nameable,
        Sluggable,
        Taggable,
        ExprTrait;
    
    /**
     * @ORM\Column(name="path", type="string", length=255)
     * @var string
     * @JMS\Expose
  	 * @JMS\Type("string")
     */
    private $path;
    
    /**
     * @ORM\Column(name="slug", type="string", length=255)
     * @var string
     * @JMS\Expose
     * @JMS\Type("string")
     */
    protected $slug;
    
    /**
     * @ORM\Column(name="overwritable", type="boolean")
     * @var bool
     * @JMS\Expose
     * @JMS\Type("boolean")
     */
    private $overwritable;
    
    /**
     * @ORM\Column(name="allowed_extensions", type="array", nullable=true)
     * @var array
     * @JMS\Expose
  	 * @JMS\Type("array")
     */
    private $allowedExtensions;

    /**
     * @ORM\Column(name="files", type="array", nullable=true)
     * @var array
     * @JMS\Expose
  	 * @JMS\Type("array")
     */
    private $files;
    
    /**
     * @ORM\OneToMany(targetEntity="Folder", mappedBy="parent", cascade={"remove"})
     */
    private $childs;
    
    /**
     * @ORM\ManyToOne(targetEntity="Folder", inversedBy="childs")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    private $parent;
    
    public function __construct() {
        $this->childs = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getSluggableFields()
    {
        return [ 'name' ];
    }
    
    public function generateSlugValue($values)
    {
        return implode('-', $values);
    }
    
    public function setFiles($files) : self {
        foreach ($files as $file) {
            $this->addFile($file);
        }
        
    	return $this;
    }
    
    public function addFile($file) : self {
    	$this->files[] = $file;
    	$this->files = array_unique($this->files);
    	
    	return $this;
    }
    
    public function removeFile($file) : self {
    	$this->files = array_diff($this->files, [$file]);
    	return $this;
    }
    
    public function getFiles() :? array {
    	return $this->files;
    }

    public function setOverwritable($overwritable) : self {
        $this->overwritable = $overwritable;
        return $this;
    }

    public function isOverwritable() :? string {
        return $this->overwritable;
    }
    
    public function setParent(Folder $parent = null) : self {
        $this->parent = $parent;
        return $this;
    }
    
    public function getParent() :? self {
        return $this->parent;
    }

    public function addChild(Folder $child) : self {
        if ($this->childs === null || $this->childs->contains($child) === false ) {
            $this->childs->add($child);
        }
        
        return $this;
    }

    public function removeChild(Folder $child) : self {
        $this->childs->removeElement($child);
    }

    public function getChilds() :? Collection {
        return $this->childs;
    }
    
    public function setAllowedExtensions($allowedExtensions) : self {
        $this->allowedExtensions = $allowedExtensions;
        return $this;
    }

    public function getAllowedExtensions() :? array {
        return $this->allowedExtensions;
    }

    /**
    * @ORM\PrePersist
    * @ORM\PreUpdate
    */
    public function setPath(){
        $this->path = $this->parent !== null ? $this->parent->getPath().'/'.$this->name : File::getBasePath().'/'.$this->name;
    }

    public function getPath() :? string {
        return $this->path;
    }
    
    public function getAbsolutePath() {
        return File::getBaseDir().$this->path;
    }
    
    public function createDefault(User $user) {
        $this->name = $user->getUsername();
        $this->overwritable = false;
    }
    
    public function isDefault(User $user) {
        return $this->name === $user->getUsername() && $this->createdBy->getId() === $user->getId() && $this->overwritable === true;
    }
}

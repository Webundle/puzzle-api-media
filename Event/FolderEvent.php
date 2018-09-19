<?php 

namespace Puzzle\Api\MediaBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Puzzle\Api\MediaBundle\Entity\Folder;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 * 
 */
class FolderEvent extends Event
{
	/**
	 * @var Folder
	 */
	private $folder;
	
	/**
	 * @var array
	 */
	private $data;
	
	public function __construct(Folder $folder, array $data = null){
		$this->folder= $folder;
		$this->data = $data;
	}
	
	public function getFolder(){
		return $this->folder;
	}
	
	public function getData(){
	    return $this->data;
	}
}

?>
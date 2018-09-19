<?php 

namespace Puzzle\Api\MediaBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 * 
 */
class FileEvent extends Event
{
	/**
	 * @var array
	 */
	private $data;
	
	public function __construct(array $data){
		$this->data = $data;
	}
	
	public function getData(){
	    return $this->data;
	}
}

?>
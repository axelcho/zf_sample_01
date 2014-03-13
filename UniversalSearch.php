<?php
namespace Smu\Command;

use Admin\Filter;
use Smu\Command;
use Smu\Model;
use Smu\Table;

/**
 * UniversalSearch related commands
 */
class UniversalSearch extends Command {
	/**
	 * Do a lookup based on a universal query
	 *
	 * @return array
	 */
	public function search() {
	
		$result = array();
		$account = new Table\Accounts();
		$accountresult = $account->universalSearch($this->_parameters['term']);	
		
		if (count($accountresult) > 1){
			foreach ($accountresult as $A){			
				$result[] = $A;
			}
		}
		
		$title = new Table\Titles();
		$titleresult = $title->universalSearch($this->_parameters['term']);
		
		if (count($titleresult) > 1) {
			foreach ($titleresult as $T){
				$result[] = $T;
			}
		}
		
		$position = new Table\Positions();
		$positionresult = $position->universalSearch($this->_parameters['term']);
		
		if (count($positionresult) > 1){
			foreach ($positionresult as $P){
				$result[] = $P;
			}
		}
		return $result;		
	}	
}
?>
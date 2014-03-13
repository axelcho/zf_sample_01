<?php

namespace Smu\Table;

use Smu\Model;
use Smu\Table;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Update;

/**
 * Positions table
 */
class Positions extends Table {
	/**
	 * Grab by its ID - grab it from the 'all' cache
	 *
	 * @param int $id
	 * @return Model\Positions
	 */
	public function getOneByPositionID($id) {
		static $positions;
		if (is_null($positions)) {
			$positions = $this->getAll('Order ASC');
		}
		return isset($positions[$id]) ? $positions[$id] : $this->getModel();
	}

	/**
	 * Grab by its ID - grab it from the 'all' cache
	 *
	 * @param Model\PositionQualifiers $qualifier
	 */
	public function removeQualifier(Model\PositionQualifiers $qualifier) {
		$update = new Update($this->table);
		$update->set(array(
				'PositionQualifierID' => null
			))
			->where(array(
				'PositionQualifierID' => $qualifier->PositionQualifierID
			));
		$this->updateWith($update);
	}

	/**
	 * Search for positions like the string passed in
	 *
	 * @param string $term
	 * @param int $limit
	 * @return array
	 */
	public function ajaxSearch($term, $limit = 15) {
		$select = new Select($this->table);
		$select->where(new Predicate\Like('Title', "%{$term}%"));
		if (intval($limit) > 0) {
			$select->limit($limit);
		}

		$return = array();
		foreach ($this->selectWith($select) as $result) {
			/** @var Model\Positions $result */
			$return[] = array(
				'id' => $result->PositionID,
				'value' => $result->Title,
				'label' => $result->Title
			);
		}
		return $return;
	}
        
	/**
	 * Get the Position ID for the Title passed in
	 *
	 * @param string $title
	 * @return int
	 */
	public function getPositionIDByTitle($title){
		$position = $this->getOneByTitle($title);
		if ($position->loaded()) {
			return  $position->PositionID;
		} else {
			return false;
		}
	}

	/**
	 * Perform a universal search
	 *
	 * @param string $term
	 * @param int $limit
	 * @return array
	 */
	public function universalSearch($term, $limit=3) {
		// Prepare the query
		$select = new Select($this->table);
		$select->where(new Predicate\Like('Title', '%' . $term . '%'))
			->limit($limit)
			->order('Title ASC');

		// Prepare our response
		$results = array(array('id' => null,
							  'value' => null,
                              'label' => "-POSITIONS-",
							  'table' => null,
							  'link' => null
							  ));
		foreach ($this->selectWith($select) as $position) {
			
			$results[] = array(
				'id' => $position->PositionID,
				'value' => $position->Title,
				'label' => $position->Title,
				'table' => "Positions",
				'link' => null
			);
		}
		return $results;
	}	
	
}
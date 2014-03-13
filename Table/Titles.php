<?php

namespace Smu\Table;

use Admin\Filter;
use Admin\ViewModel\Ajax;
use Exception;
use Smu\Model;
use Smu\Pager;
use Smu\Table;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Update;

/**
 * Titles table
 */
class Titles extends Table {
	/**
	 * Linkify a title's name
	 *
	 * @param string $name
	 * @return string
	 * @throws Exception
	 */
	public function linkify($name) {
		$link = trim($name);
		$link = preg_replace('/[^a-zA-Z0-9& ]+/', '', $link);
		$link = preg_replace('/ +/', '-', $link);
		$check = $link = preg_replace('/--+/', '-', $link);
		for ($i = 2; $this->getOneByLink($link)->loaded(); ++$i) {
			$link = "{$check}-{$i}";
			if ($i > 10) {
				throw new Exception("Logical error in loop.");
			}
		}
		return $link;
	}

	/**
	 * Get our results based on the filter used
	 *
	 * @param Filter\Titles $filter
	 * @return Ajax\Titles
	 */
	public function getListBy(Filter\Titles $filter) {
		// First get the count
		$select = new Select('Titles');
		$select->columns(array(
			'Count' => new Expression('COUNT(Titles.TitleID)')
		), false);
		if (strlen($filter->searchTerm) > 0) {
			$select->where(new Predicate\Like('Titles.Name', '%' . $filter->searchTerm . '%'));
		}
		if ($filter->retrieveUnapprovedOnly) {
			$select->where(new Predicate\Expression('Titles.IsApproved = 0'));
		}

		// Prepare our return result
		$return = new Ajax\Titles();
		$return->pager = new Pager($filter->page, $this->arraySelectWith($select)->current()->Count, $filter->limit);

		// Limit result set
		$select->columns(array('*'))
			->order('Titles.NumberOfCredits DESC')
			->offset($return->pager->start-1)
			->limit($filter->limit);

		// Get all the results
		$return->results = $this->selectWith($select);
		return $return;
	}

	/**
	 * Search for titles like the string passed in
	 *
	 * @param string $term
	 * @param int $limit
	 * @param bool|null $approved
	 * @return array
	 */
	public function ajaxSearch($term, $limit = 15, $approved = null) {
		// Prepare the query
		$select = new Select($this->table);
		$select->where(new Predicate\Like('Titles.Name', '%' . $term . '%'));
		if (!is_null($approved)) {
			$select->where(new Predicate\Expression('Titles.IsApproved = ?', ($approved == true ? 1 : 0)));
		}
		$select->order('Titles.NumberOfCredits DESC');
		if (intval($limit) > 0) {
			$select->limit(intval($limit));
		}

		// Prepare our response
		$results = array();
		foreach ($this->selectWith($select) as $title) {
			/** @var Model\Titles $title */
			$results[] = array(
				'id' => $title->TitleID,
				'value' => $title->Name,
				'label' => $title->Name . ' (matches: ' . $title->NumberOfCredits . ')',
				'link' => $title->Link,
				'approved' => $title->IsApproved
			);
		}
		return $results;
	}

	/**
	 * Get an array of unique season numbers for a given title
	 *
	 * @param Model\Titles $title
	 * @return int[]
	 */
	public function getUniqueSeasonsFor(Model\Titles $title) {
		$select = new Select($this->table);
		$select->columns(array())
			->quantifier(Select::QUANTIFIER_DISTINCT)
			->join('Credits', 'Credits.TitleID = Titles.TitleID', array('Season'))
			->where(array(
				new Predicate\IsNotNull('Season'),
				new Predicate\Operator('Season', '!=', 0),
				new Predicate\Expression('Titles.TitleID = ?', $title->TitleID),
			));

		$results = array();
		foreach ($this->arraySelectWith($select) as $row) {
			$results[] = $row->Season;
		}
		sort($results);
		return $results;
	}

	/**
	 * Get an array of unique years for a given title
	 *
	 * @param Model\Titles $title
	 * @return int[]
	 */
	public function getUniqueYearsFor(Model\Titles $title) {
		$select = new Select($this->table);
		$select->columns(array())
			->quantifier(Select::QUANTIFIER_DISTINCT)
			->join('Credits', 'Credits.TitleID = Titles.TitleID', array('Year'))
			->where(array(
				new Predicate\IsNotNull('Year'),
				new Predicate\Operator('Year', '>', 0),
				new Predicate\Expression('Titles.TitleID = ?', $title->TitleID),
			));

		$results = array();
		foreach ($this->arraySelectWith($select) as $row) {
			$results[] = $row->Year;
		}
		sort($results);
		return $results;
	}

	/**
	 * Update the number of credits for a specific title
	 *
	 * @param Model\Titles $title
	 */
	public function updateCreditsFor(Model\Titles $title) {
		$update = new Update($this->table);
		$update->set(array(
				'NumberOfCredits' => new Expression('(SELECT COUNT(*) FROM Credits WHERE Credits.TitleID = Titles.TitleID)')
			))
			->where(array(
				'TitleID' => $title->TitleID
			));

		$this->updateWith($update);
	}

	/**
	 * Get all titles for a specific team
	 *
	 * @param Model\Teams $team
	 * @return Model\Titles[]
	 */
	public function getForTeam(Model\Teams $team) {
		$select = new Select($this->table);
		$select->join('TeamTitles', 'TeamTitles.TitleID = Titles.TitleID', array());
		$select->where(array(
			new Predicate\Expression('TeamTitles.TeamID = ?', $team->TeamID)
		));
		$select->order("Name ASC");

		$return = array();
		foreach ($this->selectWith($select) as $job) {
			$return[] = $job;
		}
		return $return;
	}

	/**
	 * Find titles with credits that have a production company name match
	 *
	 * @param Model\Teams $team
	 * @return Model\Titles[]
	 */
	public function getSuggestedForTeam(Model\Teams $team) {
		$select = new Select($this->table);
		$select->quantifier(Select::QUANTIFIER_DISTINCT);
		$select->join('Credits', 'Credits.TitleID = Titles.TitleID', array());
		$select->join('ProductionCompanies', 'Credits.ProductionCompanyID = ProductionCompanies.ProductionCompanyID', array());
		$select->join('TeamTitles', 'TeamTitles.TitleID = Titles.TitleID', array(), Select::JOIN_LEFT);
		$select->where(new Predicate\PredicateSet(array(
			new Predicate\Expression('TeamTitles.TeamID IS NULL'),
			new Predicate\Expression('TeamTitles.TeamID != ?', $team->TeamID)
		), Predicate\PredicateSet::COMBINED_BY_OR));
		$select->where(array(
			new Predicate\Expression('ProductionCompanies.Name = ?', $team->Name),
		));
		$select->group('Titles.TitleID');
		$select->having('COUNT(*) > 3');
		$select->order('Titles.Name ASC');

		return $this->selectWith($select);
	}

	/**
	 * Find titles with credits that have a title name match and are used at least a number of times
	 *
	 * @param string $search
	 * @return Model\Titles[]
	 */
	public function getSuggestedForTeamInput($search) {
		$select = new Select($this->table);
		$select->join('Credits', 'Credits.TitleID = Titles.TitleID', array());
		$select->group('Titles.TitleID');
		$select->having('COUNT(*) > 20');
		$select->where(array(
			new Predicate\Expression('Titles.Name LIKE ?', '%' . $search . '%'),
		));

		// Prepare our response
		$results = array();
		foreach ($this->selectWith($select) as $title) {
			/** @var Model\Titles $title */
			$results[] = array(
				'id' => $title->TitleID,
				'value' => $title->Name,
			);
		}
		return $results;
	}
	
	public function universalSearch($term, $limit=3) {
		// Prepare the query
		$select = new Select($this->table);
		$select->where(array(
			new Predicate\Like('Name', '%' . $term . '%'),
			new Predicate\Expression('NumberOfCredits > 1'),
			new Predicate\Expression('IsApproved = 1')
			))
			->limit($limit)
			->order('NumberOfCredits DESC');

		// Prepare our response
		$results = array(array('id' => null,
							  'value' => null,
                              'label' => "-TITLES-",
							  'table' => null,
							  'link' => null
							  ));
		foreach ($this->selectWith($select) as $title) {
			
			$results[] = array(
				'id' => $title->TitleID,
				'value' => $title->Name,
				'label' => $title->Name . ' (Matches: ' . $title->NumberOfCredits . ')',
				'table' => "Titles",
				'link' => $title->getLink()
			);
		}
		return $results;
	}
	
}
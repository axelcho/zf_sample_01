<?php

namespace Smu\Table;

use Admin\Filter;
use Admin\ViewModel\Ajax;
use Smu\Model;
use Smu\Pager;
use Smu\Table;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate;
use stdClass;

/**
 * Accounts table
 */
class Accounts extends Table {
	/**
	 * Get all subscriptions that are ready to be charged :)
	 *
	 * @return Model\Accounts[]
	 */
	public function getReadyToCharge() {
		$select = new Select($this->table);
		$select->join('Subscriptions', 'Accounts.SubscriptionID = Subscriptions.SubscriptionID', array())
			->where(array(
				new Predicate\Expression('Accounts.IsActive = 1'),
				new Predicate\Expression('Subscriptions.SubscriptionProviderID = ?', Model\SubscriptionProviders::AUTHORIZE_NET),
				new Predicate\Expression('Subscriptions.IsFake = 0'),
				new Predicate\Literal('(Subscriptions.LastRun IS NULL OR Subscriptions.LastRun != CURRENT_DATE())'),
				new Predicate\PredicateSet(array(
					new Predicate\Literal('(Subscriptions.ForceNextPayment IS NOT NULL AND Subscriptions.ForceNextPayment <= CURRENT_DATE())'),
					new Predicate\Literal('(Subscriptions.ForceNextPayment IS NULL AND Subscriptions.LastPayment IS NULL)'),
					new Predicate\Literal('(
						Subscriptions.ForceNextPayment IS NULL AND (
							(Subscriptions.IsMonthly = 1 AND Subscriptions.LastPayment <= CURRENT_DATE() - INTERVAL 1 MONTH)
							OR (Subscriptions.IsMonthly = 0 AND Subscriptions.LastPayment <= CURRENT_DATE() - INTERVAL 12 MONTH)
						)
					)')
				), Predicate\PredicateSet::COMBINED_BY_OR)
			));
		return $this->selectWith($select);
	}

	/**
	 * Get all premium accounts that are ready to be cancelled
	 *
	 * @return Model\Accounts[]
	 */
	public function getToCancelPremium() {
		$select = new Select($this->table);
		$select->join('Subscriptions', 'Accounts.SubscriptionID = Subscriptions.SubscriptionID', array());
		$select->where(array(
			new Predicate\Expression('Subscriptions.RequestedCancelDate <= CURRENT_DATE()'),
			new Predicate\Expression('Subscriptions.IsCanceled = 0')
		));
		return $this->selectWith($select);
	}

	/**
	 * Get user statistics in stdClass format and send them back (monthly subs, yearly subs, unpaid, total)
	 *
	 * return stdClass
	 */
	public function getAccountStatistics() {
		$stats = new stdClass();

		$select = new Select($this->table);
		$select->columns(array(
			'Count' => new Expression("COUNT(Accounts.AccountID)")
		), false);
		$stats->total = $this->arraySelectWith($select)->current()->Count;

		$select = new Select($this->table);
		$select->join('Subscriptions', 'Subscriptions.SubscriptionID = Accounts.SubscriptionID')
			->columns(array(
				'Count' => new Expression("COUNT(Accounts.AccountID)")
			), false)
			->where(array(
				'Subscriptions.IsMonthly' => 1
			));
		$stats->monthly = $this->arraySelectWith($select)->current()->Count;

		$select = new Select($this->table);
		$select->join('Subscriptions', 'Subscriptions.SubscriptionID = Accounts.SubscriptionID')
			->columns(array(
				'Count' => new Expression("COUNT(Accounts.AccountID)")
			), false)
			->where(array(
				'Subscriptions.IsMonthly' => 0
			));
		$stats->yearly = $this->arraySelectWith($select)->current()->Count;

		return $stats;
	}

	/**
	 * Get our results based on the filter used
	 *
	 * @param Filter\Accounts $filter
	 * @return Ajax\Accounts
	 */
	public function getListBy(Filter\Accounts $filter) {
		$select = new Select($this->table);
		$select->columns(array(
			'Count' => new Expression('COUNT(AccountID)')
		));
		if (strlen($filter->searchTerm) > 0) {
			$select->where(new Predicate\Like($filter->searchType, '%' . $filter->searchTerm . '%'));
		}
		if ($filter->retrieveValidatedOnly) {
			$select->where(new Predicate\Expression('IsValidated = 1'));
		}
		if ($filter->retrieveActiveOnly) {
			$select->where(new Predicate\Expression('IsActive = 1'));
		}
		if ($filter->retrieveInactiveOnly) {
			$select->where(new Predicate\Expression('IsActive = 0'));
		}

		// Prepare our return result
		$return = new Ajax\Accounts();
		$return->pager = new Pager($filter->page, $this->arraySelectWith($select)->current()->Count, $filter->limit);

		// Add a few more clauses
		$select->columns(array('*'))
			->order("{$filter->sort} {$filter->sortOrder}")
			->offset($return->pager->start-1)
			->limit($filter->limit);

		$return->results = $this->selectWith($select);
		return $return;
	}

	/**
	 * Get all accounts with credits in the database matching the title, season and year specified
	 *
	 * @param int $titleId
	 * @param int $season
	 * @param int $year
	 * @return Accounts[]
	 */
	public function getAccountsMatchingTitle($titleId, $season, $year) {
		// Get accounts matching title/season/year
		$select = new Select($this->table);
		$select->quantifier(Select::QUANTIFIER_DISTINCT)
			->join('Credits', 'Accounts.AccountID = Credits.AccountID', array())
			->where(new Predicate\Expression('TitleID = ?', $titleId));

		// Check if we have a special season
		if (strlen($season) > 0 && $season != 0) {
			$select->where(new Predicate\Expression('Season = ?', $season));
		} else {
			$select->where(new Predicate\IsNull('Season'));
		}

		// Check if we have a special season
		if ($year > 0) {
			$select->where(new Predicate\Expression('Year = ?', $year));
		} else {
			$select->where(new Predicate\IsNull('Year'));
		}

		return $this->selectWith($select);
	}

	/**
	 * Gets the network status between a 'from' account and a 'to' one
	 *
	 * @param int $from
	 * @param int $to
	 * @return int
	 */
	public function getNetworkStatusBetween($from, $to) {
		// Make sure they're both accounts
		if (!$from || !$to) {
			return Model\NetworkConnections::STATUS_NOT_IN_NETWORK;
		}

		// Check for a connection
		$connections = new NetworkConnections();
		$connection = $connections->getConnection($from, $to);
		if ($connection->loaded()) {
			return Model\NetworkConnections::STATUS_IN_NETWORK;
		}

		// Check for an invitation
		$invitations = new NetworkConnectionInvitations();
		$invitation = $invitations->getInvitation($from, $to);
		if ($invitation->loaded()) {
			return Model\NetworkConnections::STATUS_INVITED;
		}

		return Model\NetworkConnections::STATUS_NOT_IN_NETWORK;
	}

	/**
	 * Search for last names like the string passed in
	 *
	 * @param string $term
	 * @param Model\Accounts|null $from
	 * @return array
	 */
	public function searchByLastName($term, $from) {
		// Get accounts
		$query = "
			SELECT
			 	a.AccountID,
			 	a.FirstName,
			 	a.LastName,
			 	a.Link,
			 	a.LocationCity,
			 	a.LocationArea,
			 	a.LocationCountry,
			 	c.Name,
			 	(SELECT COUNT(*) FROM NetworkConnections WHERE FromAccountID = '{$from->AccountID}' AND ToAccountID = a.AccountID) AS Connected
			FROM
				Accounts a
				LEFT JOIN Countries c ON (a.LocationCountry = c.Country)
			WHERE
				a.LastName LIKE " . $this->getAdapter()->getPlatform()->quoteValue('%' . $term . '%') . "
				AND a.IsActive = 1
			ORDER BY
				a.LastName ASC,
				a.FirstName ASC
		";

		// Get results
		$results = array();
		foreach ($this->getAdapter()->query($query, Adapter::QUERY_MODE_EXECUTE) as $row) {
			// Get the location
			$location = $row->LocationArea . " (" . $row->LocationCity . ")";
			if (strlen($row->LocationCountry) > 0 && $row->LocationCountry != "US" && strlen($row->Name) > 0) {
				$location = $row->Name . " (" . $row->LocationCity . ")";
			}

			// Compile the results
			$results[] = array(
				'id' => $row->AccountID,
				'name' => $row->LastName . ", " . $row->FirstName,
				'first' => $row->FirstName,
				'profile' => $row->Link,
				'location' => $location,
				'inNetwork' => $row->Connected
			);
		}
		return $results;
	}

	/**
	 * Search for email suffixes like the string passed in
	 *
	 * @param string $suffix
	 * @return array
	 */
	public function searchEmailSuffix($suffix) {
		$select = new Select($this->table);
		$select->where(new Predicate\Expression("Email LIKE ?", '%@' . $suffix));
		$select->order(array(
			'LastName ASC',
			'FirstName ASC'
		));

		$results = array();
		foreach ($this->selectWith($select) as $account) {
			/** @var Model\Accounts $account */
			$results[] = array(
				'name' => $account->LastName . ', ' . $account->FirstName,
				'email' => $account->Email,
				'link' => $account->getLink()
			);
		}

		return $results;
	}

	/**
	 * Search for accounts in a given location with a certain amount of experience
	 *
	 * @param int $positionId
	 * @param float $latitude
	 * @param float $longitude
	 * @param int $radius
	 * @return array
	 */
	public function searchByExperience($positionId, $latitude, $longitude, $radius) {
		// Get accounts
		$select = new Select($this->table);
		$select->columns(array(
				'AccountID',
				'FirstName',
				'LastName',
				'Link',
			))
			->join('AccountPositionSummary', 'Accounts.AccountID = AccountPositionSummary.AccountID', array('Months'))
			->join('Subscriptions', 'Accounts.SubscriptionID = Subscriptions.SubscriptionID', array('SubscriptionID'), Select::JOIN_LEFT)
			->where(array(
				new Predicate\Expression('Months > 0'),
				new Predicate\Expression('AccountPositionSummary.PositionID = ?', $positionId),
				new Predicate\Expression('IsConverted = 1'),
				new Predicate\Expression('IsActive = 1')
			));

		if ($latitude && $longitude && $radius) {
			$select->columns(array(
					'AccountID',
					'FirstName',
					'LastName',
					'Link',
					'Miles' => new Expression('CalculateDistance(Latitude, Longitude, ' . $this->getAdapter()->getPlatform()->quoteValue($latitude) . ', ' . $this->getAdapter()->getPlatform()->quoteValue($longitude) . ')')
				))
				->having('Miles <= ' . intval($radius));
		}

		$results = array();
		foreach ($this->arraySelectWith($select) as $data) {
			$results[] = array(
				'id' => $data['AccountID'],
				'name' => $data['LastName'] . ", " . $data['FirstName'],
				'profile' => $data['Link'],
				'months' => $data['Months'],
				'miles' => isset($data['Miles']) ? $data['Miles'] : NULL,
				'premium' => $data['SubscriptionID'] > 0,
			);
		}

		// Sort our response
		usort($results, function($result1, $result2) {
			// First sort by premium or not
			if ($result1['premium'] == $result2['premium']) {
				if ($result1['months'] == $result2['months']) {
					return 0;
				}

				return $result1['months'] > $result2['months'] ? -1 : 1;
			}

			return $result1['premium'] ? -1 : 1;
		});

		return $results;
	}
	
	public function universalSearch($term, $limit=3) {
	
		$select = new Select($this->table);
		$select->where(array(
				new Predicate\Expression("CONCAT(FirstName, ' ', LastName) LIKE ?", '%'. $term .'%'),
				new Predicate\Expression('IsActive = 1'),
				new Predicate\Expression('IsConverted = 1')
				))
				->limit($limit);		

		// Prepare our response
		$results = array(array('id' => null,
							  'value' => null,
                              'label' => "-NAMES-",
							  'table' => null,
							  'link' => null));
		foreach ($this->selectWith($select) as $account) {
			
			$results[] = array(
				'id' => $account->AccountID,
				'link' => "/".$account->getLink(),				
				'value' => $account->FirstName." ".$account->LastName,
				'label' => $account->FirstName." ".$account->LastName,
				'table' => "Accounts"
			);
		}
		return $results;
	}

}
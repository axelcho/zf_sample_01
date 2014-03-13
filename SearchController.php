<?php

namespace Smu\Controller;

use Application\Mvc\Controller;
use Smu\Model;
use Smu\Table;
use Smu\ViewModel\Search;
use Zend\View\Model\ViewModel;

/**
 * All the search routes for the site
 */
class SearchController extends Controller {
	/**
	 * Show search options
	 */
	public function indexAction() {
		return new ViewModel();
	}

	/**
	 * Search by title
	 */
	public function showsAction() {
		// Make sure they validated their email
		if (!$this->getAccount()->IsValidated) {
			$this->flash('error', 'Your email address has not been validated.  You may not access this page until you have validated your email address.');
			return $this->redirectToReferer();
		}

		// Make sure they're premium
		if (!$this->getAccount()->isPremium()) {
			return $this->redirectToUrl('/search/premium');
		}

		return new ViewModel();
	}

	/**
	 * Search by Last name
	 */
	public function lastNameAction() {
		// Make sure they validated their email
		if (!$this->getAccount()->IsValidated) {
			$this->flash('error', 'Your email address has not been validated.  You may not access this page until you have validated your email address.');
			return $this->redirectToReferer();
		}

		$model = new Search\LastName();
		$model->search = $this->query('search');
		return new ViewModel(array(
			'model' => $model
		));
	}

	/**
	 * Search for a user by experience
	 */
	public function experienceAction() {
		// Make sure they validated their email
		if (!$this->getAccount()->IsValidated) {
			$this->flash('error', 'Your email address has not been validated.  You may not access this page until you have validated your email address.');
			return $this->redirectToReferer();
		}

		// Make sure they're premium
		if (!$this->getAccount()->isPremium()) {
			return $this->redirectToUrl('/search/premium');
		}

		$model = new Search\Experience();
		$model->universalId = $this->post('universalPositionID');
		$model->universalPosition = $this->post('universalPosition');		
		$model->positions = $this->getTable('Positions')->getAll('Order ASC');
		return new ViewModel(array(
			'model' => $model
		));
	}

	/**
	 * Show benefits of becoming premium
	 */
	public function premiumAction() {
		return new ViewModel();
	}

	/**
	 * Universal search
	 */
	public function universalAction() {

		// Make sure they validated their email
		if (!$this->getAccount()->IsValidated) {
			$this->flash('error', 'Your email address has not been validated.  You may not access this page until you have validated your email address.');
			return $this->redirectToReferer();
		}
		
		$model = new Search\Universal();				
		$search = $this->post('universalSearch');
				
		$result = array();
		$account = new Table\Accounts();
		$accountresult = $account->universalSearch($search, 10);	
		
		if (count($accountresult) > 1){
			foreach ($accountresult as $A){			
				$result[] = $A;
			}
		}
		
		$title = new Table\Titles();
		$titleresult = $title->universalSearch($search, 10);
		
		if (count($titleresult) > 1) {
			foreach ($titleresult as $T){
				$result[] = $T;
			}
		}
		
		$position = new Table\Positions();
		$positionresult = $position->universalSearch($search, 10);
		
		if (count($positionresult) > 1){
			foreach ($positionresult as $P){
				$result[] = $P;
			}
		}	
	
		$model->search = $search; 
		$model->result = $result; 
	
		return new ViewModel(array(
			'model' => $model,
		));
	}
	
}

<?php

namespace ST;

use User;
use WikiPage;

/** @todo: rename TaskDiff something similar */
class Assignees {

	private $taskAssignees;
	private $taskStatus;

	/**
	 * Previously this was SemanticTasksMailer::findOldValues
	 *
	 * @param WikiPage $article
	 * @return bool
	 */
	public function saveAssignees( WikiPage &$article ) {
		$this->taskAssignees = $this->getCurrentAssignees( $article );
		$this->taskStatus = $this->getCurrentStatus( $article );
		return true;
	}

	public function getSavedStatus() {
		return $this->taskStatus;
	}

	public function getSavedAssignees() {
		return $this->taskAssignees;
	}

	/**
	 * @param WikiPage $article
	 * @return array
	 */
	public function getCurrentAssignees( WikiPage &$article ) {
		global $stgPropertyAssignedTo;
		$titleText = $article->getTitle()->getFullText();
		return $this->getAssignees( $stgPropertyAssignedTo, $titleText );
	}

	public function getCurrentCarbonCopy( WikiPage &$article ) {
		global $stgPropertyCarbonCopy;
		$titleText = $article->getTitle()->getFullText();
		return $this::getAssignees( $stgPropertyCarbonCopy, $titleText );
	}

	/**
	 * @param WikiPage $article
	 * @return string
	 */
	public function getCurrentStatus( WikiPage &$article ) {
		global $stgPropertyStatus;
		$titleText = $article->getTitle()->getFullText();
		$status = $this->getStatus( $stgPropertyStatus, $titleText );
		$statusString = '';
		if ( count( $status ) > 0 ) {
			$statusString = $status[0];
		}
		return $statusString;
	}

	/**
	 * @param WikiPage $article
	 * @return array
	 */
	public function getNewAssignees( WikiPage &$article ) {
		return array_diff( $this->getCurrentAssignees( $article ), $this->taskAssignees );
	}

	/**
	 * @param WikiPage $article
	 * @return array
	 */
	public function getRemovedAssignees( WikiPage &$article ) {
		return array_diff( $this->taskAssignees, $this->getCurrentAssignees( $article ) );
	}

	/**
	 * Returns an array of assignees based on $query_word
	 *
	 * @param WikiPage $article
	 * @return array
	 */
	public function getGroupAssignees( WikiPage &$article ) {
		global $stgPropertyAssignedToGroup;
		global $stgPropertyHasAssignee;

		$query_word = $stgPropertyAssignedToGroup;
		$title_text = $article->getTitle()->getFullText();

		// Array of assignees to return
		$assignee_arr = array();

		// get the result of the query "[[$title]][[$query_word::+]]"
		$properties_to_display = array( $query_word );
		$results = Query::getQueryResults( "[[$title_text]][[$query_word::+]]", $properties_to_display,
			false );

		$group_assignees = null;
		// In theory, there is only one row
		while ( $row = $results->getNext() ) {
			$group_assignees = $row[0];
		}

		// If not any row, do nothing
		if ( !empty( $group_assignees ) ) {
			while ( $group_assignee = $group_assignees->getNextObject() ) {
				$group_assignee = $group_assignee->getTitle();
				$group_name = $group_assignee->getText();
				$query_word = $stgPropertyHasAssignee;
				$properties_to_display = array( $query_word );
				$results = Query::getQueryResults( "[[$group_name]][[$query_word::+]]", $properties_to_display,
					false );

				$task_assignees = null;
				// In theory, there is only one row
				while ( $row = $results->getNext() ) {
					$task_assignees = $row[0];
				}

				if ( !empty( $task_assignees ) ) {
					while ( $task_assignee = $task_assignees->getNextObject() ) {
						$assignee_name = $task_assignee->getTitle();
						$assignee_name = $assignee_name->getText();
						/** @todo Create User object */
						$assignee_name = explode( ":", $assignee_name );
						$assignee_name = $assignee_name[0];

						array_push( $assignee_arr, $assignee_name );
					}
				}
			}
		}

		return $assignee_arr;
	}

	/**
	 * Get the email addresses of all the assignees
	 *
	 * @param array $assignees
	 * @return array
	 */
	public function getAssigneeAddresses( array $assignees ) {
		$assignee_arr = array();
		foreach ( $assignees as $assignee_name ) {
			$assignee = User::newFromName( $assignee_name );
			// if assignee is the current user, do nothing
			# if ( $assignee->getID() != $user->getID() ) {
			$assignee_mail = new \MailAddress( $assignee->getEmail(), $assignee_name );
			array_push( $assignee_arr, $assignee_mail );
			# }
		}

		return $assignee_arr;
	}

	/**
	 * Returns an array of properties based on $query_word
	 *
	 * @param string $query_word The property that designate the users to notify.
	 * @param string $title_text
	 * @return array
	 */
	private function getAssignees( $query_word, $title_text ) {
		// Array of assignees to return
		$assignee_arr = array();

		// get the result of the query "[[$title]][[$query_word::+]]"
		$properties_to_display = array( $query_word );
		$results = Query::getQueryResults( "[[$title_text]][[$query_word::+]]", $properties_to_display,
			false );

		$task_assignees = null;
		// In theory, there is only one row
		while ( $row = $results->getNext() ) {
			$task_assignees = $row[0];
		}

		// If not any row, do nothing
		if ( !empty( $task_assignees ) ) {
			// since title is not displayed, 'Assigned to' value will be first value
			$assignee_name = $task_assignees->getNextText( SMW_OUTPUT_WIKI );
			/** @todo Create User object */
			$assignee_name = explode( ":", $assignee_name );

			if ( !isset( $assignee_name[1] ) ) {
				return array();
			}

			$assignee_name = $assignee_name[1];

			array_push( $assignee_arr, $assignee_name );
		}

		return $assignee_arr;
	}

	/**
	 * Returns an array of properties based on $query_word
	 *
	 * @param string $query_word The property that designate the users to notify.
	 * @param string $title_text
	 * @return array
	 */
	private function getStatus( $query_word, $title_text ) {
		// Array of assignees to return
		$assignee_arr = array();

		// get the result of the query "[[$title]][[$query_word::+]]"
		$properties_to_display = array();
		$properties_to_display[0] = $query_word;
		$results = Query::getQueryResults( "[[$title_text]][[$query_word::+]]", $properties_to_display,
			false );

		// In theory, there is only one row
		while ( $row = $results->getNext() ) {
			$task_assignees = $row[0];
		}

		// If not any row, do nothing
		if ( !empty( $task_assignees ) ) {
			while ( $task_assignee = $task_assignees->getNextObject() ) {
				$assignee_name = $task_assignee->getWikiValue();
				array_push( $assignee_arr, $assignee_name );
			}
		}

		return $assignee_arr;
	}
}


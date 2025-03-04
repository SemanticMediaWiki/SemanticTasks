<?php

namespace ST;

use ParserOutput;
use SMW\DIWikiPage;
use SMW\Services\ServicesFactory as ApplicationFactory;
use SMWDataItem;
use User;
use WikiPage;

/** @todo: rename TaskDiff something similar */
class Assignees {

	private $taskAssignees;
	private $taskStatus;

	/**
	 * Previously this was SemanticTasksMailer::findOldValues
	 *
	 * @param WikiPage &$article
	 * @return bool
	 */
	public function saveAssignees( WikiPage &$article ) {
		$this->taskAssignees = $this->getCurrentAssignees( $article, null );
		$this->taskStatus = $this->getCurrentStatus( $article, null );
		return true;
	}

	public function saveAssigneesMultiContentSave( \MediaWiki\Revision\RenderedRevision $renderedRevision, \MediaWiki\User\UserIdentity $user, \CommentStoreComment $summary, $flags, \Status $hookStatus ) {
		if ( method_exists( RevisionRecord::class, 'getPage' ) ) {
			$article = $revision->getPage();

		} else {
			$revision = $renderedRevision->getRevision();
			$title = \Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
			$article = WikiPage::factory( $title );
		}

		$this->taskAssignees = $this->getCurrentAssignees( $article, null );
		$this->taskStatus = $this->getCurrentStatus( $article, null );
		return true;
	}

	public function getSavedStatus() {
		return $this->taskStatus;
	}

	public function getSavedAssignees() {
		return $this->taskAssignees;
	}

	/**
	 * @param WikiPage &$article
	 * @param $revision
	 * @return array
	 */
	public function getCurrentAssignees( WikiPage &$article, $revision ) {
		global $stgPropertyAssignedTo;
		return $this->getProperties( $stgPropertyAssignedTo, $article, $revision );
	}

	public function getCurrentCarbonCopy( WikiPage &$article, $revision ) {
		global $stgPropertyCarbonCopy;
		return $this->getProperties( $stgPropertyCarbonCopy, $article, $revision );
	}

	/**
	 * @param WikiPage &$article
	 * @param $revision
	 * @return string
	 */
	public function getCurrentStatus( WikiPage &$article, $revision ) {
		global $stgPropertyStatus;
		$status = $this->getProperties( $stgPropertyStatus, $article, $revision );

		if ( count( $status ) > 0 ) {
			$status = $status[0];

			// status must be type text
			if ( $status instanceof \SMWDIBlob ) {
				return $status->getString();
			}
		}

		return null;
	}

	/**
	 * @param WikiPage &$article
	 * @param $revision
	 * @return array
	 */
	public function getNewAssignees( WikiPage &$article, $revision ) {
		return array_diff( $this->getCurrentAssignees( $article, $revision ), $this->taskAssignees );
	}

	/**
	 * @param WikiPage &$article
	 * @param $revision
	 * @return array
	 */
	public function getRemovedAssignees( WikiPage &$article, $revision ) {
		return array_diff( $this->taskAssignees, $this->getCurrentAssignees( $article, $revision ) );
	}

	/**
	 * Returns an array of assignees based on $query_word
	 *
	 * @param WikiPage &$article
	 * @return array
	 */
	public function getGroupAssignees( WikiPage &$article ) {
		global $stgPropertyAssignedToGroup;
		global $stgPropertyHasAssignee;

		$query_word = $stgPropertyAssignedToGroup;
		$title_text = $article->getTitle()->getFullText();

		// Array of assignees to return
		$assignee_arr = [];

		// get the result of the query "[[$title]][[$query_word::+]]"
		$properties_to_display = [ $query_word ];
		$results = Query::getQueryResults( "[[$title_text]][[$query_word::+]]", $properties_to_display,
			false );

		$group_assignees = null;
		// In theory, there is only one row
		while ( $row = $results->getNext() ) {
			$group_assignees = $row[0];
		}

		// If not any row, do nothing
		if ( !empty( $group_assignees ) ) {
			while ( $group_assignee = $group_assignees->getNextDataItem() ) {
				$group_assignee = $group_assignee->getTitle();
				$group_name = $group_assignee->getText();
				$query_word = $stgPropertyHasAssignee;
				$properties_to_display = [ $query_word ];
				$results = Query::getQueryResults( "[[$group_name]][[$query_word::+]]", $properties_to_display,
					false );

				$task_assignees = null;
				// In theory, there is only one row
				while ( $row = $results->getNext() ) {
					$task_assignees = $row[0];
				}

				if ( !empty( $task_assignees ) ) {
					while ( $task_assignee = $task_assignees->getNextDataItem() ) {
						$assignee = $task_assignee->getTitle();
						if ( $assignee ) {
							array_push( $assignee_arr, $assignee->getText() );
						}
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
	public static function getAssigneeAddresses( array $assignees ) {
		$assignee_arr = array_unique( $assignees );
		$ret = [];
		foreach ( $assignees as $assignee_name ) {
			$assignee = User::newFromName( $assignee_name );
			// if assignee is the current user, do nothing
			# if ( $assignee->getID() != $user->getID() ) {
			if ( !$assignee ) {
				continue;
			}
			$assignee_mail = new \MailAddress( $assignee->getEmail(), $assignee_name );
			array_push( $ret, $assignee_mail );
			# }
		}

		return $ret;
	}

	/**
	 * Returns an array of properties based on $query_word
	 *
	 * @param string $propertyString The property that designate the users to notify.
	 * @param WikiPage $article
	 * @param $revision
	 * @return array
	 */
	private function getProperties( $propertyString, $article, $revision ) {
		$smwFactory = ApplicationFactory::getInstance();
		$mwCollaboratorFactory = $smwFactory->newMwCollaboratorFactory();

		// untested in v. 3.0.0
		if ( version_compare( SMW_VERSION, '3.1', '<' ) ) {
			$editInfo = $mwCollaboratorFactory->newEditInfoProvider(
				$article,
				$revision,
				null
			);

		// $revision must be null when called from hook
		// MultiContentSave, to get the unsaved (not planned)
		// revision
		} else {
			if ( version_compare( SMW_VERSION, '4.0', '<' )
				&& ( $revision instanceof \MediaWiki\Revision\RevisionStoreRecord ) ) {
				// *** get legacyRevision
				$revision = new \Revision( $revision );
			}
			$editInfo = $mwCollaboratorFactory->newEditInfo(
				$article,
				$revision,
				null
			);
		}
		$editInfo->fetchEditInfo();
		$parserOutput = $editInfo->getOutput();

		if ( !$parserOutput instanceof ParserOutput ) {
			return [];
		}

		$propertyStringUnderscores = str_replace( ' ', '_', $propertyString );
		$property = new \SMW\DIProperty( $propertyStringUnderscores, false );

		/** @var $semanticData \SMW\SemanticData */
		$semanticData = $parserOutput->getExtensionData( 'smwdata' );
		if ( $semanticData === null ) {
			return [];
		}
		$propValues = $semanticData->getPropertyValues( $property );
		$valueList = array_map( static function ( SMWDataItem $propVal ) {
			if ( $propVal instanceof DIWikiPage ) {
				return $propVal->getTitle()->getText();
			}
			return $propVal;
		}, $propValues );

		return $valueList;
	}
}

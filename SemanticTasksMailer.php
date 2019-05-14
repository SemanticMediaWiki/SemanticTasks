<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'Not a valid entry point';
	exit( 1 );
}

if ( !defined( 'SMW_VERSION' ) ) {
	echo 'This extension requires Semantic MediaWiki to be installed.';
	exit( 1 );
}

use SMW\DataValueFactory;

// constants for message type
define( 'NEWTASK', 0 );
define( 'UPDATE', 1 );
define( 'ASSIGNED', 2 );
define( 'CLOSED', 3 );
define( 'UNASSIGNED', 4 );

/**
 * This class handles the creation and sending of notification emails.
 */
class SemanticTasksMailer {

	private static $task_assignees;
	private static $task_status;

	/**
	 * Store previous values from the article being saved
	 *
	 * @param WikiPage $article
	 * @param User $user
	 * @return boolean
	 */
	public static function findOldValues( WikiPage &$article, User &$user ) {
		$title = $article->getTitle();
		$title_text = $title->getFullText();

		$assignees = self::getAssignees( 'Assigned to', $title_text, $user );
		$status = self::getStatus( 'Status', $title_text, $user );

		self::printDebug( "Old assignees: ", $assignees );
		self::printDebug( "Old status: ", $status );

		self::$task_assignees = $assignees;

		if ( count( $status ) > 0 ) {
			self::$task_status = $status[0];
		} else {
			self::$task_status = "";
		}

		return true;
	}

	/**
	 * Mails the assignees when the task is modified
	 *
	 * @param WikiPage $article
	 * @param User $current_user
	 * @param string $text
	 * @param string $summary Unused
	 * @param string $minoredit
	 * @param null $watchthis Unused
	 * @param null $sectionanchor Unused
	 * @param $flags
	 * @return boolean
	 */
	public static function mailAssigneesUpdatedTask( WikiPage $article, User $current_user, $text,
		$summary, $minoredit, $watchthis, $sectionanchor, $flags ) {
		if ( !$minoredit ) {
			if ( ( $flags & EDIT_NEW ) && !$article->getTitle()->isTalkPage() ) {
				$status = NEWTASK;
			} else {
				$status = UPDATE;
			}
			self::mailAssignees( $article, $text, $current_user, $status );
		}
		return true;
	}

	/**
	 *
	 * @global boolean $wgSemanticTasksNotifyIfUnassigned
	 * @param WikiPage $article
	 * @param Content $content
	 * @param User $user
	 * @param integer $status
	 * @return boolean
	 */
	static function mailAssignees( WikiPage $article, Content $content, User $user, $status ) {
		self::printDebug( "Saved assignees:", self::$task_assignees );
		self::printDebug( "Saved task status: " . self::$task_status );

		$text = ContentHandler::getContentText( $content );
		$title = $article->getTitle();
		$title_text = $title->getPrefixedText();
		self::printDebug( "Title text: $title_text" );

		$current_assignees = self::getAssignees( 'Assigned to', $title_text, $user );

		self::printDebug( "Previous assignees: ", self::$task_assignees );
		self::printDebug( "New assignees: ", $current_assignees );

		$assignees_to_task = array_diff( $current_assignees, self::$task_assignees );

		// Notify those unassigned from this task
		global $wgSemanticTasksNotifyIfUnassigned;
		if ( $wgSemanticTasksNotifyIfUnassigned ) {
			$unassignees_from_task = array_diff( self::$task_assignees, $current_assignees );
			$unassignees_from_task = self::getAssigneeAddresses( $unassignees_from_task );
			self::mailNotification( $unassignees_from_task, $text, $title, $user, UNASSIGNED );
		}

		self::printDebug( "Assignees to task: ", $assignees_to_task );

		// Send notification of an assigned task to assignees
		// Treat task as new
		$assignees_to_task = self::getAssigneeAddresses( $assignees_to_task );
		self::mailNotification( $assignees_to_task, $text, $title, $user, ASSIGNED );

		// Only send group notifications on new tasks
		if ( $status == NEWTASK ) {
			$groups = self::getGroupAssignees( 'Assigned to group', $title_text, $user );
		} else {
			$groups = array();
		}

		$copies = self::getAssignees( 'Carbon copy', $title_text, $user );

		$current_task_status = self::getStatus( 'Status', $title_text, $user );
		self::printDebug( "New status: ", $current_task_status );
		if ( count( $current_task_status ) > 0 ) {
			$current_task_status = $current_task_status[0];
			if ( $current_task_status == "Closed" && self::$task_status != "Closed" ) {
				$close_mailto = self::getAssigneeAddresses( $copies );
				self::mailNotification( $close_mailto, $text, $title, $user, CLOSED );
			}
		}

		$mailto = array_merge( $current_assignees, $copies, $groups );
		$mailto = array_unique( $mailto );
		$mailto = self::getAssigneeAddresses( $mailto );

		// Send notifications to assignees, ccs, and groups
		self::mailNotification( $mailto, $text, $title, $user, $status );

		return true;
	}

	/**
	 * Returns an array of properties based on $query_word
	 *
	 * @param string $query_word The property that designate the users to notify.
	 * @param string $title_text
	 * @param $user Unused
	 * @return array
	 */
	static function getAssignees( $query_word, $title_text, $user ) {
		// Array of assignees to return
		$assignee_arr = array();

		// get the result of the query "[[$title]][[$query_word::+]]"
		$properties_to_display = array( $query_word );
		$results = self::getQueryResults( "[[$title_text]][[$query_word::+]]", $properties_to_display,
				false );

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
	 * @param $user Unused
	 * @return array
	 */
	static function getStatus( $query_word, $title_text, $user ) {
		// Array of assignees to return
		$assignee_arr = array();

		// get the result of the query "[[$title]][[$query_word::+]]"
		$properties_to_display = array();
		$properties_to_display[0] = $query_word;
		$results = self::getQueryResults( "[[$title_text]][[$query_word::+]]", $properties_to_display,
				false );

		// In theory, there is only one row
		while ( $row = $results->getNext() ) {
			$task_assignees = $row[0];
		}

		// If not any row, do nothing
		if ( !empty( $task_assignees ) ) {
			while ( $task_assignee = $task_assignees->getNextObject() ) {
				$assignee_name = $task_assignee->getWikiValue();
				$assignee_name = $assignee_name;

				array_push( $assignee_arr, $assignee_name );
			}
		}

		return $assignee_arr;
	}

	/**
	 * Returns an array of assignees based on $query_word
	 *
	 * @param string $query_word The property that designate the users to notify.
	 * @param string $title_text
	 * @param $user Unused
	 * @return array
	 */
	static function getGroupAssignees( $query_word, $title_text, $user ) {
		// Array of assignees to return
		$assignee_arr = array();

		// get the result of the query "[[$title]][[$query_word::+]]"
		$properties_to_display = array();
		$properties_to_display[0] = $query_word;
		$results = self::getQueryResults( "[[$title_text]][[$query_word::+]]", $properties_to_display,
				false );

		// In theory, there is only one row
		while ( $row = $results->getNext() ) {
			$group_assignees = $row[0];
		}

		// If not any row, do nothing
		if ( !empty( $group_assignees ) ) {
			while ( $group_assignee = $group_assignees->getNextObject() ) {
				$group_assignee = $group_assignee->getTitle();
				$group_name = $group_assignee->getText();
				$query_word = "Has assignee";
				$properties_to_display = array();
				$properties_to_display[0] = $query_word;
				self::printDebug( $group_name );
				$results = self::getQueryResults( "[[$group_name]][[$query_word::+]]", $properties_to_display,
						false );

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

						self::printDebug( "Groupadd: " . $assignee_name );
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
	static function getAssigneeAddresses( array $assignees ) {
		$assignee_arr = array();
		foreach ( $assignees as $assignee_name ) {
			$assignee = User::newFromName( $assignee_name );
			// if assignee is the current user, do nothing
			# if ( $assignee->getID() != $user->getID() ) {
			$assignee_mail = new MailAddress( $assignee->getEmail(), $assignee_name );
			array_push( $assignee_arr, $assignee_mail );
			self::printDebug( $assignee_name );
			# }
		}

		return $assignee_arr;
	}

	/**
	 * Sends mail notifications
	 *
	 * @global string $wgSitename
	 * @param array $assignees
	 * @param string $text
	 * @param Title $title
	 * @param User $user
	 * @param integer $status
	 */
	static function mailNotification( array $assignees, $text, Title $title, User $user, $status ) {
		global $wgSitename;

		if ( !empty( $assignees ) ) {
			$title_text = $title->getFullText();
			$from = new MailAddress( $user->getEmail(), $user->getName() );
			$link = htmlspecialchars( $title->getFullURL() );

			/** @todo This should probably be refactored */
			if ( $status == NEWTASK ) {
				$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-newtask' )->text() . ' ' .
					$title_text;
				$message = 'semantictasks-newtask-msg';
				$body = wfMessage( $message, $title_text )->text() . " " . $link;
				$body .= "\n \n" . wfMessage( 'semantictasks-text-message' )->text() . "\n" . $text;
			} elseif ( $status == UPDATE ) {
				$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-taskupdated' )->text() . ' ' .
					$title_text;
				$message = 'semantictasks-updatedtoyou-msg2';
				$body = wfMessage( $message, $title_text )->text() . " " . $link;
				$body .= "\n \n" . wfMessage( 'semantictasks-diff-message' )->text() . "\n" .
					self::generateDiffBodyTxt( $title );
			} elseif ( $status == CLOSED ) {
				$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-taskclosed' )->text() . ' ' .
					$title_text;
				$message = 'semantictasks-taskclosed-msg';
				$body = wfMessage( $message, $title_text )->text() . " " . $link;
				$body .= "\n \n" . wfMessage( 'semantictasks-text-message' )->text() . "\n" . $text;
			} elseif ( $status == UNASSIGNED ) {
				$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-taskunassigned' )->text() . ' ' .
					$title_text;
				$message = 'semantictasks-unassignedtoyou-msg2';
				$body = wfMessage( $message, $title_text )->text() . " " . $link;
				$body .= "\n \n" . wfMessage( 'semantictasks-text-message' )->text() . "\n" . $text;
			} else {
				// status == ASSIGNED
				$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-taskassigned' )->text() . ' ' .
					$title_text;
				$message = 'semantictasks-assignedtoyou-msg2';
				$body = wfMessage( $message, $title_text )->text() . " " . $link;
				$body .= "\n \n" . wfMessage( 'semantictasks-text-message' )->text() . "\n" . $text;
			}

			$user_mailer = new UserMailer();

			$user_mailer->send( $assignees, $from, $subject, $body );
		}
	}

	/**
	 * Generates a diff txt
	 *
	 * Code is similar to DifferenceEngine::generateTextDiffBody
	 * @param Title $title
	 * @return string
	 */
	static function generateDiffBodyTxt( $title ) {
		$revision = Revision::newFromTitle( $title, 0 );
		/** @todo The first parameter should be a Context. */
		$diff = new DifferenceEngine( $title, $revision->getId(), 'prev' );
		// The DifferenceEngine::getDiffBody() method generates html,
		// so let's generate the txt diff manually:
		global $wgContLang;
		$diff->loadText();
		$otext = str_replace( "\r\n", "\n", ContentHandler::getContentText( $diff->mOldContent ) );
		$ntext = str_replace( "\r\n", "\n", ContentHandler::getContentText( $diff->mNewContent ) );

		$ota = explode( "\n", $wgContLang->segmentForDiff( $otext ) );
		$nta = explode( "\n", $wgContLang->segmentForDiff( $ntext ) );
		// We use here the php diff engine included in MediaWiki
		$diffs = new Diff( $ota, $nta );
		// And we ask for a txt formatted diff
		$formatter = new UnifiedDiffFormatter();
		$diff_text = $wgContLang->unsegmentForDiff( $formatter->format( $diffs ) );
		return $diff_text;
	}

	/**
	 * This function returns the results of a certain query.
	 * Thank you Yaron Koren for advice concerning this code.
	 *
	 * @param string $query_string The query
	 * @param array(String) $properties_to_display Array of property names to display
	 * @param boolean $display_title Add the page title in the result
	 * @return SMWQueryResult
	 */
	static function getQueryResults( $query_string, array $properties_to_display, $display_title ) {
		// We use the Semantic MediaWiki Processor
		$params = array();
		$inline = true;
		$printouts = array();

		// add the page name to the printouts
		if ( $display_title ) {
			SMWQueryProcessor::addThisPrintout( $printouts, $params );
		}

		// Push the properties to display in the printout array.
		foreach ( $properties_to_display as $property ) {
			$to_push = new SMWPrintRequest(
				SMWPrintRequest::PRINT_PROP,
				$property,
				DataValueFactory::getInstance()->newPropertyValueByLabel( $property )
			);
			array_push( $printouts, $to_push );
		}

		$params = SMWQueryProcessor::getProcessedParams( $params, $printouts );

		$query = SMWQueryProcessor::createQuery( $query_string, $params, $inline, null, $printouts );
		$results = smwfGetStore()->getQueryResult( $query );

		return $results;
	}

	/**
	 * Run by the maintenance script to remind the assignees
	 *
	 * @global string $wgSitename
	 * @global Language $wgLang
	 * @return boolean
	 */
	static function remindAssignees() {
		global $wgSitename;

		# Make this equal to midnight. Rational is that if users set today as the Target date with
		# reminders set to "0" so that the reminder happens on the deadline, the reminders will go
		# out even though now it is after the beginning of today and technically past the
		# target date.
		$today = wfTimestamp( TS_ISO_8601, strtotime( 'today midnight' ) );

		# Get tasks where a reminder is called for, whose status is either new or in progress, and
		# whose target date is in the future.
		$query_string = "[[Reminder at::+]][[Status::New||In Progress]][[Target date::≥ $today]]";
		$properties_to_display = array( 'Reminder at', 'Assigned to', 'Target date' );

		$results = self::getQueryResults( $query_string, $properties_to_display, true );
		if ( empty( $results ) ) {
			return false;
		}

		while ( $row = $results->getNext() ) {
			$task_name = $row[0]->getNextObject()->getTitle();
			$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-reminder' )->text() . $task_name;
			// The following doesn't work, maybe because we use a cron job.
			// $link = $task_name->getFullURL();
			// So let's do it manually
			//$link = $wiki_url . $task_name->getPartialURL();
			// You know what? Let's try it again.
			$link = $task_name->getFullURL();

			$target_date = $row[3]->getNextObject();
			$tg_date = new DateTime( $target_date->getShortHTMLText() );

			while ( $reminder = $row[1]->getNextObject() ) {
				$remind_me_in = $reminder->getShortHTMLText();
				$date = new DateTime( 'today midnight' );
				$date->modify( "+$remind_me_in day" );

				if ( $tg_date == $date ) {
					global $wgLang;
					while ( $task_assignee = $row[2]->getNextObject() ) {
						$assignee_username = $task_assignee->getTitle()->getText();
						$assignee = User::newFromName( $assignee_username );

						$body = wfMessage( 'semantictasks-reminder-message2', $task_name,
							$wgLang->formatNum( $remind_me_in ), $link )->text();
						$assignee->sendMail( $subject, $body );
					}
				}
			}
		}
		return true;
	}

	/**
	 * Prints debugging information. $debugText is what you want to print, $debugVal
	 * is the level at which you want to print the information.
	 *
	 * @global boolean $wgSemanticTasksDebug
	 * @param string $debugText
	 * @param string $debugArr
	 * @access private
	 */
	static function printDebug( $debugText, $debugArr = null ) {
		global $wgSemanticTasksDebug;

		if ( $wgSemanticTasksDebug ) {
			if ( isset( $debugArr ) ) {
				$text = $debugText . ' ' . implode( '::', $debugArr );
				wfDebugLog( 'semantic-tasks', $text, false );
			} else {
				wfDebugLog( 'semantic-tasks', $debugText, false );
			}
		}
	}

}

<?php

namespace ST;

use Content;
use ContentHandler;
use Exception;
use IContextSource;
use Language;
use MediaWiki\Diff\ComplexityException;
use MWException;
use ParserOutput;
use SMW\Services\ServicesFactory as ApplicationFactory;
use SMW\DIWikiPage;
use SMWDataItem;
use SMWPrintRequest;
use Title;
use User;
use WikiPage;

if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'Not a valid entry point';
	exit( 1 );
}

if ( !defined( 'SMW_VERSION' ) ) {
	echo 'This extension requires Semantic MediaWiki to be installed.';
	exit( 1 );
}

// constants for message type
if ( !defined( 'ST_NEWTASK' ) ) {
	define( 'ST_NEWTASK', 0 );
	define( 'ST_UPDATE', 1 );
	define( 'ST_ASSIGNED', 2 );
	define( 'ST_CLOSED', 3 );
	define( 'ST_UNASSIGNED', 4 );
}

/**
 * This class handles the creation and sending of notification emails.
 */
class SemanticTasksMailer {

	private static $user_mailer;

	/**
	 * Mails the assignees when the task is modified
	 *
	 * @param Assignees $assignees
	 * @param WikiPage $article
	 * @param User $current_user
	 * @param Content $text
	 * @param string $summary Unused
	 * @param bool $minoredit
	 * @param null $watchthis Unused
	 * @param null $sectionanchor Unused
	 * @param $flags
	 * @return boolean
	 * @throws ComplexityException
	 * @throws MWException
	 */
	public static function mailAssigneesUpdatedTask( Assignees $assignees, WikiPage $article, User $current_user, $text,
			$summary, $minoredit, $watchthis, $sectionanchor, $flags, $revision ) {
		if ( $minoredit ) {
			return true;
		}
		$status = ST_UPDATE;
		if ( ( $flags & EDIT_NEW ) && !$article->getTitle()->isTalkPage() ) {
			$status = ST_NEWTASK;
		}

		return self::mailAssignees( $article, $text, $current_user, $status, $assignees, $revision );
	}

	/**
	 *
	 * @param WikiPage $article
	 * @param Content $content
	 * @param User $user
	 * @param integer $status
	 * @param Assignees $assignees
	 * @return boolean
	 * @throws ComplexityException
	 * @throws MWException
	 * @global boolean $wgSemanticTasksNotifyIfUnassigned
	 */
	static function mailAssignees( WikiPage $article, Content $content, User $user, $status, Assignees $assignees,
								   $revision ) {
		$text = ContentHandler::getContentText( $content );
		$title = $article->getTitle();

		$newAssignees = $assignees->getNewAssignees( $article, $revision );
		$currentAssignees = $assignees->getCurrentAssignees( $article, $revision );	
		$groups = $assignees->getGroupAssignees( $article );
		$copies = $assignees->getCurrentCarbonCopy( $article, $revision );
		
		$currentStatus = $assignees->getCurrentStatus( $article, $revision );
		$oldStatus = $assignees->getSavedStatus();

		if ( $currentStatus === "Closed" && $oldStatus !== "Closed" ) {
			$recipients = array_merge( $currentAssignees, $copies, $groups );
			$mailTo = Assignees::getAssigneeAddresses( $recipients );
			self::mailNotification( $mailTo, $text, $title, $user, ST_CLOSED );
		}

		// do not send notifications to other users if status is Closed
		if ( $currentStatus === "Closed" ) {
			return;
		}

		// Notify those unassigned from this task, default false
		global $wgSemanticTasksNotifyIfUnassigned;
		if ( $wgSemanticTasksNotifyIfUnassigned ) {
			$removedAssignees = $assignees->getRemovedAssignees( $article, $revision );
			$mailTo = Assignees::getAssigneeAddresses( $removedAssignees );
			self::mailNotification( $mailTo, $text, $title, $user, ST_UNASSIGNED );
		}

		// Send notification of an assigned task to assignees
		$mailTo = Assignees::getAssigneeAddresses( $newAssignees );
		self::mailNotification( $mailTo, $text, $title, $user, ST_ASSIGNED );

		// Send notifications to assignees, ccs, and groups
		$recipients = array_merge( $currentAssignees, $copies, $groups );
		$mailTo = Assignees::getAssigneeAddresses( $recipients );
		self::mailNotification( $mailTo, $text, $title, $user, $status );

		return true;
	}

	/**
	 * Sends mail notifications
	 *
	 * @param array $assignees
	 * @param string $text
	 * @param Title $title
	 * @param User $user
	 * @param integer $status
	 * @throws MWException
	 * @throws ComplexityException
	 * @global string $wgSitename
	 */
	static function mailNotification( array $assignees, $text, Title $title, User $user, $status ) {
		global $wgSitename, $stgNotificationFromSystemAddress, $wgPasswordSender;

		if ( empty( $assignees ) ) {
			return;
		}
		$title_text = $title->getFullText();
		$from = new \MailAddress(
			$stgNotificationFromSystemAddress ? $wgPasswordSender : $user->getEmail(),
			$stgNotificationFromSystemAddress ? $wgSitename : $user->getName()
		);
		$link = htmlspecialchars( $title->getFullURL() );

		/** @todo This should probably be refactored */
		if ( $status == ST_NEWTASK ) {
			$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-newtask' )->text() . ' ' .
				$title_text;
			$message = 'semantictasks-newtask-msg';
			$body = wfMessage( $message, $title_text )->text() . " " . $link;
			$body .= "\n \n" . wfMessage( 'semantictasks-text-message' )->text() . "\n" . $text;
		} elseif ( $status == ST_UPDATE ) {
			$context = new \RequestContext();
			$context->setTitle( $title );

			$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-taskupdated' )->text() . ' ' .
				$title_text;
			$message = 'semantictasks-updatedtoyou-msg2';
			$body = wfMessage( $message, $title_text )->text() . " " . $link;
			$body .= "\n \n" . wfMessage( 'semantictasks-diff-message' )->text() . "\n";
			$body .= self::generateDiffBodyTxt( $title, $context );
		} elseif ( $status == ST_CLOSED ) {
			$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-taskclosed' )->text() . ' ' .
				$title_text;
			$message = 'semantictasks-taskclosed-msg';
			$body = wfMessage( $message, $title_text )->text() . " " . $link;
			$body .= "\n \n" . wfMessage( 'semantictasks-text-message' )->text() . "\n" . $text;
		} elseif ( $status == ST_UNASSIGNED ) {
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

		if (!self::$user_mailer) {
			self::$user_mailer = new \ST\UserMailer(new \UserMailer());
		}

		self::$user_mailer->send( $assignees, $from, $subject, $body );
	}

	static function setUserMailer(\ST\UserMailer $user_mailer) {
		self::$user_mailer = $user_mailer;
	}

	/**
	 * Generates a diff txt
	 *
	 * Code is similar to DifferenceEngine::generateTextDiffBody
	 * @param Title $title
	 * @param IContextSource $context
	 * @return string
	 * @throws ComplexityException
	 * @throws MWException
	 */
	static function generateDiffBodyTxt( Title $title, IContextSource $context = null) {
		$diff = new \DifferenceEngine( $context );

		// The DifferenceEngine::getDiffBody() method generates html,
		// so let's generate the txt diff manually:
		$diff->loadText();
		$otext = '';
		$ntext = '';
		if ( version_compare( MW_VERSION, '1.32', '<' ) ) {
			$otext = str_replace( "\r\n", "\n", \ContentHandler::getContentText( $diff->mOldContent ) );
			$ntext = str_replace( "\r\n", "\n", \ContentHandler::getContentText( $diff->mNewContent ) );
		} else {
			if ($diff->getOldRevision()) {
				$otext = str_replace( "\r\n", "\n", ContentHandler::getContentText( $diff->getOldRevision()->getContent( 'main' ) ) );
			}
			if ($diff->getNewRevision()) {
				$ntext = str_replace( "\r\n", "\n", ContentHandler::getContentText( $diff->getNewRevision()->getContent( 'main' ) ) );
			}
		}
		$lang = \MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();

		$ota = explode( "\n", $lang->segmentForDiff( $otext ) );
		$nta = explode( "\n", $lang->segmentForDiff( $ntext ) );

		// We use here the php diff engine included in MediaWiki
		$diffs = new \Diff( $ota, $nta );
		// And we ask for a txt formatted diff
		$formatter = new \UnifiedDiffFormatter();

		$diff_text = $lang->unsegmentForDiff( $formatter->format( $diffs ) );
		return $diff_text;
	}

	/**
	 * Run by the maintenance script to remind the assignees
	 *
	 * @return boolean
	 * @throws Exception
	 * @global string $wgSitename
	 * @global Language $wgLang
	 */
	static function remindAssignees() {
		global $wgLang;
		global $wgSitename;
		global $stgPropertyReminderAt;
		global $stgPropertyAssignedTo;
		global $stgPropertyTargetDate;
		global $stgPropertyStatus;
		global $stgPropertyCarbonCopy;
		global $stgPropertyAssignedToGroup;
		global $stgPropertyHasAssignee;

		# Make this equal to midnight. Rational is that if users set today as the Target date with
		# reminders set to "0" so that the reminder happens on the deadline, the reminders will go
		# out even though now it is after the beginning of today and technically past the
		# target date.
		$today = wfTimestamp( TS_ISO_8601, strtotime( 'today midnight' ) );

		# Get tasks where a reminder is called for, whose status is either new or in progress, and
		# whose target date is in the future.

		// target date in the future is the only requirement
		$query_string = "[[$stgPropertyTargetDate::≥ $today]]";

		$properties_to_display = array(
			$stgPropertyReminderAt,
			$stgPropertyAssignedTo,
			$stgPropertyTargetDate,
			$stgPropertyCarbonCopy,
			$stgPropertyAssignedToGroup,
			$stgPropertyStatus
		);

		$results = Query::getQueryResults( $query_string, $properties_to_display, true );
		if ( empty( $results ) ) {
			return false;
		}

		while ( $row = $results->getNext() ) {
			$date = new \DateTime( 'today midnight' );
			$target_date = $row[3]->getNextDataItem();

			// must be of type date
			$tg_date = $target_date->asDateTime();

			$reminder = false;
			if ( $date->getTimestamp() === $tg_date->getTimestamp() ) {
				$reminder = true;
			}

			if ( !$reminder ) {
				while ( $reminderAt = $row[1]->getNextDataItem() ) {
					if ( $reminderAt instanceof \SMWDITime ) {
						$reminderDate = $reminderAt->asDateTime();
						if ( $date->getTimestamp() === $reminderDate->getTimestamp() ) {
							$reminder = true;
							break;
						}
					}
				}
			}

			if ( !$reminder ) {
				continue;
			}

			$status = $row[6]->getNextDataItem();

			if ( $status instanceof \SMWDIBlob ) {
				$status = $status->getString();

				if ( $status === 'Closed' ) {
					continue;
				}
			}

			$remind_me_in = $tg_date->diff( $date )->format( "%a" );
			$assignees = array();

			// Assigned to
			while ( $task_assignee = $row[2]->getNextDataItem() ) {
				$assignees[] = $task_assignee->getTitle()->getText();
			}

			// Carbon copy
			while ( $task_assignee = $row[4]->getNextDataItem() ) {
				$assignees[] = $task_assignee->getTitle()->getText();
			}

			// groups
			while ( $group_assignee = $row[5]->getNextDataItem() ) {
				$group_name = $group_assignee->getTitle()->getText();
				$query_word = $stgPropertyHasAssignee;
				$results_ = Query::getQueryResults( "[[$group_name]][[$query_word::+]]", array( $query_word ), false );

				while ( $row_ = $results_->getNext() ) {
					while ( $task_assignee = $row_[0]->getNextDataItem() ) {
						$assignees[] = $task_assignee->getTitle()->getText();
					}
				}
			}

			if ( !count ( $assignees ) ) {
				continue;
			}

			$assignees = array_unique( $assignees );

			$task_name = $row[0]->getNextDataItem()->getTitle();
			$subject = '[' . $wgSitename . '] ' . wfMessage( 'semantictasks-reminder' )->text() . $task_name;

			// ***unused var
			$link = $task_name->getFullURL();

			foreach( $assignees as $assignee_username ) {
				$body = wfMessage( 'semantictasks-reminder-message2', $task_name, $wgLang->formatNum( $remind_me_in ), $link )->text();
				$assignee = User::newFromName( $assignee_username );
				$assignee->sendMail( $subject, $body );
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

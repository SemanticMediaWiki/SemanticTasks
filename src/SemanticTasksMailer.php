<?php

namespace ST;

use Content;
use Exception;
use IContextSource;
use MediaWiki\Content\TextContent;
use MediaWiki\Diff\ComplexityException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MWException;
use SemanticTasks;
use WikiPage;

if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'Not a valid entry point';
	exit( 1 );
}

if ( !defined( 'SMW_VERSION' ) ) {
	echo 'This extension requires Semantic MediaWiki to be installed.';
	exit( 1 );
}

/**
 * This class handles the creation and sending of notification emails.
 */
class SemanticTasksMailer {
	// constants for message type
	public const NEWTASK = 0;
	public const UPDATED = 1;
	public const ASSIGNED = 2;
	public const CLOSED = 3;
	public const UNASSIGNED = 4;
	public const DELETED = 5;
	public const TALK_CREATED = 6;
	public const TALK_EDITED = 7;
	public const TALK_DELETED = 8;

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
	 * @return null|bool
	 * @throws ComplexityException
	 * @throws MWException
	 */
	public static function mailAssigneesUpdatedTask( Assignees $assignees, WikiPage $article, User $current_user, $text,
			$summary, $minoredit, $watchthis, $sectionanchor, $flags, $revision
	) {
		global $stgNotifyOnTalkPageEditOfTaskArticle;

		if ( $minoredit ) {
			return;
		}

		$status = self::UPDATED;
		if ( ( $flags & EDIT_NEW ) ) {
			$status = self::NEWTASK;
		}

		if ( $article->getTitle()->isTalkPage() ) {
			if ( !$stgNotifyOnTalkPageEditOfTaskArticle ) {
				return;
			}
	
			$article = SemanticTasks::getEffectiveArticleFromPage( $article );

			if ( ( $flags & EDIT_NEW ) ) {
				$status = self::TALK_CREATED;
			} else {
				$status = self::TALK_EDITED;
			}
		}

		return self::mailAssignees( $article, $text, $current_user, $status, $assignees, $revision );
	}

	/**
	 *
	 * @param string $key
	 * @param mixed ...$argv
	 * @return string
	 */
	public static function getMessage( $key, ...$argv ) {
		global $stgMessagesArticle;
		if ( !$stgMessagesArticle ) {
			return wfMessage( $key, ...$argv )->text();
		}
		$title = Title::newFromText( $stgMessagesArticle );
		if ( !$title || !$title->isKnown() ) {
			return wfMessage( $key, ...$argv )->text();
		}
		$semanticTasksMessages = MediaWikiServices::getInstance()->getService( 'SemanticTasksMessages' );
		return $semanticTasksMessages->getMessage( $key, ...$argv );
	}

	/**
	 *
	 * @param WikiPage $article
	 * @param Content $content
	 * @param User $user
	 * @param int $status
	 * @param Assignees $assignees
	 * @return bool
	 * @throws ComplexityException
	 * @throws MWException
	 */
	public static function mailAssignees(
		WikiPage $article,
		$content,
		User $user,
		$status,
		Assignees $assignees,
		$revision
	) {
		$text = $content instanceof TextContent ? $content->getText() : '';
		$title = $article->getTitle();

		if ( $status === self::DELETED ) {
			$assegnees = $assignees->getSavedAssignees();
			$copies = $assignees->getSavedCopies();
			$groups = $assignees->getSavedGroups();

			$recipients = array_merge( $assegnees, $copies, $groups );
			$mailTo = Assignees::getAssigneeAddresses( $recipients );
			$text = null;
			self::mailNotification( $mailTo, $text, $title, $user, $status );
			return;
		}

		$newAssignees = $assignees->getNewAssignees( $article, $revision );
		$currentAssignees = $assignees->getCurrentAssignees( $article, $revision );
		$groups = $assignees->getGroupAssignees( $article );
		$copies = $assignees->getCurrentCarbonCopy( $article, $revision );

		$currentStatus = $assignees->getCurrentStatus( $article, $revision );
		$oldStatus = $assignees->getSavedStatus();

		if ( $currentStatus === "Closed" && $oldStatus !== "Closed" ) {
			$recipients = array_merge( $currentAssignees, $copies, $groups );
			$mailTo = Assignees::getAssigneeAddresses( $recipients );
			self::mailNotification( $mailTo, $text, $title, $user, self::CLOSED );
		}

		// do not send notifications to other users if status is Closed
		if ( $currentStatus === "Closed" ) {
			return;
		}

		// Notify those unassigned from this task, default false
		global $stgNotifyIfUnassigned;

		// back-compatibility
		global $wgSemanticTasksNotifyIfUnassigned;
		if ( $stgNotifyIfUnassigned || $wgSemanticTasksNotifyIfUnassigned ) {
			$removedAssignees = $assignees->getRemovedAssignees( $article, $revision );
			$mailTo = Assignees::getAssigneeAddresses( $removedAssignees );
			self::mailNotification( $mailTo, $text, $title, $user, self::UNASSIGNED );
		}

		$notifiedUsers = [];

		// Send notification of an assigned task to new assignees
		if ( count( $newAssignees ) ) {
			$mailToNewAssignees = Assignees::getAssigneeAddresses( $newAssignees );
			self::mailNotification( $mailToNewAssignees, $text, $title, $user, self::ASSIGNED );
	
			$notifiedUsers = array_map( static function ( $value ) {
				return $value->name;
			}, $mailToNewAssignees );
		}

		// Send notifications to assignees, ccs, and groups
		$recipients = array_merge( $currentAssignees, $copies, $groups );
		$mailToAssignees = Assignees::getAssigneeAddresses( $recipients );

		// ensure recipients do not overlap
		$mailToAssignees = array_filter( $mailToAssignees, static function ( $value ) use ( $notifiedUsers ) {
			return !in_array( $value->name, $notifiedUsers );
		} );

		self::mailNotification( $mailToAssignees, $text, $title, $user, $status );

		return true;
	}

	/**
	 * Sends mail notifications
	 *
	 * @param array $assignees
	 * @param string $text
	 * @param Title $title
	 * @param User $user
	 * @param int $status
	 * @throws MWException
	 * @throws ComplexityException
	 */
	public static function mailNotification( array $assignees, $text, Title $title, User $user, $status ) {
		global $wgSitename, $stgNotificationFromSystemAddress, $wgPasswordSender;

		if ( empty( $assignees ) ) {
			return;
		}

		$title_text = $title->getFullText();
		$from = new \MailAddress(
			$stgNotificationFromSystemAddress ? $wgPasswordSender : $user->getEmail(),
			$stgNotificationFromSystemAddress ? $wgSitename : $user->getName()
		);
		$link = htmlspecialchars( $title->getFullURL() ?? '' );
		$subject = '[' . $wgSitename . '] ';

		/** @TODO This should probably be refactored */
		if ( $status == self::NEWTASK ) {
			$subject .= self::getMessage( 'semantictasks-newtask-subject', $title_text );
			$body = self::getMessage( 'semantictasks-newtask-body-1', $title_text ) . " " . $link;
			$body .= "\n \n" . self::getMessage( 'semantictasks-newtask-body-2' ) . "\n" . $text;

		} elseif ( $status == self::UPDATED ) {
			$context = new \RequestContext();
			$context->setTitle( $title );

			$subject .= self::getMessage( 'semantictasks-taskupdated-subject', $title_text );
			$body = self::getMessage( 'semantictasks-taskupdated-body-1', $title_text ) . " " . $link;
			$body .= "\n \n" . self::getMessage( 'semantictasks-taskupdated-body-2' ) . "\n";
			$body .= self::generateDiffBodyTxt( $title, $context );

		} elseif ( $status == self::CLOSED ) {
			$subject .= self::getMessage( 'semantictasks-taskclosed-subject', $title_text );
			$body = self::getMessage( 'semantictasks-taskclosed-body-1', $title_text ) . " " . $link;
			$body .= "\n \n" . self::getMessage( 'semantictasks-taskclosed-body-2' ) . "\n" . $text;

		} elseif ( $status == self::UNASSIGNED ) {
			$subject .= self::getMessage( 'semantictasks-taskunassigned-subject', $title_text );
			$body = self::getMessage( 'semantictasks-taskunassigned-body-1', $title_text ) . " " . $link;
			$body .= "\n \n" . self::getMessage( 'semantictasks-taskunassigned-body-2' ) . "\n" . $text;

		} elseif ( $status == self::TALK_CREATED ) {
			$subject .= self::getMessage( 'semantictasks-talkpageoftaskcreated-subject', $title_text );
			$body = self::getMessage( 'semantictasks-talkpageoftaskcreated-body-1', $title_text );

		} elseif ( $status == self::TALK_EDITED ) {
			$subject .= self::getMessage( 'semantictasks-talkpageoftaskedited-subject', $title_text );
			$body = self::getMessage( 'semantictasks-talkpageoftaskedited-body-1', $title_text );

		} elseif ( $status == self::DELETED ) {
			$subject .= self::getMessage( 'semantictasks-taskdeleted-subject', $title_text );
			$body = self::getMessage( 'semantictasks-taskdeleted-body-1', $title_text );

		} elseif ( $status == self::TALK_DELETED ) {
			$subject .= self::getMessage( 'semantictasks-talkpageoftaskdeleted-subject', $title_text );
			$body = self::getMessage( 'semantictasks-talkpageoftaskdeleted-body-1', $title_text );

		} else {
			// status == ASSIGNED
			$subject .= self::getMessage( 'semantictasks-taskassigned-subject', $title_text );
			$body = self::getMessage( 'semantictasks-taskassigned-body-1', $title_text ) . " " . $link;
			$body .= "\n \n" . self::getMessage( 'semantictasks-taskassigned-body-2' ) . "\n" . $text;
		}

		if ( !self::$user_mailer ) {
			self::$user_mailer = new \ST\UserMailer( new \UserMailer() );
		}

		self::$user_mailer->send( $assignees, $from, $subject, $body );
	}

	public static function setUserMailer( \ST\UserMailer $user_mailer ) {
		self::$user_mailer = $user_mailer;
	}

	/**
	 * Generates a diff txt
	 *
	 * Code is similar to DifferenceEngine::generateTextDiffBody
	 * @param Title $title
	 * @param IContextSource|null $context
	 * @return string
	 * @throws ComplexityException
	 * @throws MWException
	 */
	public static function generateDiffBodyTxt( Title $title, ?IContextSource $context = null ) {
		$diff = new \DifferenceEngine( $context );

		// The DifferenceEngine::getDiffBody() method generates html,
		// so let's generate the txt diff manually:
		$diff->loadText();
		$otext = '';
		$ntext = '';
		if ( $diff->getOldRevision() ) {
			$content = $diff->getOldRevision()->getContent( 'main' );
			$text = $content instanceof TextContent ? $content->getText() : '';
			$otext = str_replace( "\r\n", "\n", $text );
		}
		if ( $diff->getNewRevision() ) {
			$content = $diff->getNewRevision()->getContent( 'main' );
			$text = $content instanceof TextContent ? $content->getText() : '';
			$ntext = str_replace( "\r\n", "\n", $text );
		}
		$lang = MediaWikiServices::getInstance()->getContentLanguage();

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
	 * @return bool
	 * @throws Exception
	 */
	public static function remindAssignees() {
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

		$properties_to_display = [
			$stgPropertyReminderAt,
			$stgPropertyAssignedTo,
			$stgPropertyTargetDate,
			$stgPropertyCarbonCopy,
			$stgPropertyAssignedToGroup,
			$stgPropertyStatus
		];

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
			$assignees = [];

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
				$results_ = Query::getQueryResults( "[[$group_name]][[$query_word::+]]", [ $query_word ], false );

				while ( $row_ = $results_->getNext() ) {
					while ( $task_assignee = $row_[0]->getNextDataItem() ) {
						$assignees[] = $task_assignee->getTitle()->getText();
					}
				}
			}

			if ( !count( $assignees ) ) {
				continue;
			}

			$assignees = array_unique( $assignees );

			$task_name = $row[0]->getNextDataItem()->getTitle();
			$subject = '[' . $wgSitename . '] ' . self::getMessage( 'semantictasks-reminder-subject', $task_name );

			// ***unused var
			$link = $task_name->getFullURL();

			foreach ( $assignees as $assignee_username ) {
				$body = self::getMessage( 'semantictasks-reminder-body-1', $task_name, $wgLang->formatNum( $remind_me_in ), $link );
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
	 * @param string $debugText
	 * @param string|null $debugArr
	 * @private
	 */
	public static function printDebug( $debugText, $debugArr = null ) {
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

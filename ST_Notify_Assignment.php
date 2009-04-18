<?php
# (C) 2008 Steren Giannini
# Licensed under the GNU GPLv2 (or later).
function fnMailAssignees_updated_task( $article, $current_user, $text, $summary, $minoredit, $watchthis, $sectionanchor, $flags, $revision ) {
	if ( !$minoredit ) {
		// i18n
		wfLoadExtensionMessages( 'SemanticTasks' );

		// Grab the wiki name
		global $wgSitename;

		// Get the revision count to determine if new article
		$rev = $article->estimateRevisionCount();

		if ( $rev == 1 ) {
			fnMailAssignees( $article, $current_user, '[' . $wgSitename . '] ' . wfMsg( 'semantictasks-newtask' ), 'semantictasks-assignedtoyou-msg', /*diff?*/ false, /*Page text*/ true );
		} else {
			fnMailAssignees( $article, $current_user, '[' . $wgSitename . '] ' . wfMsg( 'semantictasks-taskupdated' ), 'semantictasks-updatedtoyou-msg', /*diff?*/ true, /*Page text*/ false );
		}
	}
	return TRUE;
}

function fnMailAssignees( $article, $user, $pre_title, $message, $display_diff, $display_text ) {
	$title = $article->getTitle();

	// Send notifications to assignees and ccs
	fnMailNotification( 'Assigned to', $article, $user, $pre_title, $message, $display_diff, $display_text );
	fnMailNotification( 'Carbon copy', $article, $user, $pre_title, $message, $display_diff, $display_text );
	return TRUE;
}

/**
* Sends mail notifications
* @param $query_word String: the property that designate the users to notify.
*/
function fnMailNotification( $query_word, $article, $user, $pre_title, $message, $display_diff, $display_text ) {
	$title = $article->getTitle();

	// get the result of the query "[[$title]][[$query_word::+]]"
	$properties_to_display = array();
	$properties_to_display[0] = $query_word;
	$results = st_get_query_results( "[[$title]][[$query_word::+]]", $properties_to_display, false );

	// In theory, there is only one row
	while ( $row = $results->getNext() ) {
		$task_assignees = $row[0];
	}

	// If not any row, do nothing
	if ( empty( $task_assignees ) ) {
		return FALSE;
	}

	$subject = "$pre_title $title";
	$from = new MailAddress( $user->getEmail(), $user->getName() );
	$link = $title->escapeFullURL();

	$user_mailer = new UserMailer();

	while ( $task_assignee = $task_assignees->getNextObject() ) {
		$assignee_username = $task_assignee->getTitle();
		$assignee_user_name = explode( ":", $assignee_username );
		$assignee_name = $assignee_user_name[1];
		$body = wfMsg( $message , $assignee_name , $title ) . $link;
		if ( $display_text ) {
			$body .= "\n \n" . wfMsg( 'semantictasks-text-message' ) . "\n" . $article->getContent() ;
		}
		if ( $display_diff ) {
			$body .= "\n \n" . wfMsg( 'semantictasks-diff-message' ) . "\n" . st_generateDiffBody_txt( $title );
		}

		// TEST: uncomment this for test mode (Writes body in testFile)
		// st_WriteTestFile( $body );

		$assignee = User::newFromName( $assignee_name );
		// if assignee is the current user, do nothing
		if ( $assignee->getID() != $user->getID() ) {
			$assignee_mail = new MailAddress( $assignee->getEmail(), $assignee_name );
			$user_mailer->send( $assignee_mail, $from, $subject, $body );
		}
	}

	return TRUE;
}

/**
* Generates a diff txt
* @param Title $title
* @return string
*/
function st_generateDiffBody_txt( $title ) {
	$revision = Revision::newFromTitle( $title, 0 );
	$diff = new DifferenceEngine( $title, $revision->getId(), 'prev' );
	// The getDiffBody() method generates html, so let's generate the txt diff manualy:
		global $wgContLang;
		$diff->loadText();
		$otext = str_replace( "\r\n", "\n", $diff->mOldtext );
		$ntext = str_replace( "\r\n", "\n", $diff->mNewtext );
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
* This function returns to results of a certain query
* Thank you Yaron Koren for advices concerning this code
* @param $query_string String : the query
* @param $properties_to_display array(String): array of property names to display
* @param $display_title Boolean : add the page title in the result
* @return TODO
*/
function st_get_query_results( $query_string, $properties_to_display, $display_title ) {
	// i18n
	wfLoadExtensionMessages( 'SemanticTasks' );

	// We use the Semantic Media Wiki Processor
	global $smwgIP;
	include_once( $smwgIP . "/includes/SMW_QueryProcessor.php" );

	$params = array();
	$inline = true;
	$format = 'auto';
	$printlabel = "";
	$printouts = array();

	// add the page name to the printouts
	if ( $display_title ) {
		$to_push = new SMWPrintRequest( SMWPrintRequest::PRINT_THIS, $printlabel );
		array_push( $printouts, $to_push );
	}

	// Push the properties to display in the printout array.
	foreach ( $properties_to_display as $property ) {
		if ( class_exists( 'SMWPropertyValue' ) ) { // SMW 1.4
			$to_push = new SMWPrintRequest( SMWPrintRequest::PRINT_PROP, $printlabel, SMWPropertyValue::makeProperty( $property ) );
		} else {
			$to_push = new SMWPrintRequest( SMWPrintRequest::PRINT_PROP, $printlabel, Title::newFromText( $property, SMW_NS_PROPERTY ) );
		}
		array_push( $printouts, $to_push );
	}

	$query = SMWQueryProcessor::createQuery( $query_string, $params, $inline, $format, $printouts );
	$results = smwfGetStore()->getQueryResult( $query );

	return $results;
}

function fnRemindAssignees( $wiki_url ) {
	global $wgSitename, $wgServer;

	$user_mailer = new UserMailer();

	$t = getdate();
	$today = date( 'F d Y', $t[0] );

	$query_string = "[[Reminder at::+]][[Status::New||In Progress]][[Target date::> $today]]";
	$properties_to_display = array( 'Reminder at', 'Assigned to', 'Target date' );

	$results = st_get_query_results( $query_string, $properties_to_display, true );
	if ( empty( $results ) ) {
		return FALSE;
	}

	$sender = new MailAddress( "no-reply@$wgServerName", "$wgSitename" );

	while ( $row = $results->getNext() ) {
		$task_name = $row[0]->getNextObject()->getTitle();
		$subject = '[' . $wgSitename . '] ' . wfMsg( 'semantictasks-reminder' ) . $task_name;
		// The following doesn't work, maybe because we use a cron job.
		// $link = $task_name->escapeFullURL();
		// So let's do it manually
		$link = $wiki_url . $task_name->getPartialURL();

		$target_date = $row[3]->getNextObject();
		$tg_date = new DateTime( $target_date->getShortHTMLText() );

		while ( $reminder = $row[1]->getNextObject() ) {
			$remind_me_in = $reminder->getShortHTMLText();
			$date = new DateTime( $today );
			$date->modify( "+$remind_me_in day" );

			if ( $tg_date-> format( 'F d Y' ) == $date-> format( 'F d Y' ) ) {
				global $wgLang;
				while ( $task_assignee = $row[2]->getNextObject() ) {
					$assignee_username = $task_assignee->getTitle();
					$assignee_user_name = explode( ":", $assignee_username );
					$assignee_name = $assignee_user_name[1];

					$assignee = User::newFromName( $assignee_name );
					$assignee_mail = new MailAddress( $assignee->getEmail(), $assignee_name );
					$body = wfMsgExt( 'semantictasks-reminder-message', 'parsemag', $assignee_name, $task_name, $wgLang->formatNum( $remind_me_in ), $link );
					$user_mailer->send( $assignee_mail, $sender, $subject, $body );
				}
			}
		}
	}
	return TRUE;
}

function st_SetupExtension() {
	global $wgHooks;
	$wgHooks['ArticleSaveComplete'][] = 'fnMailAssignees_updated_task';
	return true;
}

/**
* This function is for test mode only, it write its argument in a specific file.
* This file must be writable for the system and be at the roor of your wiki installation
* @param $stringData String : to write
*/
function st_WriteTestFile( $stringData ) {
	$testFile = "testFile.txt";
	$fh = fopen( $testFile, 'w' ) or die( "can't open file" );
	fwrite( $fh, $stringData );
	fclose( $fh );
}

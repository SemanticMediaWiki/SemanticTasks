<?php

/**
 * @see https://github.com/SemanticMediaWiki/SemanticTasks
 *
 * @defgroup SemanticTasks Semantic Tasks
 */

use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MWException;
use ST\SemanticTasksMailer;

SemanticTasks::load();

/**
 * @codeCoverageIgnore
 */
class SemanticTasks {

	public static function load() {
		if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
			include_once __DIR__ . '/vendor/autoload.php';
		}
	}

	/**
	 * @since 1.0
	 * @see https://www.mediawiki.org/wiki/Manual:Extension.json/Schema#callback
	 */
	public static function initExtension( $credits = [] ) {
		$version = 'UNKNOWN';

		// See https://phabricator.wikimedia.org/T151136
		if ( isset( $credits['version'] ) ) {
			$version = $credits['version'];
		}

		define( 'SEMANTIC_TASKS', $version );

		// https://phabricator.wikimedia.org/T212738
		if ( !defined( 'MW_VERSION' ) ) {
			define( 'MW_VERSION', $GLOBALS['wgVersion'] );
		}
	}

	/**
	 * @see https://github.com/SemanticMediaWiki/KnowledgeGraph/blob/main/includes/KnowledgeGraph.php
	 *
	 * @return bool
	 */
	private static function ensureSemanticTasksMessagesExists() {
		global $stgMessagesArticle;

		if ( !$stgMessagesArticle ) {
			return false;
		}

		$title = Title::newFromText( $stgMessagesArticle );
		if ( !$title ) {
			 throw new MWException( 'Title for SemanticTasksMessage not valid' );
		}

		if ( $title->isKnown() ) {
			return true;
		}

		// Create page content
		$filePath = __DIR__ . '/data/SemanticTasksMessages.wikitext';

		if ( !file_exists( $filePath ) ) {
			wfDebugLog( 'SemanticTasks', 'Missing SemanticTasksMessages.wikitext.' );
			return;
		}

		$text = file_get_contents( $filePath );
		
		$content = ContentHandler::makeContent(
			$text,
			$title
		);

		$user = User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );

		$wikiPage = MediaWikiServices::getInstance()
			->getWikiPageFactory()->newFromTitle( $title );

		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( SlotRecord::MAIN, $content );
		$pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'Initialize KnowledgeGraphOptions' ),
			EDIT_SUPPRESS_RC
		);
	}

	/**
	 * @param Title $title
	 * @return WikiPage
	 */
	public static function getEffectiveArticle( $title ) {
		if ( !$title->isTalkPage() ) {
			return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		}

		$subjectTitle = $title->getSubjectPage();
		return MediaWikiServices::getInstance()
			->getWikiPageFactory()->newFromTitle( $subjectTitle );
	}

	/**
	 * @param WikiPage $wikiPage
	 * @return WikiPage
	 */
	public static function getEffectiveArticleFromPage( $wikiPage ) {
		$title = $wikiPage->getTitle();
		if ( !$title->isTalkPage() ) {
			return $wikiPage;
		}

		$subjectTitle = $title->getSubjectPage();
		return MediaWikiServices::getInstance()
			->getWikiPageFactory()->newFromTitle( $subjectTitle );
	}

	/**
	 * @since 1.0
	 */
	public static function onExtensionFunction() {
		// Check requirements after LocalSetting.php has been processed
		if ( !defined( 'SMW_VERSION' ) ) {
			if ( PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' ) {
				die( "\nThe 'Semantic Tasks' extension requires the 'Semantic MediaWiki' extension to be installed and enabled.\n" );
			} else {
				die(
					'<b>Error:</b> The <a href="https://github.com/SemanticMediaWiki/SemanticTasks">Semantic Tasks</a> extension' .
					' requires the <a href="https://www.semantic-mediawiki.org/wiki/Semantic_MediaWiki">Semantic MediaWiki</a> extension to be installed and enabled.<br />'
				);
			}
		}

		self::ensureSemanticTasksMessagesExists();

		$assignees = new \ST\Assignees();

		// Register extension hooks.
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();

		$semanticTasksMessages = MediaWikiServices::getInstance()->getService( 'SemanticTasksMessages' );

		$hookContainer->register( 'MultiContentSave', [ $assignees, 'saveAssigneesMultiContentSave' ] );

		$hookContainer->register( 'PageSaveComplete', static function ( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags, MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult ) use ( $assignees, $semanticTasksMessages ) {
			// @see includes/Storage/PageUpdater.php
			$mainContent = $revisionRecord->getContent( MediaWiki\Revision\SlotRecord::MAIN, MediaWiki\Revision\RevisionRecord::RAW );
			$minoredit = $editResult->isNullEdit() || ( $flags & EDIT_MINOR )
				// *** this is for the use in conjunction with WSSlots
				|| ( $flags & EDIT_INTERNAL );
			$watchthis = null;
			$sectionanchor = null;

			SemanticTasksMailer::mailAssigneesUpdatedTask(
				$assignees, $wikiPage, $user, $mainContent,
				$summary, $minoredit, $watchthis, $sectionanchor, $flags, $revisionRecord
			);

			$semanticTasksMessages->handlePageUpdate( $wikiPage->getTitle() );
		} );

		$hookContainer->register( 'PageDelete', static function ( $wikiPage, $deleter, string $reason, StatusValue $status, bool $suppress ) use ( $assignees ) {
			$assignees->saveAssigneesPageDelete( $wikiPage );
		} );

		// @see https://github.com/SemanticMediaWiki/SemanticTasks/issues/67
		$hookContainer->register( 'PageDeleteComplete', static function ( $pageRecord, $deleter, $reason, $pageID, $deletedRev, $logEntry, $archivedRevisionCount ) use ( $assignees, $semanticTasksMessages ) {
			global $stgNotifyOnDeleteTaskArticle;
			global $stgNotifyOnTalkPageEditOfTaskArticle;
			$user = $deleter->getUser();
			$text = null;
			$revision = null;

			$title = $pageRecord->getTitle();

			$semanticTasksMessages->handlePageUpdate( $title );

			// directly send email
			if ( !$title->isTalkPage() ) {
				if ( !$stgNotifyOnDeleteTaskArticle ) {
					return;
				}

				$wikiPage = $pageRecord;
				$status = SemanticTasksMailer::DELETED;

			// retrieve subject article
			} else {
				if ( !$stgNotifyOnTalkPageEditOfTaskArticle ) {
					return;
				}

				$wikiPage = SemanticTasks::getEffectiveArticle( $title );
				$status =  SemanticTasksMailer::TALK_DELETED;
			}

			SemanticTasksMailer::mailAssignees( $wikiPage, $text, $user, $status, $assignees, $revision );
		} );

	}
}

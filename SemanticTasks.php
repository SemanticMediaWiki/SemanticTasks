<?php

/**
 * @see https://github.com/SemanticMediaWiki/SemanticTasks
 *
 * @defgroup SemanticTasks Semantic Tasks
 */

use MediaWiki\MediaWikiServices;
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

		$assignees = new \ST\Assignees();

		// Register extension hooks.
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		$hookContainer->register( 'MultiContentSave', [ $assignees, 'saveAssigneesMultiContentSave' ] );
		$hookContainer->register( 'PageSaveComplete', static function ( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags, MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult ) use ( $assignees ) {
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
		} );
	}

}

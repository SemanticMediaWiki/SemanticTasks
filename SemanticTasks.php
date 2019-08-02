<?php

/**
 * @see https://github.com/SemanticMediaWiki/SemanticTasks
 *
 * @defgroup SemanticTasks Semantic Tasks
 */

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
	 * @global boolean $wgSemanticTasksNotifyIfUnassigned
	 * @since 1.0
	 * @see https://www.mediawiki.org/wiki/Manual:Extension.json/Schema#callback
	 */
	public static function initExtension( $credits = [] ) {

		$version = 'UNKNOWN' ;

		// See https://phabricator.wikimedia.org/T151136
		if ( isset( $credits['version'] ) ) {
			$version = $credits['version'];
		}

		define( 'SEMANTIC_TASKS', $version );

		// Register extension messages and other localisation.
		$wgMessagesDirs['SemanticTasks'] = __DIR__ . '/i18n';

		// Set to true to notify users when they are unassigned from a task
		global $wgSemanticTasksNotifyIfUnassigned;
		$wgSemanticTasksNotifyIfUnassigned = false;
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
		global $wgHooks;
		$wgHooks['PageContentSave'][] = [ $assignees, 'saveAssignees' ];
		$wgHooks['PageContentSaveComplete'][] = function(WikiPage $article, User $current_user, $text,
				$summary, $minoredit, $watchthis, $sectionanchor, $flags) use ($assignees) {
			error_log('hA?');
			SemanticTasksMailer::mailAssigneesUpdatedTask(
				$assignees, $article, $current_user, $text,
				$summary, $minoredit, $watchthis, $sectionanchor, $flags
			);
		}; //[ 'SemanticTasksMailer::mailAssigneesUpdatedTask', $assignees ];
	}

}

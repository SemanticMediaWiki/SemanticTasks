<?php

/**
 * @see https://github.com/SemanticMediaWiki/SemanticTasks
 *
 * @defgroup SemanticTasks Semantic Tasks
 */
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

		$version = 'UNKNOWN' ;

		// See https://phabricator.wikimedia.org/T151136
		if ( isset( $credits['version'] ) ) {
			$version = $credits['version'];
		}

		define( 'SEMANTIC_TASKS', $version );

		// Register extension messages and other localisation.
		$wgMessagesDirs['SemanticTasks'] = __DIR__ . '/i18n';

		// Set to true to notify users when they are unassigned from a task
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

		// Register extension hooks.
		$wgHooks['PageContentSaveComplete'][] = 'SemanticTasksMailer::mailAssigneesUpdatedTask';
		$wgHooks['PageContentSave'][] = 'SemanticTasksMailer::findOldValues';
	}

}

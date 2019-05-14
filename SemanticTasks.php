<?php

// This is the path to your installation of SemanticTasks as seen from the web.
// Change it if required ($wgScriptPath is the path to the base directory of
// your wiki). No final slash. It appears to be unused.
$stScriptPath = $wgScriptPath . '/extensions/SemanticTasks';

// Set to true to notify users when they are unassigned from a task
$wgSemanticTasksNotifyIfUnassigned = false;


SemanticTasks::load();

class SemanticTasks {

	public static function load() {
		if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
			include_once __DIR__ . '/vendor/autoload.php';
		}
	}

	public static function initExtension() {
		// Register extension messages and other localisation.
		$wgMessagesDirs['SemanticTasks'] = __DIR__ . '/i18n';

		// Register extension hooks.
		$wgHooks['PageContentSaveComplete'][] = 'SemanticTasksMailer::mailAssigneesUpdatedTask';
		$wgHooks['PageContentSave'][] = 'SemanticTasksMailer::findOldValues';
	}

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


		$hookRegistry = new HookRegistry();
		$hookRegistry->register();
	}
}

<?php

// Ensure that the script cannot be executed outside of MediaWiki.
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is an extension to MediaWiki and cannot be run standalone.' );
}

// Ensure that Semantic MediaWiki is installed.
if ( !defined( 'SMW_VERSION' ) ) {
	die( 'This extension requires Semantic MediaWiki to be installed.' );
}

// This is the path to your installation of SemanticTasks as seen from the web.
// Change it if required ($wgScriptPath is the path to the base directory of
// your wiki). No final slash. It appears to be unused.
$stScriptPath = $wgScriptPath . '/extensions/SemanticTasks';

// Set to true to notify users when they are unassigned from a task
$wgSemanticTasksNotifyIfUnassigned = false;

// Display extension properties on MediaWiki.
$wgExtensionCredits['semantic'][] = array(
	'path' => __FILE__,
	'name' => 'SemanticTasks',
	'author' => array(
		'Steren Giannini',
		'Ryan Lane',
		'Ike Hecht',
		'...'
	),
	'version' => '1.7.0',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Semantic_Tasks',
	'descriptionmsg' => 'semantictasks-desc',
	'license-name' => 'GPL-2.0-or-later'
);

// Register extension messages and other localisation.
$wgMessagesDirs['SemanticTasks'] = __DIR__ . '/i18n';

// Load extension's classes.
$wgAutoloadClasses['SemanticTasksMailer'] = __DIR__ . '/SemanticTasks.classes.php';

// Register extension hooks.
$wgHooks['PageContentSaveComplete'][] = 'SemanticTasksMailer::mailAssigneesUpdatedTask';
$wgHooks['PageContentSave'][] = 'SemanticTasksMailer::findOldValues';

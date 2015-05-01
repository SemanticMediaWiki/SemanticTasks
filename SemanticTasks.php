<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'Not a valid entry point';
	exit( 1 );
}

if ( !defined( 'SMW_VERSION' ) ) {
	echo 'This extension requires Semantic MediaWiki to be installed.';
	exit( 1 );
}

# This is the path to your installation of SemanticTasks as
# seen from the web. Change it if required ($wgScriptPath is the
# path to the base directory of your wiki). No final slash. It appears to be unused.
$stScriptPath = $wgScriptPath . '/extensions/SemanticTasks';

# Set to true to notify users when they are unassigned from a task
$wgSemanticTasksNotifyIfUnassigned = false;


# Extension credits
$wgExtensionCredits[defined( 'SEMANTIC_EXTENSION_TYPE' ) ? 'semantic' : 'other'][] = array(
	'path' => __FILE__,
	'name' => 'SemanticTasks',
	'author' => array(
		'Steren Giannini',
		'Ryan Lane',
		'Ike Hecht'
	),
	'version' => '1.6.0',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Semantic_Tasks',
	'descriptionmsg' => 'semantictasks-desc',
);

// i18n
$wgMessagesDirs['SemanticTasks'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['SemanticTasks'] = __DIR__ . '/SemanticTasks.i18n.php';

// Autoloading
$wgAutoloadClasses['SemanticTasksMailer'] = __DIR__ . '/SemanticTasks.classes.php';

// Hooks
$wgHooks['PageContentSaveComplete'][] = 'SemanticTasksMailer::mailAssigneesUpdatedTask';
$wgHooks['PageContentSave'][] = 'SemanticTasksMailer::findOldValues';

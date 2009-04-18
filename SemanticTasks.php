<?php

#
# This is the path to your installation of SemanticTasks as
# seen from the web. Change it if required ($wgScriptPath is the
# path to the base directory of your wiki). No final slash.
# #
$stScriptPath = $wgScriptPath . '/extensions/SemanticTasks';
#

#
# This is the path to your installation of SemanticTasks as
# seen on your local filesystem. Used against some PHP file path
# issues.
#
$stIP = $IP . '/extensions/SemanticTasks';
#

# Informations
$wgExtensionCredits['parserhook'][] = array(
	'name' => 'SemanticTasks',
	'author' => 'Steren Giannini',
	'version' => '1.1.1',
	'url' => 'http://www.creativecommons.org', // FIXME: URL should point to a page about this extension
	'description' => 'E-mail notifications for assigned or updated tasks',
	'descriptionmsg' => 'semantictasks-desc',
);

// Do st_SetupExtension after the mediawiki setup, AND after SemanticMediaWiki setup
// FIXME: only hook added is ArticleSaveComplete. There appears to be no need for this.
$wgExtensionFunctions[] = 'st_SetupExtension';

// i18n
$wgExtensionMessagesFiles['SemanticTasks'] = dirname( __FILE__ ) . '/SemanticTasks.i18n.php';

// FIXME: Use $wgAutoloadClasses
// ST_Notify_Assignment.php
require_once( $stIP . "/ST_Notify_Assignment.php" );

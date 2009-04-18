<?php

###
# This is the path to your installation of SemanticTasks as
# seen from the web. Change it if required ($wgScriptPath is the
# path to the base directory of your wiki). No final slash.
##
$stScriptPath = $wgScriptPath . '/extensions/SemanticTasks';
##

###
# This is the path to your installation of SemanticTasks as
# seen on your local filesystem. Used against some PHP file path
# issues.
##
$stIP = $IP . '/extensions/SemanticTasks';
##

#Informations
$wgExtensionCredits['parserhook'][] = array(
       'name' => 'SemanticTasks',
       'author' =>'Steren Giannini', 
       'url' => 'http://www.creativecommons.org', 
       'description' => 'Email notifications for assigned or updated tasks.'
       );

//Do st_SetupExtension after the mediawiki setup, AND after SemanticMediaWiki setup
$wgExtensionFunctions[] = 'st_SetupExtension';

//i18n
$wgExtensionMessagesFiles['SemanticTasks'] = dirname( __FILE__ ) . '/SemanticTasks.i18n.php';

// ST_Notify_Assignment.php
require_once($stIP . "/ST_Notify_Assignment.php");

?>

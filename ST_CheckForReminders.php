<?php
# (C) 2008 Steren Giannini
# Licensed under the GNU GPLv2 (or later).

$IP = realpath( dirname( __FILE__ ) . "/../..");
require_once( "$IP/maintenance/commandLine.inc" );

global $smwgIP;
require_once( $smwgIP . '/includes/SMW_Factbox.php' );

global $stIP;
require_once( $stIP . '/ST_Notify_Assignment.php' );

//Let's send reminders
fnRemindAssignees( 'http://teamspace.creativecommons.org/' );

print( "ST check for reminders\n" );
?>

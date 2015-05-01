<?php
# (C) 2008 Steren Giannini
# Licensed under the GNU GPLv2 (or later).

$IP = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../..';
require_once $IP . "/maintenance/maintenance.php";

class CheckForReminders extends Maintenance {

	public function execute() {
		require_once __DIR__ . '/SemanticTasks.classes.php';
		// Let's send reminders
		SemanticTasksMailer::remindAssignees();
		print "ST check for reminders\n";
	}
}

$maintClass = 'CheckForReminders';

require_once RUN_MAINTENANCE_IF_MAIN;

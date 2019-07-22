<?php
namespace ST\Tests;

use ST\SemanticTasksMailer;
use ST\UserMailer;

/**
 * @covers \ST\Tests
 * @group semantic-tasks
 *
 * @license GNU GPL v2+
 * @since 3.0
 */
class SemanticTasksMailerTest extends \PHPUnit_Framework_TestCase {

	// todo: expand tests
	public function testMailNotification() {
		$userMailerMock = $this->createMock(\ST\UserMailer::class);

		$assignees = [ 'someone' ];
		$text = '';
		$title = new \Title();
		$user = new \User();
		$status = 0; //ST_NEWTASK

		$userMailerMock->expects($this->once())
			->method('send')
			->with($assignees, $this->anything(), $this->anything(), $this->anything(), $this->anything())
			->willReturn(\Status::newGood());

		SemanticTasksMailer::setUserMailer($userMailerMock);
		SemanticTasksMailer::mailNotification( $assignees, $text, $title, $user, $status );
	}

	// todo: fix: needs database..
	/*public function testGenerateDiffBodyTxt() {
		$title = new \Title('test');
		$returnText = SemanticTasksMailer::generateDiffBodyTxt( $title );
	}*/
}

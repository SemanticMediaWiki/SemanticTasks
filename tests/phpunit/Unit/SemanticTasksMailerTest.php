<?php
namespace ST\Tests;

use MediaWiki\Diff\ComplexityException;
use ST\Assignees;
use ST\SemanticTasksMailer;
use TextContent;
use Title;
use User;
use WikiPage;

/**
 * @covers \ST\Tests
 * @group semantic-tasks
 *
 * @license GNU GPL v2+
 * @since 3.0
 */
class SemanticTasksMailerTest extends \MediaWikiTestCase {

	/** @todo: expand tests */
	/**
	 * @covers \ST\SemanticTasksMailer::mailNotification
	 * @throws ComplexityException
	 * @throws \MWException
	 */
	public function testMailNotification() {
		$userMailerMock = $this->createMock(\ST\UserMailer::class);

		$assignees = [ 'someone' ];
		$text = '';
		$title = new Title();
		$user = new \User();
		$status = 0; //ST_NEWTASK

		$userMailerMock->expects($this->once())
			->method('send')
			->with($assignees, $this->anything(), $this->anything(), $this->anything(), $this->anything())
			->willReturn(\Status::newGood());

		SemanticTasksMailer::setUserMailer($userMailerMock);
		SemanticTasksMailer::mailNotification( $assignees, $text, $title, $user, $status );
	}

	/**
	 * @covers SemanticTasksMailer::generateDiffBodyTxt
	 * @throws ComplexityException
	 * @throws \MWException
	 */
	public function testGenerateDiffBodyTxt() {
		$namespace = $this->getDefaultWikitextNS();
		$title = Title::newFromText( 'Kitten', $namespace );

		$context = new \RequestContext();
		$context->setTitle( $title );

		$page = WikiPage::factory( $title );
		$strings = [ "it is a kitten", "two kittens", "three kittens", "four kittens" ];
		$revisions = [];
		foreach ( $strings as $string ) {
			$content = ContentHandler::makeContent( $string, $title );
			$page->doEditContent( $content, 'edit page' );
			$revisions[] = $page->getLatest();
		}

		$returnText = SemanticTasksMailer::generateDiffBodyTxt( $title );
		$this->assertNotEquals('', $returnText, 'Diff should not be empty string.');
	}

	/**
	 * @covers \ST\Assignees::saveAssignees
	 */
	public function testSaveAssignees() {
		$title = new Title();
		$article = new WikiPage( $title );
		$assignees = new Assignees();
		$assignees->saveAssignees( $article );
	}

	/** @todo: add more tests or asserts */
	/**
	 * @covers \ST\SemanticTasksMailer::mailAssigneesUpdatedTask
	 * @throws \MWException
	 */
	public function testMailAssigneesUpdatedTaskTrueOnMinorEdit() {
		$assignees = new Assignees();
		$title = new Title();
		$article = new WikiPage($title);
		$current_user = new User();
		$text = new TextContent('test TextContent');
		$summary = ''; // unused
		$minoredit = true; // or true;
		$watchthis = null; // unused
		$sectionanchor = null; // unused
		$flags = EDIT_NEW; // or other..
		try {
			$returnValue = SemanticTasksMailer::mailAssigneesUpdatedTask( $assignees, $article, $current_user, $text, $summary,
				$minoredit, $watchthis, $sectionanchor, $flags );
		} catch ( \MWException $e ) {

		} catch ( ComplexityException $e ) {

		}

		$this->assertTrue($returnValue);
	}
}

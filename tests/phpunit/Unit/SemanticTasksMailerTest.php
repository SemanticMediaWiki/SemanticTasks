<?php
namespace ST\Tests;

use CommentStoreComment;
use MediaWiki\Diff\ComplexityException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\RevisionSlotsUpdate;
use MWException;
use PHPUnit\Framework\TestResult;
use ST\Assignees;
use ST\SemanticTasksMailer;
use ST\UserMailer;
use TextContent;
use Title;
use User;
use WikiPage;

/**
 * @covers \ST\SemanticTasksMailer
 * @group semantic-tasks
 *
 * @license GPL-2.0-or-later
 * @since 3.0
 */
class SemanticTasksMailerTest extends \MediaWikiIntegrationTestCase {

	/**
	 * Only needed for MW 1.31
	 */
	public function run( ?TestResult $result = null ): TestResult {
		// MW 1.31
		$this->setCliArg( 'use-normal-tables', true );
		$testResult = parent::run( $result );
		return $testResult;
	}

	protected function overrideMwServices( $configOverrides = null, array $services = [] ) {
		/**
		 * `MediaWikiIntegrationTestCase` isolates the result with  `MediaWikiTestResult` which
		 * ecapsultes the commandline args and since we need to use "real" tables
		 * as part of "use-normal-tables" we otherwise end-up with the `CloneDatabase`
		 * to create TEMPORARY  TABLE by default as in:
		 *
		 * CREATE TEMPORARY  TABLE `unittest_smw_di_blob` (LIKE `smw_di_blob`) and
		 * because of the TEMPORARY TABLE, MySQL (not MariaDB) will complain
		 * about things like:
		 *
		 * SELECT p.smw_title AS prop, o_id AS id0, o0.smw_title AS v0, o0.smw_namespace
		 * AS v1, o0.smw_iw AS v2, o0.smw_sortkey AS v3, o0.smw_subobject AS v4,
		 * o0.smw_sort AS v5 FROM `unittest_smw_di_wikipage` INNER JOIN
		 * `unittest_smw_object_ids` AS p ON p_id=p.smw_id INNER JOIN
		 * `unittest_smw_object_ids` AS o0 ON o_id=o0.smw_id WHERE (s_id='29') AND
		 * (p.smw_iw!=':smw') AND (p.smw_iw!=':smw-delete')
		 *
		 * Function: SMW\SQLStore\EntityStore\SemanticDataLookup::fetchSemanticDataFromTable
		 * Error: 1137 Can't reopen table: 'p' ()
		 *
		 * The reason is that `unittest_smw_object_ids` was created as TEMPORARY TABLE
		 * and p is referencing to a TEMPORARY TABLE as well which isn't allowed in
		 * MySQL.
		 *
		 * "You cannot refer to a TEMPORARY table more than once in the same query" [0]
		 *
		 * [0] https://dev.mysql.com/doc/refman/8.0/en/temporary-table-problems.html
		 */
		// MW 1.32+
		$this->setCliArg( 'use-normal-tables', true );
		parent::overrideMwServices( $configOverrides, $services );
	}

	/** @todo: expand tests */

	/**
	 * @covers \ST\SemanticTasksMailer::mailNotification
	 * @throws ComplexityException
	 * @throws MWException
	 */
	public function testMailNotification() {
		$userMailerMock = $this->createMock( UserMailer::class );

		$assignees = [ 'someone' ];
		$text = '';
		$title = $this->createMock( Title::class );
		$user = new \User();
		$status = 0; // ST_NEWTASK

		$userMailerMock->expects( $this->once() )
			->method( 'send' )
			->with( $assignees, $this->anything(), $this->anything(), $this->anything(), $this->anything() )
			->willReturn( \Status::newGood() );

		SemanticTasksMailer::setUserMailer( $userMailerMock );
		SemanticTasksMailer::mailNotification( $assignees, $text, $title, $user, $status );
	}

	/**
	 * @covers \ST\SemanticTasksMailer::generateDiffBodyTxt
	 * @throws ComplexityException
	 * @throws MWException
	 */
	public function testGenerateDiffBodyTxt() {
		$namespace = $this->getDefaultWikitextNS();
		$title = Title::newFromText( 'Kitten', $namespace );

		$context = new \RequestContext();
		$context->setTitle( $title );

		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$strings = [ "it is a kitten", "two kittens", "three kittens", "four kittens" ];
		$revisions = [];
		foreach ( $strings as $string ) {
			$content = \ContentHandler::makeContent( $string, $title );

			$performer = $context->getUser();
			$summary = CommentStoreComment::newUnsavedComment( trim( 'edit page' ) );

			$slotsUpdate = new RevisionSlotsUpdate();
			$slotsUpdate->modifyContent( SlotRecord::MAIN, $content );

			$page->newPageUpdater( $performer, $slotsUpdate )
				->setContent( SlotRecord::MAIN, $content )
				->saveRevision( $summary );

			$revisions[] = $page->getLatest();
		}

		$returnText = SemanticTasksMailer::generateDiffBodyTxt( $title, $context );
		$this->assertNotEquals( '', $returnText, 'Diff should not be empty string.' );
	}

	/**
	 * @covers \ST\Assignees::saveAssignees
	 */
	public function testSaveAssignees() {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$article = new WikiPage( $title );
		$assignees = new Assignees();
		$assignees->saveAssignees( $article );
	}

	/** @todo: add more tests or asserts */

	/**
	 * @covers \ST\SemanticTasksMailer::mailAssigneesUpdatedTask
	 * @throws MWException
	 */
	public function testMailAssigneesUpdatedTaskTrueOnMinorEdit() {
		$assignees = new Assignees();
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$article = new WikiPage( $title );
		$current_user = new User();
		$text = new TextContent( 'test TextContent' );
		$summary = ''; // unused
		$minoredit = true; // or true;
		$watchthis = null; // unused
		$sectionanchor = null; // unused
		$flags = EDIT_NEW; // or other..
		$revision = null;
		try {
			$returnValue = SemanticTasksMailer::mailAssigneesUpdatedTask( $assignees, $article, $current_user, $text,
				$summary, $minoredit, $watchthis, $sectionanchor, $flags, $revision );
		} catch ( MWException $e ) {

		} catch ( ComplexityException $e ) {

		}

		$this->assertTrue( $returnValue );
	}

	public function testGetAssignedUsersFromParserOutput() {
		$namespace = $this->getDefaultWikitextNS();
		$title = Title::newFromText( 'Some Random Page', $namespace );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$content = \ContentHandler::makeContent( 'this is some edit', $title );
		$performer = \RequestContext::getMain()->getUser();
		$summary = CommentStoreComment::newUnsavedComment( trim( 'edit page' ) );
		$slotsUpdate = new RevisionSlotsUpdate();
		$slotsUpdate->modifyContent( SlotRecord::MAIN, $content );
		$page->newPageUpdater( $performer, $slotsUpdate )
			->setContent( SlotRecord::MAIN, $content )
			->saveRevision( $summary );

		$revisionRecord = $page->getRevisionRecord();
		$current_user = new User();
		$assignees = new Assignees();
		$assignendUsers = $assignees->getCurrentAssignees( $page, $revisionRecord );

		$this->assertEmpty( $assignendUsers );
	}

	/** @todo fix covers annotation and remove this. */
	public function testValidCovers() {
		$this->assertTrue( true );
	}
}

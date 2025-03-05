<?php

namespace ST;

use MailAddress;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

class UserMailer {

	private $userMailer;

	public function __construct( \UserMailer $userMailer ) {
		$this->userMailer = $userMailer;
	}

	/**
	 * @param $to
	 * @param $from
	 * @param $subject
	 * @param $body
	 * @param array $options
	 * @throws \MWException
	 */
	function send( $to, $from, $subject, $body, $options = [] ) {
		global $wgEnotifRevealEditorAddress;

		// @see User -> sendMail
		$passwordSender = MediaWikiServices::getInstance()->getMainConfig()
			->get( MainConfigNames::PasswordSender );

		$sender = new MailAddress( $passwordSender,
			wfMessage( 'emailsender' )->inContentLanguage()->text() );

		$options = [];
		if ( $wgEnotifRevealEditorAddress ) {
			$options['replyTo'] = $from;
		}

		$this->userMailer->send( $to, $sender, $subject, $body, $options );

		// *** the following may fail since $from is not the real
		// sender
		// $this->userMailer->send( $to, $from, $subject, $body, $options );
	}
}

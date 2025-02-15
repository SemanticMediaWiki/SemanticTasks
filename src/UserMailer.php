<?php

namespace ST;

use MailAddress;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

class UserMailer {

	private $userMailer;

	function __construct(\UserMailer $userMailer) {
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
		// @see User -> sendMail
		$passwordSender = MediaWikiServices::getInstance()->getMainConfig()
			->get( MainConfigNames::PasswordSender );

		$sender = new MailAddress( $passwordSender,
			wfMessage( 'emailsender' )->inContentLanguage()->text() );

		$this->userMailer->send( $to, $sender, $subject, $body, [
			'replyTo' => $from,
		] );

		// *** the following may fail since $from is not the real
		// sender
		// $this->userMailer->send( $to, $from, $subject, $body, $options );
	}
}

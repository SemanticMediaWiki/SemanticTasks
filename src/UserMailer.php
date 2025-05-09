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
	public function send( $to, $from, $subject, $body, $options = [] ) {
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

		// @attention !! @see UserMailer-> sendInternal
		// PEAR mailer will set 
		// $headers['To'] = 'undisclosed-recipients'
		// when the recipients are more than 1 !!
		// this may trigger antispam, leading to non delivery
		// of messages
		// send to the recipients one by one as a workaround
		$sent = [];
		foreach ( $to as $recipient ) {
			if ( !in_array( $recipient->address, $sent ) ) {
				$this->userMailer->send( $recipient, $sender, $subject, $body, $options );
				$sent[] = $recipient->address;
			}
		}
	}
}

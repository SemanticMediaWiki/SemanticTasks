<?php

namespace ST;

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
		$this->userMailer->send( $to, $from, $subject, $body, $options );
	}
}

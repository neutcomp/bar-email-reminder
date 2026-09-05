<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Mailer {
	public static function send( $reminder ) {
		$subject = sprintf(
			/* translators: %s: recipient name */
			__( 'Reminder for %s', 'bar-email-reminder' ),
			$reminder['name']
		);

		$message = sprintf(
			__( "Hello %1$s,\n\nThis is your reminder.\n\nCode: %2$s\nDate: %3$s\n\nRegards,", 'bar-email-reminder' ),
			$reminder['name'],
			$reminder['code'],
			$reminder['date']
		);

		return wp_mail( $reminder['email'], $subject, $message );
	}
}
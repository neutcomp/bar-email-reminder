<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Mailer {
	public static function send( $reminder ) {
		$subject = sprintf(
			/* translators: %s: recipient name */
			__( 'Bardienst reminder voor %s', 'bar-email-reminder' ),
			$reminder['name']
		);

		$message = sprintf(
			__( "Hallo %1$s,\n\nDit is je herinnering voor de bardienst bij The Victory.\n\nSleutel code: %2$s\nDatum bardienst: %3$s\n\nMet vriendelijke groet,\nThe Victory", 'bar-email-reminder' ),
			$reminder['name'],
			$reminder['code'],
			$reminder['date']
		);

		return wp_mail( BER_Reminder_Post_Type::get_emails( $reminder['email'] ), $subject, $message );
	}
}
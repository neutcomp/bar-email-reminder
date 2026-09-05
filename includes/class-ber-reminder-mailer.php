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
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $reminder['date'], wp_timezone() );
		$date = $date ? $date->format( 'd-m-Y' ) : $reminder['date'];

		$message = sprintf(
			__( "Hallo %s,\n\nDit is je herinnering voor de bardienst bij The Victory.\n\nSleutel code: %s\nDatum bardienst: %s\n\nMet vriendelijke groet,\nThe Victory", 'bar-email-reminder' ),
			$reminder['name'],
			$reminder['code'],
			$date
		);

		return wp_mail( BER_Reminder_Post_Type::get_emails( $reminder['email'] ), $subject, $message );
	}
}
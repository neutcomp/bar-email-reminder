<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Mailer {
	const SETTINGS_OPTION = 'ber_reminder_email_settings';

	public static function get_settings() {
		$defaults = array(
			'from_email' => get_option( 'admin_email' ),
			'subject'    => 'Bardienst reminder voor {name}',
			'message'    => "Hallo {name},\n\nDit is je herinnering voor de bardienst bij The Victory.\n\nDatum bardienst: {date}\n\nMet vriendelijke groet,\nThe Victory",
		);

		return wp_parse_args( get_option( self::SETTINGS_OPTION, array() ), $defaults );
	}

	public static function send( $reminder ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $reminder['date'], wp_timezone() );
		$date = $date ? $date->format( 'd-m-Y' ) : $reminder['date'];
		$settings = self::get_settings();
		$replacements = array(
			'{name}' => $reminder['name'],
			'{code}' => '',
			'{date}' => $date,
		);

		$subject = strtr( $settings['subject'], $replacements );
		$message = strtr( $settings['message'], $replacements );
		$headers = array(
			'From: ' . $settings['from_email'],
			'Content-Type: text/html; charset=UTF-8',
		);

		return wp_mail( self::get_recipients( $reminder ), $subject, $message, $headers );
	}

	public static function get_recipients( $reminder ) {
		if ( ! empty( $reminder['team_id'] ) && BER_Team_Post_Type::POST_TYPE === get_post_type( $reminder['team_id'] ) ) {
			return BER_Team_Post_Type::get_emails( $reminder['team_id'] );
		}

		return BER_Reminder_Post_Type::get_emails( $reminder['email'] );
	}
}
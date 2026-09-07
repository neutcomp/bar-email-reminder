<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Cron {
	const HOOK = 'ber_process_reminders';
	const LOCK = 'ber_process_reminders_lock';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'process' ) );
	}

	public static function add_schedule( $schedules ) {
		$schedules['ber_every_thirty_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'bar-email-reminder' ),
		);

		return $schedules;
	}

	public static function activate() {
		BER_Reminder_Post_Type::register();

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'ber_every_thirty_minutes', self::HOOK );
		}

		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
		delete_transient( self::LOCK );
		flush_rewrite_rules();
	}

	public static function process() {
		if ( get_transient( self::LOCK ) ) {
			return;
		}

		set_transient( self::LOCK, 1, 10 * MINUTE_IN_SECONDS );

		try {
			$today       = new DateTimeImmutable( 'now', wp_timezone() );
			$send_date   = $today->modify( '+2 days' )->format( 'Y-m-d' );
			$reminder_ids = get_posts(
				array(
					'post_type'      => BER_Reminder_Post_Type::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $reminder_ids as $reminder_id ) {
				$reminder = BER_Reminder_Post_Type::get( $reminder_id );

				if ( 'not-sent' !== $reminder['status'] || ! self::is_valid_date( $reminder['date'] ) || ! BER_Reminder_Mailer::get_recipients( $reminder ) ) {
					continue;
				}

				if ( $reminder['date'] < $send_date ) {
					update_post_meta( $reminder_id, BER_Reminder_Post_Type::STATUS_META, 'missed' );
					continue;
				}

				if ( $reminder['date'] !== $send_date ) {
					continue;
				}

				if ( BER_Reminder_Mailer::send( $reminder ) ) {
					update_post_meta( $reminder_id, BER_Reminder_Post_Type::STATUS_META, 'sent' );
				}
			}
		} finally {
			delete_transient( self::LOCK );
		}
	}

	private static function is_valid_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $date_object && $date_object->format( 'Y-m-d' ) === $date;
	}
}
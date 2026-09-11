<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NEUTCOMP_TER_Reminder_Cron {
	const HOOK = 'ter_process_reminders';
	const LOCK = 'ter_process_reminders_lock';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'process' ) );
	}

	public static function add_schedule( $schedules ) {
		$schedules['ter_every_thirty_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'team-email-reminder' ),
		);

		return $schedules;
	}

	public static function activate() {
		NEUTCOMP_TER_Reminder_Post_Type::register();

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'ter_every_thirty_minutes', self::HOOK );
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
			$settings    = NEUTCOMP_TER_Reminder_Mailer::get_settings();
			$send_date   = $today->modify( '+' . absint( $settings['reminder_days'] ) . ' days' )->format( 'Y-m-d' );
			$reminder_ids = get_posts(
				array(
					'post_type'      => NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $reminder_ids as $reminder_id ) {
				$reminder = NEUTCOMP_TER_Reminder_Post_Type::get( $reminder_id );

				if ( 'not-sent' !== $reminder['status'] || ! self::is_valid_date( $reminder['date'] ) || ! NEUTCOMP_TER_Reminder_Mailer::get_recipients( $reminder ) ) {
					continue;
				}

				if ( $reminder['date'] < $send_date ) {
					update_post_meta( $reminder_id, NEUTCOMP_TER_Reminder_Post_Type::STATUS_META, 'missed' );
					continue;
				}

				if ( $reminder['date'] !== $send_date ) {
					continue;
				}

				if ( NEUTCOMP_TER_Reminder_Mailer::send( $reminder ) ) {
					update_post_meta( $reminder_id, NEUTCOMP_TER_Reminder_Post_Type::STATUS_META, 'sent' );
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
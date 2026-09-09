<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TER_Reminder_Post_Type {
	const POST_TYPE = 'ter_reminder';

	const NAME_META   = '_ter_name';
	const EMAIL_META  = '_ter_email';
	const TEAM_META   = '_ter_team_id';
	const DATE_META   = '_ter_date';
	const STATUS_META = '_ter_status';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Reminders', 'team-email-reminder' ),
					'singular_name' => __( 'Reminder', 'team-email-reminder' ),
				),
				'public'            => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'supports'          => array(),
				'capability_type'   => 'post',
				'map_meta_cap'      => true,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	public static function get( $post_id ) {
		return array(
			'id'     => absint( $post_id ),
			'name'   => (string) get_post_meta( $post_id, self::NAME_META, true ),
			'team_id' => absint( get_post_meta( $post_id, self::TEAM_META, true ) ),
			'email'  => (string) get_post_meta( $post_id, self::EMAIL_META, true ),
			'date'   => (string) get_post_meta( $post_id, self::DATE_META, true ),
			'status' => self::get_status( $post_id ),
		);
	}

	public static function get_status( $post_id ) {
		$status = get_post_meta( $post_id, self::STATUS_META, true );

		return in_array( $status, array( 'not-sent', 'sent', 'missed' ), true ) ? $status : 'not-sent';
	}

	public static function save( $post_id, $fields ) {
		update_post_meta( $post_id, self::NAME_META, sanitize_text_field( $fields['name'] ) );
		update_post_meta( $post_id, self::TEAM_META, absint( $fields['team_id'] ) );
		update_post_meta( $post_id, self::DATE_META, sanitize_text_field( $fields['date'] ) );

		if ( isset( $fields['status'] ) ) {
			update_post_meta( $post_id, self::STATUS_META, sanitize_key( $fields['status'] ) );
		} elseif ( ! get_post_meta( $post_id, self::STATUS_META, true ) ) {
			update_post_meta( $post_id, self::STATUS_META, 'not-sent' );
		}
	}

	public static function get_emails( $value ) {
		$emails = array_map( 'trim', explode( ';', (string) $value ) );
		$emails = array_filter( $emails );
		$emails = array_map( 'sanitize_email', $emails );

		return array_values( array_filter( $emails, 'is_email' ) );
	}
}
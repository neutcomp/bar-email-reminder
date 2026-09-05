<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Post_Type {
	const POST_TYPE = 'ber_reminder';

	const NAME_META   = '_ber_name';
	const EMAIL_META  = '_ber_email';
	const CODE_META   = '_ber_code';
	const DATE_META   = '_ber_date';
	const STATUS_META = '_ber_status';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Reminders', 'bar-email-reminder' ),
					'singular_name' => __( 'Reminder', 'bar-email-reminder' ),
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
			'email'  => (string) get_post_meta( $post_id, self::EMAIL_META, true ),
			'code'   => (string) get_post_meta( $post_id, self::CODE_META, true ),
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
		update_post_meta( $post_id, self::EMAIL_META, sanitize_email( $fields['email'] ) );
		update_post_meta( $post_id, self::CODE_META, sanitize_text_field( $fields['code'] ) );
		update_post_meta( $post_id, self::DATE_META, sanitize_text_field( $fields['date'] ) );

		if ( isset( $fields['status'] ) ) {
			update_post_meta( $post_id, self::STATUS_META, sanitize_key( $fields['status'] ) );
		} elseif ( ! get_post_meta( $post_id, self::STATUS_META, true ) ) {
			update_post_meta( $post_id, self::STATUS_META, 'not-sent' );
		}
	}
}
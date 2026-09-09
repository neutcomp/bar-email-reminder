<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TER_Team_Post_Type {
	const POST_TYPE = 'ter_team';

	const NAME_META  = '_ter_team_name';
	const EMAIL_META = '_ter_team_email';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'           => array(
					'name'          => __( 'Teams', 'team-email-reminder' ),
					'singular_name' => __( 'Team', 'team-email-reminder' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_menu'    => false,
				'supports'        => array(),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	public static function get( $post_id ) {
		return array(
			'id'    => absint( $post_id ),
			'name'  => (string) get_post_meta( $post_id, self::NAME_META, true ),
			'email' => (string) get_post_meta( $post_id, self::EMAIL_META, true ),
		);
	}

	public static function get_all() {
		$team_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$teams = array();

		foreach ( $team_ids as $team_id ) {
			$teams[] = self::get( $team_id );
		}

		return $teams;
	}

	public static function get_emails( $team_id ) {
		$team = self::get( $team_id );

		return TER_Reminder_Post_Type::get_emails( $team['email'] );
	}

	public static function save( $post_id, $fields ) {
		$name   = sanitize_text_field( $fields['name'] );
		$emails = implode( ';', TER_Reminder_Post_Type::get_emails( $fields['email'] ) );

		wp_update_post( array( 'ID' => $post_id, 'post_title' => $name ) );
		update_post_meta( $post_id, self::NAME_META, $name );
		update_post_meta( $post_id, self::EMAIL_META, $emails );
	}
}
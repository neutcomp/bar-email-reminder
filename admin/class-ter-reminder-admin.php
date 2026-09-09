<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TER_Reminder_Admin {
	const PAGE = 'ter-reminders';
	const TEAMS_PAGE = 'ter-reminder-teams';
	const SETTINGS_PAGE = 'ter-reminder-settings';
	const CAPABILITY = 'edit_others_posts';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ter_save_reminder', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_ter_save_team', array( __CLASS__, 'save_team' ) );
		add_action( 'admin_post_ter_delete_reminder', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_ter_delete_team', array( __CLASS__, 'delete_team' ) );
		add_action( 'admin_post_ter_bulk_delete_reminders', array( __CLASS__, 'bulk_delete' ) );
		add_action( 'admin_post_ter_run_cron', array( __CLASS__, 'run_cron' ) );
		add_action( 'admin_post_ter_save_settings', array( __CLASS__, 'save_settings' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Team email reminders', 'team-email-reminder' ),
			__( 'Reminders', 'team-email-reminder' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-email-alt',
			25
		);
		add_submenu_page(
			self::PAGE,
			__( 'Teams', 'team-email-reminder' ),
			__( 'Teams', 'team-email-reminder' ),
			self::CAPABILITY,
			self::TEAMS_PAGE,
			array( __CLASS__, 'render_teams' )
		);
		add_submenu_page(
			self::PAGE,
			__( 'Email settings', 'team-email-reminder' ),
			__( 'Email settings', 'team-email-reminder' ),
			self::CAPABILITY,
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage reminders.', 'team-email-reminder' ) );
		}

		$edit_id = 0;
		if ( isset( $_GET['edit'] ) ) {
			check_admin_referer( 'ter_edit_reminder' );
			$edit_id = absint( $_GET['edit'] );
		}
		$editing  = $edit_id ? TER_Reminder_Post_Type::get( $edit_id ) : array(
			'id'     => 0,
			'name'   => '',
			'team_id' => 0,
			'date'   => '',
			'status' => 'not-sent',
		);
		$teams = TER_Team_Post_Type::get_all();
		$reminder_ids = get_posts(
			array(
				'post_type'      => TER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_key'       => TER_Reminder_Post_Type::DATE_META,
				'fields'         => 'ids',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Team email reminders', 'team-email-reminder' ); ?></h1>
			<?php self::notice(); ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ter_run_cron' ), 'ter_run_cron' ) ); ?>">
					<?php esc_html_e( 'Check reminders now', 'team-email-reminder' ); ?>
				</a>
			</p>
			<h2><?php echo $editing['id'] ? esc_html__( 'Edit reminder', 'team-email-reminder' ) : esc_html__( 'Add reminder', 'team-email-reminder' ); ?></h2>
			<form class="ter-reminder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ter_save_reminder">
				<input type="hidden" name="reminder_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
				<?php wp_nonce_field( 'ter_save_reminder' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="ter-name"><?php esc_html_e( 'Name', 'team-email-reminder' ); ?></label></th><td><input required class="regular-text" id="ter-name" name="name" value="<?php echo esc_attr( $editing['name'] ); ?>"></td></tr>
					<tr><th><label for="ter-team"><?php esc_html_e( 'Team', 'team-email-reminder' ); ?></label></th><td><select required class="regular-text" id="ter-team" name="team_id"><option value=""><?php esc_html_e( 'Select a team', 'team-email-reminder' ); ?></option><?php foreach ( $teams as $team ) : ?><option value="<?php echo esc_attr( $team['id'] ); ?>" <?php selected( $editing['team_id'], $team['id'] ); ?>><?php echo esc_html( $team['name'] ); ?></option><?php endforeach; ?></select><?php if ( ! $teams ) : ?><p class="description"><?php esc_html_e( 'Create a team first.', 'team-email-reminder' ); ?></p><?php endif; ?></td></tr>
					<tr><th><label for="ter-date"><?php esc_html_e( 'Date', 'team-email-reminder' ); ?></label></th><td><input required type="date" id="ter-date" name="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $editing['date'] ); ?>"></td></tr>
				</table>
				<?php submit_button( $editing['id'] ? __( 'Update reminder', 'team-email-reminder' ) : __( 'Add reminder', 'team-email-reminder' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Overview', 'team-email-reminder' ); ?></h2>
			<style>
				.ter-status-not-sent { color: #b32d2e; font-weight: 600; }
				.ter-status-sent { color: #008a20; font-weight: 600; }
				.ter-reminder-table .check-column { position: relative; vertical-align: middle !important; }
				.ter-reminder-table .check-column input[type="checkbox"] { position: absolute; top: 50%; left: 50%; margin: 0; transform: translate(-50%, -50%); }
				.ter-bulk-delete-submit { margin-top: 16px; }
				.ter-reminder-form #ter-name,
				.ter-reminder-form #ter-email,
				.ter-reminder-form #ter-date { box-sizing: border-box; height: 44px !important; min-height: 44px !important; }
			</style>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ter_bulk_delete_reminders">
				<?php wp_nonce_field( 'ter_bulk_delete_reminders' ); ?>
			<table class="widefat fixed striped ter-reminder-table">
				<thead><tr><th class="check-column"><input type="checkbox" aria-label="<?php esc_attr_e( 'Select all', 'team-email-reminder' ); ?>"></th><th><?php esc_html_e( 'Name', 'team-email-reminder' ); ?></th><th><?php esc_html_e( 'Team', 'team-email-reminder' ); ?></th><th><?php esc_html_e( 'Date', 'team-email-reminder' ); ?></th><th><?php esc_html_e( 'Status', 'team-email-reminder' ); ?></th><th><?php esc_html_e( 'Actions', 'team-email-reminder' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $reminder_ids ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No reminders found.', 'team-email-reminder' ); ?></td></tr>
				<?php else : foreach ( $reminder_ids as $reminder_id ) : $reminder = TER_Reminder_Post_Type::get( $reminder_id ); ?>
					<tr>
						<td class="check-column">
							<?php
							/* translators: %s: reminder name. */
							$select_label = sprintf( __( 'Select %s', 'team-email-reminder' ), $reminder['name'] );
							?>
							<input type="checkbox" name="reminder_ids[]" value="<?php echo esc_attr( $reminder_id ); ?>" aria-label="<?php echo esc_attr( $select_label ); ?>">
						</td><td><?php echo esc_html( $reminder['name'] ); ?></td><td><?php echo esc_html( self::get_team_name( $reminder['team_id'] ) ); ?></td><td><?php echo esc_html( self::format_date( $reminder['date'] ) ); ?></td><td><span class="ter-status-<?php echo esc_attr( $reminder['status'] ); ?>"><?php echo esc_html( self::get_status_label( $reminder['status'] ) ); ?></span></td>
						<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $reminder_id ), 'ter_edit_reminder' ) ); ?>"><?php esc_html_e( 'Edit', 'team-email-reminder' ); ?></a> | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ter_delete_reminder&reminder_id=' . $reminder_id ), 'ter_delete_reminder_' . $reminder_id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this reminder?', 'team-email-reminder' ) ); ?>');"><?php esc_html_e( 'Delete', 'team-email-reminder' ); ?></a></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
			<div class="ter-bulk-delete-submit">
				<?php submit_button( __( 'Delete selected reminders', 'team-email-reminder' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Delete the selected reminders?', 'team-email-reminder' ) ) . "');" ) ); ?>
			</div>
			</form>
		</div>
		<?php
	}

	public static function save() {
		self::check_access();
		check_admin_referer( 'ter_save_reminder' );

		$fields = array(
			'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'team_id' => isset( $_POST['team_id'] ) ? absint( $_POST['team_id'] ) : 0,
			'date'    => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
			'status' => 'not-sent',
		);
		$reminder_id = isset( $_POST['reminder_id'] ) ? absint( $_POST['reminder_id'] ) : 0;

		if ( ! $fields['name'] || ! self::is_valid_team( $fields['team_id'] ) || ! self::is_date( $fields['date'] ) || self::is_past_date( $fields['date'] ) ) {
			self::redirect( $reminder_id, 'error' );
		}

		$is_update = $reminder_id && TER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id );

		if ( $is_update ) {
			$post_id = $reminder_id;
		} else {
			$post_id = wp_insert_post( array( 'post_type' => TER_Reminder_Post_Type::POST_TYPE, 'post_status' => 'private', 'post_title' => $fields['name'] ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			self::redirect( 0, 'error' );
		}

		TER_Reminder_Post_Type::save( $post_id, $fields );
		self::redirect( $is_update ? $post_id : 0, 'saved' );
	}

	public static function render_teams() {
		self::check_access();
		$edit_id = 0;
		if ( isset( $_GET['edit'] ) ) {
			check_admin_referer( 'ter_edit_team' );
			$edit_id = absint( $_GET['edit'] );
		}
		$editing = $edit_id ? TER_Team_Post_Type::get( $edit_id ) : array( 'id' => 0, 'name' => '', 'email' => '' );
		$teams   = TER_Team_Post_Type::get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Teams', 'team-email-reminder' ); ?></h1>
			<?php self::notice(); ?>
			<h2><?php echo $editing['id'] ? esc_html__( 'Edit team', 'team-email-reminder' ) : esc_html__( 'Add team', 'team-email-reminder' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ter_save_team">
				<input type="hidden" name="team_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
				<?php wp_nonce_field( 'ter_save_team' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="ter-team-name"><?php esc_html_e( 'Name', 'team-email-reminder' ); ?></label></th><td><input required class="regular-text" id="ter-team-name" name="name" value="<?php echo esc_attr( $editing['name'] ); ?>"></td></tr>
					<tr><th><label for="ter-team-email"><?php esc_html_e( 'Email address', 'team-email-reminder' ); ?></label></th><td><input required type="text" class="regular-text" id="ter-team-email" name="email" value="<?php echo esc_attr( $editing['email'] ); ?>"><p class="description"><?php esc_html_e( 'Separate multiple email addresses with semicolons.', 'team-email-reminder' ); ?></p></td></tr>
				</table>
				<?php submit_button( $editing['id'] ? __( 'Update team', 'team-email-reminder' ) : __( 'Add team', 'team-email-reminder' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Overview', 'team-email-reminder' ); ?></h2>
			<table class="widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Name', 'team-email-reminder' ); ?></th><th><?php esc_html_e( 'Email address', 'team-email-reminder' ); ?></th><th><?php esc_html_e( 'Actions', 'team-email-reminder' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $teams ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No teams found.', 'team-email-reminder' ); ?></td></tr>
				<?php else : foreach ( $teams as $team ) : ?>
					<tr><td><?php echo esc_html( $team['name'] ); ?></td><td><?php echo esc_html( $team['email'] ); ?></td><td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::TEAMS_PAGE . '&edit=' . $team['id'] ), 'ter_edit_team' ) ); ?>"><?php esc_html_e( 'Edit', 'team-email-reminder' ); ?></a> | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ter_delete_team&team_id=' . $team['id'] ), 'ter_delete_team_' . $team['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this team?', 'team-email-reminder' ) ); ?>');"><?php esc_html_e( 'Delete', 'team-email-reminder' ); ?></a></td></tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function save_team() {
		self::check_access();
		check_admin_referer( 'ter_save_team' );

		$fields  = array(
			'name'  => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email' => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '',
		);
		$team_id = isset( $_POST['team_id'] ) ? absint( $_POST['team_id'] ) : 0;

		if ( ! $fields['name'] || ! TER_Reminder_Post_Type::get_emails( $fields['email'] ) ) {
			self::team_redirect( $team_id, 'error' );
		}

		$is_update = $team_id && TER_Team_Post_Type::POST_TYPE === get_post_type( $team_id );
		$post_id   = $is_update ? $team_id : wp_insert_post( array( 'post_type' => TER_Team_Post_Type::POST_TYPE, 'post_status' => 'private', 'post_title' => $fields['name'] ), true );

		if ( is_wp_error( $post_id ) ) {
			self::team_redirect( 0, 'error' );
		}

		TER_Team_Post_Type::save( $post_id, $fields );
		self::team_redirect( $is_update ? $post_id : 0, 'team-saved' );
	}

	public static function delete_team() {
		self::check_access();
		$team_id = isset( $_GET['team_id'] ) ? absint( $_GET['team_id'] ) : 0;
		check_admin_referer( 'ter_delete_team_' . $team_id );

		$linked_reminders = get_posts(
			array(
				'post_type'      => TER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => TER_Reminder_Post_Type::TEAM_META,
						'value' => $team_id,
					),
				),
			)
		);

		if ( $linked_reminders ) {
			self::team_redirect( 0, 'team-in-use' );
		}

		if ( TER_Team_Post_Type::POST_TYPE === get_post_type( $team_id ) ) {
			wp_delete_post( $team_id, true );
		}

		self::team_redirect( 0, 'team-deleted' );
	}

	public static function delete() {
		self::check_access();
		$reminder_id = isset( $_GET['reminder_id'] ) ? absint( $_GET['reminder_id'] ) : 0;
		check_admin_referer( 'ter_delete_reminder_' . $reminder_id );

		if ( TER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id ) ) {
			wp_delete_post( $reminder_id, true );
		}

		self::redirect( 0, 'deleted' );
	}

	public static function bulk_delete() {
		self::check_access();
		check_admin_referer( 'ter_bulk_delete_reminders' );

		$reminder_ids = isset( $_POST['reminder_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['reminder_ids'] ) ) : array();
		$deleted      = 0;

		foreach ( $reminder_ids as $reminder_id ) {
			if ( TER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id ) && wp_delete_post( $reminder_id, true ) ) {
				$deleted++;
			}
		}

		self::redirect( 0, $deleted ? 'bulk-deleted-' . $deleted : 'nothing-selected' );
	}

	public static function run_cron() {
		self::check_access();
		check_admin_referer( 'ter_run_cron' );
		TER_Reminder_Cron::process();
		self::redirect( 0, 'cron-run' );
	}

	public static function render_settings() {
		self::check_access();
		$settings = TER_Reminder_Mailer::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email settings', 'team-email-reminder' ); ?></h1>
			<?php self::notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ter_save_settings">
				<?php wp_nonce_field( 'ter_save_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ter-from-email"><?php esc_html_e( 'Sender email address', 'team-email-reminder' ); ?></label></th>
						<td><input required type="email" class="regular-text" id="ter-from-email" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="ter-email-subject"><?php esc_html_e( 'Email subject', 'team-email-reminder' ); ?></label></th>
						<td><input required class="large-text" id="ter-email-subject" name="subject" value="<?php echo esc_attr( $settings['subject'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="ter-email-message"><?php esc_html_e( 'Email message', 'team-email-reminder' ); ?></label></th>
						<td>
							<?php
							wp_editor(
								$settings['message'],
								'ter-email-message',
								array(
									'textarea_name' => 'message',
									'textarea_rows' => 12,
									'media_buttons' => false,
									'quicktags'     => true,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Available placeholders: {name}, {team}, and {date}.', 'team-email-reminder' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save email settings', 'team-email-reminder' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		self::check_access();
		check_admin_referer( 'ter_save_settings' );

		$from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
		$subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! is_email( $from_email ) || ! $subject || ! $message ) {
			self::settings_redirect( 'error' );
		}

		update_option(
			TER_Reminder_Mailer::SETTINGS_OPTION,
			array(
				'from_email' => $from_email,
				'subject'    => $subject,
				'message'    => $message,
			)
		);

		self::settings_redirect( 'settings-saved' );
	}

	private static function check_access() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage reminders.', 'team-email-reminder' ) );
		}
	}

	private static function redirect( $reminder_id, $message ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE . '&message=' . rawurlencode( $message ) );
		if ( $reminder_id ) {
			$url .= '&edit=' . absint( $reminder_id );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private static function settings_redirect( $message ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE . '&message=' . rawurlencode( $message ) ) );
		exit;
	}

	private static function team_redirect( $team_id, $message ) {
		$url = admin_url( 'admin.php?page=' . self::TEAMS_PAGE . '&message=' . rawurlencode( $message ) );
		if ( $team_id ) {
			$url .= '&edit=' . absint( $team_id );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private static function is_valid_team( $team_id ) {
		return $team_id && TER_Team_Post_Type::POST_TYPE === get_post_type( $team_id ) && TER_Team_Post_Type::get_emails( $team_id );
	}

	private static function get_team_name( $team_id ) {
		if ( ! $team_id || TER_Team_Post_Type::POST_TYPE !== get_post_type( $team_id ) ) {
			return __( 'Unknown team', 'team-email-reminder' );
		}

		return TER_Team_Post_Type::get( $team_id )['name'];
	}

	private static function is_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $date_object && $date_object->format( 'Y-m-d' ) === $date;
	}

	private static function is_past_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$today       = new DateTimeImmutable( 'today', wp_timezone() );

		return $date_object && $date_object < $today;
	}

	private static function format_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $date_object ? $date_object->format( 'd-m-Y' ) : $date;
	}

	private static function get_status_label( $status ) {
		$labels = array(
			'not-sent' => __( 'Not sent', 'team-email-reminder' ),
			'sent'     => __( 'Sent', 'team-email-reminder' ),
			'missed'   => __( 'Missed', 'team-email-reminder' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	private static function notice() {
		$message = filter_input( INPUT_GET, 'message', FILTER_DEFAULT );
		if ( empty( $message ) ) {
			return;
		}
		$messages = array( 'saved' => __( 'Reminder saved.', 'team-email-reminder' ), 'deleted' => __( 'Reminder deleted.', 'team-email-reminder' ), 'error' => __( 'Please check the fields.', 'team-email-reminder' ), 'cron-run' => __( 'Reminder check completed.', 'team-email-reminder' ), 'settings-saved' => __( 'Email settings saved.', 'team-email-reminder' ), 'team-saved' => __( 'Team saved.', 'team-email-reminder' ), 'team-deleted' => __( 'Team deleted.', 'team-email-reminder' ), 'team-in-use' => __( 'This team cannot be deleted because it is still linked to a reminder.', 'team-email-reminder' ) );
		$key      = sanitize_key( $message );
		if ( 0 === strpos( $key, 'bulk-deleted-' ) ) {
			$count = absint( substr( $key, strlen( 'bulk-deleted-' ) ) );
			/* translators: %d: numter of reminders deleted. */
			$deleted_message = sprintf( _n( '%d reminder deleted.', '%d reminders deleted.', $count, 'team-email-reminder' ), $count );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $deleted_message ) . '</p></div>';
			return;
		}
		if ( 'nothing-selected' === $key ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Select at least one reminder first.', 'team-email-reminder' ) . '</p></div>';
			return;
		}
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice ' . ( 'error' === $key ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
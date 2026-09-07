<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Admin {
	const PAGE = 'ber-reminders';
	const TEAMS_PAGE = 'ber-reminder-teams';
	const SETTINGS_PAGE = 'ber-reminder-settings';
	const CAPABILITY = 'edit_others_posts';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ber_save_reminder', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_ber_save_team', array( __CLASS__, 'save_team' ) );
		add_action( 'admin_post_ber_delete_reminder', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_ber_delete_team', array( __CLASS__, 'delete_team' ) );
		add_action( 'admin_post_ber_bulk_delete_reminders', array( __CLASS__, 'bulk_delete' ) );
		add_action( 'admin_post_ber_run_cron', array( __CLASS__, 'run_cron' ) );
		add_action( 'admin_post_ber_save_settings', array( __CLASS__, 'save_settings' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Bar e-mailherinneringen', 'bar-email-reminder' ),
			__( 'Herinneringen', 'bar-email-reminder' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-email-alt',
			25
		);
		add_submenu_page(
			self::PAGE,
			__( 'Teams', 'bar-email-reminder' ),
			__( 'Teams', 'bar-email-reminder' ),
			self::CAPABILITY,
			self::TEAMS_PAGE,
			array( __CLASS__, 'render_teams' )
		);
		add_submenu_page(
			self::PAGE,
			__( 'E-mailinstellingen', 'bar-email-reminder' ),
			__( 'E-mailinstellingen', 'bar-email-reminder' ),
			self::CAPABILITY,
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Je hebt geen toestemming om herinneringen te beheren.', 'bar-email-reminder' ) );
		}

		$edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing  = $edit_id ? BER_Reminder_Post_Type::get( $edit_id ) : array(
			'id'     => 0,
			'name'   => '',
			'team_id' => 0,
			'date'   => '',
			'status' => 'not-sent',
		);
		$teams = BER_Team_Post_Type::get_all();
		$reminder_ids = get_posts(
			array(
				'post_type'      => BER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_key'       => BER_Reminder_Post_Type::DATE_META,
				'fields'         => 'ids',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bar e-mailherinneringen', 'bar-email-reminder' ); ?></h1>
			<?php self::notice(); ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ber_run_cron' ), 'ber_run_cron' ) ); ?>">
					<?php esc_html_e( 'Herinneringen nu controleren', 'bar-email-reminder' ); ?>
				</a>
			</p>
			<h2><?php echo $editing['id'] ? esc_html__( 'Herinnering bewerken', 'bar-email-reminder' ) : esc_html__( 'Herinnering toevoegen', 'bar-email-reminder' ); ?></h2>
			<form class="ber-reminder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ber_save_reminder">
				<input type="hidden" name="reminder_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
				<?php wp_nonce_field( 'ber_save_reminder' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="ber-name">Naam</label></th><td><input required class="regular-text" id="ber-name" name="name" value="<?php echo esc_attr( $editing['name'] ); ?>"></td></tr>
					<tr><th><label for="ber-team">Team</label></th><td><select required class="regular-text" id="ber-team" name="team_id"><option value=""><?php esc_html_e( 'Selecteer een team', 'bar-email-reminder' ); ?></option><?php foreach ( $teams as $team ) : ?><option value="<?php echo esc_attr( $team['id'] ); ?>" <?php selected( $editing['team_id'], $team['id'] ); ?>><?php echo esc_html( $team['name'] ); ?></option><?php endforeach; ?></select><?php if ( ! $teams ) : ?><p class="description"><?php esc_html_e( 'Maak eerst een team aan.', 'bar-email-reminder' ); ?></p><?php endif; ?></td></tr>
					<tr><th><label for="ber-date">Datum</label></th><td><input required type="date" id="ber-date" name="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $editing['date'] ); ?>"></td></tr>
				</table>
				<?php submit_button( $editing['id'] ? __( 'Herinnering bijwerken', 'bar-email-reminder' ) : __( 'Herinnering toevoegen', 'bar-email-reminder' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Overzicht', 'bar-email-reminder' ); ?></h2>
			<style>
				.ber-status-not-sent { color: #b32d2e; font-weight: 600; }
				.ber-status-sent { color: #008a20; font-weight: 600; }
				.ber-reminder-table .check-column { position: relative; vertical-align: middle !important; }
				.ber-reminder-table .check-column input[type="checkbox"] { position: absolute; top: 50%; left: 50%; margin: 0; transform: translate(-50%, -50%); }
				.ber-bulk-delete-submit { margin-top: 16px; }
				.ber-reminder-form #ber-name,
				.ber-reminder-form #ber-email,
				.ber-reminder-form #ber-date { box-sizing: border-box; height: 44px !important; min-height: 44px !important; }
			</style>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ber_bulk_delete_reminders">
				<?php wp_nonce_field( 'ber_bulk_delete_reminders' ); ?>
			<table class="widefat fixed striped ber-reminder-table">
				<thead><tr><th class="check-column"><input type="checkbox" aria-label="Alles selecteren"></th><th>Naam</th><th>Team</th><th>Datum</th><th>Status</th><th><?php esc_html_e( 'Acties', 'bar-email-reminder' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $reminder_ids ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Geen herinneringen gevonden.', 'bar-email-reminder' ); ?></td></tr>
				<?php else : foreach ( $reminder_ids as $reminder_id ) : $reminder = BER_Reminder_Post_Type::get( $reminder_id ); ?>
					<tr>
						<td class="check-column"><input type="checkbox" name="reminder_ids[]" value="<?php echo esc_attr( $reminder_id ); ?>" aria-label="Selecteer <?php echo esc_attr( $reminder['name'] ); ?>"></td><td><?php echo esc_html( $reminder['name'] ); ?></td><td><?php echo esc_html( self::get_team_name( $reminder['team_id'] ) ); ?></td><td><?php echo esc_html( self::format_date( $reminder['date'] ) ); ?></td><td><span class="ber-status-<?php echo esc_attr( $reminder['status'] ); ?>"><?php echo esc_html( self::get_status_label( $reminder['status'] ) ); ?></span></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $reminder_id ) ); ?>">Bewerken</a> | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ber_delete_reminder&reminder_id=' . $reminder_id ), 'ber_delete_reminder_' . $reminder_id ) ); ?>" onclick="return confirm('Deze herinnering verwijderen?');">Verwijderen</a></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
			<div class="ber-bulk-delete-submit">
				<?php submit_button( __( 'Geselecteerde herinneringen verwijderen', 'bar-email-reminder' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('De geselecteerde herinneringen verwijderen?');" ) ); ?>
			</div>
			</form>
		</div>
		<?php
	}

	public static function save() {
		self::check_access();
		check_admin_referer( 'ber_save_reminder' );

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

		$is_update = $reminder_id && BER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id );

		if ( $is_update ) {
			$post_id = $reminder_id;
		} else {
			$post_id = wp_insert_post( array( 'post_type' => BER_Reminder_Post_Type::POST_TYPE, 'post_status' => 'private', 'post_title' => $fields['name'] ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			self::redirect( 0, 'error' );
		}

		BER_Reminder_Post_Type::save( $post_id, $fields );
		self::redirect( $is_update ? $post_id : 0, 'saved' );
	}

	public static function render_teams() {
		self::check_access();
		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing = $edit_id ? BER_Team_Post_Type::get( $edit_id ) : array( 'id' => 0, 'name' => '', 'email' => '' );
		$teams   = BER_Team_Post_Type::get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Teams', 'bar-email-reminder' ); ?></h1>
			<?php self::notice(); ?>
			<h2><?php echo $editing['id'] ? esc_html__( 'Team bewerken', 'bar-email-reminder' ) : esc_html__( 'Team toevoegen', 'bar-email-reminder' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ber_save_team">
				<input type="hidden" name="team_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
				<?php wp_nonce_field( 'ber_save_team' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="ber-team-name">Naam</label></th><td><input required class="regular-text" id="ber-team-name" name="name" value="<?php echo esc_attr( $editing['name'] ); ?>"></td></tr>
					<tr><th><label for="ber-team-email">E-mailadres</label></th><td><input required type="text" class="regular-text" id="ber-team-email" name="email" value="<?php echo esc_attr( $editing['email'] ); ?>"><p class="description"><?php esc_html_e( 'Scheid meerdere e-mailadressen met puntkomma\'s.', 'bar-email-reminder' ); ?></p></td></tr>
				</table>
				<?php submit_button( $editing['id'] ? __( 'Team bijwerken', 'bar-email-reminder' ) : __( 'Team toevoegen', 'bar-email-reminder' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Overzicht', 'bar-email-reminder' ); ?></h2>
			<table class="widefat fixed striped">
				<thead><tr><th>Naam</th><th>E-mailadres</th><th><?php esc_html_e( 'Acties', 'bar-email-reminder' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $teams ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'Geen teams gevonden.', 'bar-email-reminder' ); ?></td></tr>
				<?php else : foreach ( $teams as $team ) : ?>
					<tr><td><?php echo esc_html( $team['name'] ); ?></td><td><?php echo esc_html( $team['email'] ); ?></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::TEAMS_PAGE . '&edit=' . $team['id'] ) ); ?>">Bewerken</a> | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ber_delete_team&team_id=' . $team['id'] ), 'ber_delete_team_' . $team['id'] ) ); ?>" onclick="return confirm('Dit team verwijderen?');">Verwijderen</a></td></tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function save_team() {
		self::check_access();
		check_admin_referer( 'ber_save_team' );

		$fields  = array(
			'name'  => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email' => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '',
		);
		$team_id = isset( $_POST['team_id'] ) ? absint( $_POST['team_id'] ) : 0;

		if ( ! $fields['name'] || ! BER_Reminder_Post_Type::get_emails( $fields['email'] ) ) {
			self::team_redirect( $team_id, 'error' );
		}

		$is_update = $team_id && BER_Team_Post_Type::POST_TYPE === get_post_type( $team_id );
		$post_id   = $is_update ? $team_id : wp_insert_post( array( 'post_type' => BER_Team_Post_Type::POST_TYPE, 'post_status' => 'private', 'post_title' => $fields['name'] ), true );

		if ( is_wp_error( $post_id ) ) {
			self::team_redirect( 0, 'error' );
		}

		BER_Team_Post_Type::save( $post_id, $fields );
		self::team_redirect( $is_update ? $post_id : 0, 'team-saved' );
	}

	public static function delete_team() {
		self::check_access();
		$team_id = isset( $_GET['team_id'] ) ? absint( $_GET['team_id'] ) : 0;
		check_admin_referer( 'ber_delete_team_' . $team_id );

		$linked_reminders = get_posts(
			array(
				'post_type'      => BER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => BER_Reminder_Post_Type::TEAM_META,
						'value' => $team_id,
					),
				),
			)
		);

		if ( $linked_reminders ) {
			self::team_redirect( 0, 'team-in-use' );
		}

		if ( BER_Team_Post_Type::POST_TYPE === get_post_type( $team_id ) ) {
			wp_delete_post( $team_id, true );
		}

		self::team_redirect( 0, 'team-deleted' );
	}

	public static function delete() {
		self::check_access();
		$reminder_id = isset( $_GET['reminder_id'] ) ? absint( $_GET['reminder_id'] ) : 0;
		check_admin_referer( 'ber_delete_reminder_' . $reminder_id );

		if ( BER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id ) ) {
			wp_delete_post( $reminder_id, true );
		}

		self::redirect( 0, 'deleted' );
	}

	public static function bulk_delete() {
		self::check_access();
		check_admin_referer( 'ber_bulk_delete_reminders' );

		$reminder_ids = isset( $_POST['reminder_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['reminder_ids'] ) ) : array();
		$deleted      = 0;

		foreach ( $reminder_ids as $reminder_id ) {
			if ( BER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id ) && wp_delete_post( $reminder_id, true ) ) {
				$deleted++;
			}
		}

		self::redirect( 0, $deleted ? 'bulk-deleted-' . $deleted : 'nothing-selected' );
	}

	public static function run_cron() {
		self::check_access();
		check_admin_referer( 'ber_run_cron' );
		BER_Reminder_Cron::process();
		self::redirect( 0, 'cron-run' );
	}

	public static function render_settings() {
		self::check_access();
		$settings = BER_Reminder_Mailer::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'E-mailinstellingen', 'bar-email-reminder' ); ?></h1>
			<?php self::notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ber_save_settings">
				<?php wp_nonce_field( 'ber_save_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ber-from-email">Afzender e-mailadres</label></th>
						<td><input required type="email" class="regular-text" id="ber-from-email" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="ber-email-subject">Onderwerp van e-mail</label></th>
						<td><input required class="large-text" id="ber-email-subject" name="subject" value="<?php echo esc_attr( $settings['subject'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="ber-email-message">Bericht van e-mail</label></th>
						<td>
							<?php
							wp_editor(
								$settings['message'],
								'ber-email-message',
								array(
									'textarea_name' => 'message',
									'textarea_rows' => 12,
									'media_buttons' => false,
									'quicktags'     => true,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Beschikbare invulvelden: {name}, {team} en {date}.', 'bar-email-reminder' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'E-mailinstellingen opslaan', 'bar-email-reminder' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		self::check_access();
		check_admin_referer( 'ber_save_settings' );

		$from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
		$subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! is_email( $from_email ) || ! $subject || ! $message ) {
			self::settings_redirect( 'error' );
		}

		update_option(
			BER_Reminder_Mailer::SETTINGS_OPTION,
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
			wp_die( esc_html__( 'Je hebt geen toestemming om herinneringen te beheren.', 'bar-email-reminder' ) );
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
		return $team_id && BER_Team_Post_Type::POST_TYPE === get_post_type( $team_id ) && BER_Team_Post_Type::get_emails( $team_id );
	}

	private static function get_team_name( $team_id ) {
		if ( ! $team_id || BER_Team_Post_Type::POST_TYPE !== get_post_type( $team_id ) ) {
			return __( 'Onbekend team', 'bar-email-reminder' );
		}

		return BER_Team_Post_Type::get( $team_id )['name'];
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
			'not-sent' => 'Niet verzonden',
			'sent'     => 'Verzonden',
			'missed'   => 'Gemist',
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	private static function notice() {
		if ( empty( $_GET['message'] ) ) {
			return;
		}
		$messages = array( 'saved' => 'Herinnering opgeslagen.', 'deleted' => 'Herinnering verwijderd.', 'error' => 'Controleer de velden.', 'cron-run' => 'Controle van herinneringen voltooid.', 'settings-saved' => 'E-mailinstellingen opgeslagen.', 'team-saved' => 'Team opgeslagen.', 'team-deleted' => 'Team verwijderd.', 'team-in-use' => 'Dit team kan niet worden verwijderd omdat het nog aan een herinnering is gekoppeld.' );
		$key      = sanitize_key( wp_unslash( $_GET['message'] ) );
		if ( 0 === strpos( $key, 'bulk-deleted-' ) ) {
			$count = absint( substr( $key, strlen( 'bulk-deleted-' ) ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d herinnering verwijderd.', '%d herinneringen verwijderd.', $count, 'bar-email-reminder' ), $count ) ) . '</p></div>';
			return;
		}
		if ( 'nothing-selected' === $key ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Selecteer eerst minstens één herinnering.', 'bar-email-reminder' ) . '</p></div>';
			return;
		}
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice ' . ( 'error' === $key ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
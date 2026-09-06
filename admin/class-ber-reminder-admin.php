<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Admin {
	const PAGE = 'ber-reminders';
	const SETTINGS_PAGE = 'ber-reminder-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ber_save_reminder', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_ber_delete_reminder', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_ber_bulk_delete_reminders', array( __CLASS__, 'bulk_delete' ) );
		add_action( 'admin_post_ber_run_cron', array( __CLASS__, 'run_cron' ) );
		add_action( 'admin_post_ber_save_settings', array( __CLASS__, 'save_settings' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Bar e-mailherinneringen', 'bar-email-reminder' ),
			__( 'Herinneringen', 'bar-email-reminder' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-email-alt',
			25
		);
		add_submenu_page(
			self::PAGE,
			__( 'E-mailinstellingen', 'bar-email-reminder' ),
			__( 'E-mailinstellingen', 'bar-email-reminder' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Je hebt geen toestemming om herinneringen te beheren.', 'bar-email-reminder' ) );
		}

		$edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing  = $edit_id ? BER_Reminder_Post_Type::get( $edit_id ) : array(
			'id'     => 0,
			'name'   => '',
			'email'  => '',
			'code'   => '',
			'date'   => '',
			'status' => 'not-sent',
		);
		$reminder_ids = get_posts(
			array(
				'post_type'      => BER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
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
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ber_save_reminder">
				<input type="hidden" name="reminder_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
				<?php wp_nonce_field( 'ber_save_reminder' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="ber-name">Naam</label></th><td><input required class="regular-text" id="ber-name" name="name" value="<?php echo esc_attr( $editing['name'] ); ?>"></td></tr>
					<tr><th><label for="ber-email">E-mailadres</label></th><td><input required type="text" class="regular-text" id="ber-email" name="email" value="<?php echo esc_attr( $editing['email'] ); ?>"><p class="description"><?php esc_html_e( 'Scheid meerdere e-mailadressen met puntkomma\'s.', 'bar-email-reminder' ); ?></p></td></tr>
					<tr><th><label for="ber-code">Code</label></th><td><input required class="regular-text" id="ber-code" name="code" value="<?php echo esc_attr( $editing['code'] ); ?>"></td></tr>
					<tr><th><label for="ber-date">Datum</label></th><td><input required type="date" id="ber-date" name="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $editing['date'] ); ?>"></td></tr>
				</table>
				<?php submit_button( $editing['id'] ? __( 'Herinnering bijwerken', 'bar-email-reminder' ) : __( 'Herinnering toevoegen', 'bar-email-reminder' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Overzicht', 'bar-email-reminder' ); ?></h2>
			<style>
				.ber-status-not-sent { color: #b32d2e; font-weight: 600; }
				.ber-status-sent { color: #008a20; font-weight: 600; }
				.ber-reminder-table .check-column { vertical-align: middle; }
				.ber-bulk-delete-submit { margin-top: 16px; }
			</style>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ber_bulk_delete_reminders">
				<?php wp_nonce_field( 'ber_bulk_delete_reminders' ); ?>
			<table class="widefat fixed striped ber-reminder-table">
				<thead><tr><th class="check-column"><input type="checkbox" aria-label="Alles selecteren"></th><th>Naam</th><th>E-mailadres</th><th>Code</th><th>Datum</th><th>Status</th><th><?php esc_html_e( 'Acties', 'bar-email-reminder' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $reminder_ids ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Geen herinneringen gevonden.', 'bar-email-reminder' ); ?></td></tr>
				<?php else : foreach ( $reminder_ids as $reminder_id ) : $reminder = BER_Reminder_Post_Type::get( $reminder_id ); ?>
					<tr>
						<td class="check-column"><input type="checkbox" name="reminder_ids[]" value="<?php echo esc_attr( $reminder_id ); ?>" aria-label="Selecteer <?php echo esc_attr( $reminder['name'] ); ?>"></td><td><?php echo esc_html( $reminder['name'] ); ?></td><td><?php echo esc_html( $reminder['email'] ); ?></td><td><?php echo esc_html( $reminder['code'] ); ?></td><td><?php echo esc_html( $reminder['date'] ); ?></td><td><span class="ber-status-<?php echo esc_attr( $reminder['status'] ); ?>"><?php echo esc_html( self::get_status_label( $reminder['status'] ) ); ?></span></td>
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
			'name'  => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email' => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '',
			'code'  => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
			'date'  => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
		);
		$reminder_id = isset( $_POST['reminder_id'] ) ? absint( $_POST['reminder_id'] ) : 0;

		if ( ! $fields['name'] || ! BER_Reminder_Post_Type::get_emails( $fields['email'] ) || ! $fields['code'] || ! self::is_date( $fields['date'] ) || self::is_past_date( $fields['date'] ) ) {
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
							<textarea required class="large-text" rows="12" id="ber-email-message" name="message"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Beschikbare invulvelden: {name}, {code} en {date}.', 'bar-email-reminder' ); ?></p>
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
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

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
		if ( ! current_user_can( 'manage_options' ) ) {
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

	private static function is_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $date_object && $date_object->format( 'Y-m-d' ) === $date;
	}

	private static function is_past_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$today       = new DateTimeImmutable( 'today', wp_timezone() );

		return $date_object && $date_object < $today;
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
		$messages = array( 'saved' => 'Herinnering opgeslagen.', 'deleted' => 'Herinnering verwijderd.', 'error' => 'Controleer de velden van de herinnering.', 'cron-run' => 'Controle van herinneringen voltooid.', 'settings-saved' => 'E-mailinstellingen opgeslagen.' );
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
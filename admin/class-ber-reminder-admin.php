<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Admin {
	const PAGE = 'ber-reminders';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ber_save_reminder', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_ber_delete_reminder', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_ber_run_cron', array( __CLASS__, 'run_cron' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Bar Email Reminders', 'bar-email-reminder' ),
			__( 'Reminders', 'bar-email-reminder' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-email-alt',
			25
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage reminders.', 'bar-email-reminder' ) );
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
			<h1><?php esc_html_e( 'Bar Email Reminders', 'bar-email-reminder' ); ?></h1>
			<?php self::notice(); ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ber_run_cron' ), 'ber_run_cron' ) ); ?>">
					<?php esc_html_e( 'Run reminder check now', 'bar-email-reminder' ); ?>
				</a>
			</p>
			<h2><?php echo $editing['id'] ? esc_html__( 'Edit reminder', 'bar-email-reminder' ) : esc_html__( 'Add reminder', 'bar-email-reminder' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ber_save_reminder">
				<input type="hidden" name="reminder_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
				<?php wp_nonce_field( 'ber_save_reminder' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="ber-name">Name</label></th><td><input required class="regular-text" id="ber-name" name="name" value="<?php echo esc_attr( $editing['name'] ); ?>"></td></tr>
					<tr><th><label for="ber-email">Email</label></th><td><input required type="text" class="regular-text" id="ber-email" name="email" value="<?php echo esc_attr( $editing['email'] ); ?>"><p class="description"><?php esc_html_e( 'Separate multiple addresses with semicolons.', 'bar-email-reminder' ); ?></p></td></tr>
					<tr><th><label for="ber-code">Code</label></th><td><input required class="regular-text" id="ber-code" name="code" value="<?php echo esc_attr( $editing['code'] ); ?>"></td></tr>
					<tr><th><label for="ber-date">Date</label></th><td><input required type="date" id="ber-date" name="date" value="<?php echo esc_attr( $editing['date'] ); ?>"></td></tr>
				</table>
				<?php submit_button( $editing['id'] ? __( 'Update reminder', 'bar-email-reminder' ) : __( 'Add reminder', 'bar-email-reminder' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Overview', 'bar-email-reminder' ); ?></h2>
			<table class="widefat fixed striped">
				<thead><tr><th>Name</th><th>Email</th><th>Code</th><th>Date</th><th>Status</th><th><?php esc_html_e( 'Actions', 'bar-email-reminder' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $reminder_ids ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No reminders found.', 'bar-email-reminder' ); ?></td></tr>
				<?php else : foreach ( $reminder_ids as $reminder_id ) : $reminder = BER_Reminder_Post_Type::get( $reminder_id ); ?>
					<tr>
						<td><?php echo esc_html( $reminder['name'] ); ?></td><td><?php echo esc_html( $reminder['email'] ); ?></td><td><?php echo esc_html( $reminder['code'] ); ?></td><td><?php echo esc_html( $reminder['date'] ); ?></td><td><?php echo esc_html( $reminder['status'] ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $reminder_id ) ); ?>">Edit</a> | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ber_delete_reminder&reminder_id=' . $reminder_id ), 'ber_delete_reminder_' . $reminder_id ) ); ?>" onclick="return confirm('Delete this reminder?');">Delete</a></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
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

		if ( ! $fields['name'] || ! BER_Reminder_Post_Type::get_emails( $fields['email'] ) || ! $fields['code'] || ! self::is_date( $fields['date'] ) ) {
			self::redirect( $reminder_id, 'error' );
		}

		if ( $reminder_id && BER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id ) ) {
			$post_id = $reminder_id;
		} else {
			$post_id = wp_insert_post( array( 'post_type' => BER_Reminder_Post_Type::POST_TYPE, 'post_status' => 'private', 'post_title' => $fields['name'] ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			self::redirect( 0, 'error' );
		}

		BER_Reminder_Post_Type::save( $post_id, $fields );
		self::redirect( $post_id, 'saved' );
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

	public static function run_cron() {
		self::check_access();
		check_admin_referer( 'ber_run_cron' );
		BER_Reminder_Cron::process();
		self::redirect( 0, 'cron-run' );
	}

	private static function check_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage reminders.', 'bar-email-reminder' ) );
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

	private static function is_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $date_object && $date_object->format( 'Y-m-d' ) === $date;
	}

	private static function notice() {
		if ( empty( $_GET['message'] ) ) {
			return;
		}
		$messages = array( 'saved' => 'Reminder saved.', 'deleted' => 'Reminder deleted.', 'error' => 'Please check the reminder fields.', 'cron-run' => 'Reminder check completed.' );
		$key      = sanitize_key( wp_unslash( $_GET['message'] ) );
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice ' . ( 'error' === $key ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
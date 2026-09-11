<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NEUTCOMP_TER_Reminder_Admin {
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
		add_action( 'admin_post_ter_export_data', array( __CLASS__, 'export_data' ) );
		add_action( 'admin_post_ter_import_data', array( __CLASS__, 'import_data' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Team reminders for clubs', 'team-reminder-for-clubs' ),
			__( 'Reminders', 'team-reminder-for-clubs' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-email-alt',
			100
		);
		add_submenu_page(
			self::PAGE,
			__( 'Teams', 'team-reminder-for-clubs' ),
			__( 'Teams', 'team-reminder-for-clubs' ),
			self::CAPABILITY,
			self::TEAMS_PAGE,
			array( __CLASS__, 'render_teams' )
		);
		add_options_page(
			__( 'Team Reminder for Clubs', 'team-reminder-for-clubs' ),
			__( 'Team Reminder for Clubs', 'team-reminder-for-clubs' ),
			self::CAPABILITY,
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage reminders.', 'team-reminder-for-clubs' ) );
		}

		$edit_id = 0;
		if ( isset( $_GET['edit'] ) ) {
			check_admin_referer( 'ter_edit_reminder' );
			$edit_id = absint( $_GET['edit'] );
		}
		$editing  = $edit_id ? NEUTCOMP_TER_Reminder_Post_Type::get( $edit_id ) : array(
			'id'     => 0,
			'name'   => '',
			'team_id' => 0,
			'date'   => '',
			'status' => 'not-sent',
		);
		$teams = NEUTCOMP_TER_Team_Post_Type::get_all();
		$reminder_ids = get_posts(
			array(
				'post_type'      => NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_key'       => NEUTCOMP_TER_Reminder_Post_Type::DATE_META,
				'fields'         => 'ids',
			)
		);
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Team reminders for clubs', 'team-reminder-for-clubs' ); ?></h1>
	<?php self::notice(); ?>
	<p>
		<a class="button"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ter_run_cron' ), 'ter_run_cron' ) ); ?>">
			<?php esc_html_e( 'Check reminders now', 'team-reminder-for-clubs' ); ?>
		</a>
	</p>
	<h2>
		<?php echo $editing['id'] ? esc_html__( 'Edit reminder', 'team-reminder-for-clubs' ) : esc_html__( 'Add reminder', 'team-reminder-for-clubs' ); ?>
	</h2>
	<form class="ter-reminder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ter_save_reminder">
		<input type="hidden" name="reminder_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
		<?php wp_nonce_field( 'ter_save_reminder' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ter-name"><?php esc_html_e( 'Name', 'team-reminder-for-clubs' ); ?></label></th>
				<td><input required class="regular-text" id="ter-name" name="name"
						value="<?php echo esc_attr( $editing['name'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ter-team"><?php esc_html_e( 'Team', 'team-reminder-for-clubs' ); ?></label></th>
				<td><select required class="regular-text" id="ter-team" name="team_id">
						<option value=""><?php esc_html_e( 'Select a team', 'team-reminder-for-clubs' ); ?></option>
						<?php foreach ( $teams as $team ) : ?><option value="<?php echo esc_attr( $team['id'] ); ?>"
							<?php selected( $editing['team_id'], $team['id'] ); ?>><?php echo esc_html( $team['name'] ); ?></option>
						<?php endforeach; ?>
					</select><?php if ( ! $teams ) : ?><p class="description">
						<?php esc_html_e( 'Create a team first.', 'team-reminder-for-clubs' ); ?></p><?php endif; ?></td>
			</tr>
			<tr>
				<th><label for="ter-date"><?php esc_html_e( 'Date', 'team-reminder-for-clubs' ); ?></label></th>
				<td><input required type="date" id="ter-date" name="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"
						value="<?php echo esc_attr( $editing['date'] ); ?>"></td>
			</tr>
		</table>
		<?php submit_button( $editing['id'] ? __( 'Update reminder', 'team-reminder-for-clubs' ) : __( 'Add reminder', 'team-reminder-for-clubs' ) ); ?>
	</form>
	<hr>
	<h2><?php echo esc_html( sprintf( __( 'Overview (%d)', 'team-reminder-for-clubs' ), count( $reminder_ids ) ) ); ?></h2>
	<style>
	.ter-status-not-sent {
		color: #b32d2e;
		font-weight: 600;
	}

	.ter-status-sent {
		color: #008a20;
		font-weight: 600;
	}

	.ter-reminder-table .check-column {
		position: relative;
		vertical-align: middle !important;
	}

	.ter-reminder-table .check-column input[type="checkbox"] {
		position: absolute;
		top: 50%;
		left: 50%;
		margin: 0;
		transform: translate(-50%, -50%);
	}

	.ter-bulk-delete-submit {
		margin-top: 16px;
	}

	.ter-reminder-form #ter-name,
	.ter-reminder-form #ter-email,
	.ter-reminder-form #ter-date {
		box-sizing: border-box;
		height: 44px !important;
		min-height: 44px !important;
	}
	</style>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ter_bulk_delete_reminders">
		<?php wp_nonce_field( 'ter_bulk_delete_reminders' ); ?>
		<table class="widefat fixed striped ter-reminder-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox"
							aria-label="<?php esc_attr_e( 'Select all', 'team-reminder-for-clubs' ); ?>"></th>
					<th><?php esc_html_e( 'Name', 'team-reminder-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Team', 'team-reminder-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Date', 'team-reminder-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Status', 'team-reminder-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'team-reminder-for-clubs' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $reminder_ids ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No reminders found.', 'team-reminder-for-clubs' ); ?></td>
				</tr>
				<?php else : foreach ( $reminder_ids as $reminder_id ) : $reminder = NEUTCOMP_TER_Reminder_Post_Type::get( $reminder_id ); ?>
				<tr>
					<td class="check-column">
						<?php
							/* translators: %s: reminder name. */
							$select_label = sprintf( __( 'Select %s', 'team-reminder-for-clubs' ), $reminder['name'] );
							?>
						<input type="checkbox" name="reminder_ids[]" value="<?php echo esc_attr( $reminder_id ); ?>"
							aria-label="<?php echo esc_attr( $select_label ); ?>">
					</td>
					<td><?php echo esc_html( $reminder['name'] ); ?></td>
					<td><?php echo esc_html( self::get_team_name( $reminder['team_id'] ) ); ?></td>
					<td><?php echo esc_html( self::format_date( $reminder['date'] ) ); ?></td>
					<td><span
							class="ter-status-<?php echo esc_attr( $reminder['status'] ); ?>"><?php echo esc_html( self::get_status_label( $reminder['status'] ) ); ?></span>
					</td>
					<td><a
							href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE . '&edit=' . $reminder_id ), 'ter_edit_reminder' ) ); ?>"><?php esc_html_e( 'Edit', 'team-reminder-for-clubs' ); ?></a>
						| <a
							href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ter_delete_reminder&reminder_id=' . $reminder_id ), 'ter_delete_reminder_' . $reminder_id ) ); ?>"
							onclick="return confirm('<?php echo esc_js( __( 'Delete this reminder?', 'team-reminder-for-clubs' ) ); ?>');"><?php esc_html_e( 'Delete', 'team-reminder-for-clubs' ); ?></a>
					</td>
				</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
		<div class="ter-bulk-delete-submit">
			<?php submit_button( __( 'Delete selected reminders', 'team-reminder-for-clubs' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Delete the selected reminders?', 'team-reminder-for-clubs' ) ) . "');" ) ); ?>
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

		$is_update = $reminder_id && NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id );

		if ( $is_update ) {
			$post_id = $reminder_id;
		} else {
			$post_id = wp_insert_post( array( 'post_type' => NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE, 'post_status' => 'private', 'post_title' => $fields['name'] ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			self::redirect( 0, 'error' );
		}

		NEUTCOMP_TER_Reminder_Post_Type::save( $post_id, $fields );
		self::redirect( $is_update ? $post_id : 0, 'saved' );
	}

	public static function render_teams() {
		self::check_access();
		$edit_id = 0;
		if ( isset( $_GET['edit'] ) ) {
			check_admin_referer( 'ter_edit_team' );
			$edit_id = absint( $_GET['edit'] );
		}
		$editing = $edit_id ? NEUTCOMP_TER_Team_Post_Type::get( $edit_id ) : array( 'id' => 0, 'name' => '', 'email' => '' );
		$teams   = NEUTCOMP_TER_Team_Post_Type::get_all();
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Teams', 'team-reminder-for-clubs' ); ?></h1>
	<?php self::notice(); ?>
	<h2>
		<?php echo $editing['id'] ? esc_html__( 'Edit team', 'team-reminder-for-clubs' ) : esc_html__( 'Add team', 'team-reminder-for-clubs' ); ?>
	</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ter_save_team">
		<input type="hidden" name="team_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
		<?php wp_nonce_field( 'ter_save_team' ); ?>
		<style>
		#ter-team-name {
			box-sizing: border-box;
			height: 44px !important;
			min-height: 44px !important;
		}
		</style>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ter-team-name"><?php esc_html_e( 'Name', 'team-reminder-for-clubs' ); ?></label></th>
				<td><input required class="regular-text" id="ter-team-name" name="name"
						value="<?php echo esc_attr( $editing['name'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ter-team-email"><?php esc_html_e( 'Email address', 'team-reminder-for-clubs' ); ?></label></th>
				<td><input required type="text" class="regular-text" id="ter-team-email" name="email"
						value="<?php echo esc_attr( $editing['email'] ); ?>">
					<p class="description">
						<?php esc_html_e( 'Separate multiple email addresses with semicolons.', 'team-reminder-for-clubs' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( $editing['id'] ? __( 'Update team', 'team-reminder-for-clubs' ) : __( 'Add team', 'team-reminder-for-clubs' ) ); ?>
	</form>
	<hr>
	<h2><?php echo esc_html( sprintf( __( 'Overview (%d)', 'team-reminder-for-clubs' ), count( $teams ) ) ); ?></h2>
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'team-reminder-for-clubs' ); ?></th>
				<th><?php esc_html_e( 'Email address', 'team-reminder-for-clubs' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'team-reminder-for-clubs' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $teams ) : ?>
			<tr>
				<td colspan="3"><?php esc_html_e( 'No teams found.', 'team-reminder-for-clubs' ); ?></td>
			</tr>
			<?php else : foreach ( $teams as $team ) : ?>
			<tr>
				<td><?php echo esc_html( $team['name'] ); ?></td>
				<td><?php echo esc_html( $team['email'] ); ?></td>
				<td><a
						href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::TEAMS_PAGE . '&edit=' . $team['id'] ), 'ter_edit_team' ) ); ?>"><?php esc_html_e( 'Edit', 'team-reminder-for-clubs' ); ?></a>
					| <a
						href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ter_delete_team&team_id=' . $team['id'] ), 'ter_delete_team_' . $team['id'] ) ); ?>"
						onclick="return confirm('<?php echo esc_js( __( 'Delete this team?', 'team-reminder-for-clubs' ) ); ?>');"><?php esc_html_e( 'Delete', 'team-reminder-for-clubs' ); ?></a>
				</td>
			</tr>
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

		if ( ! $fields['name'] || ! NEUTCOMP_TER_Reminder_Post_Type::get_emails( $fields['email'] ) ) {
			self::team_redirect( $team_id, 'error' );
		}

		$is_update = $team_id && NEUTCOMP_TER_Team_Post_Type::POST_TYPE === get_post_type( $team_id );
		$post_id   = $is_update ? $team_id : wp_insert_post( array( 'post_type' => NEUTCOMP_TER_Team_Post_Type::POST_TYPE, 'post_status' => 'private', 'post_title' => $fields['name'] ), true );

		if ( is_wp_error( $post_id ) ) {
			self::team_redirect( 0, 'error' );
		}

		NEUTCOMP_TER_Team_Post_Type::save( $post_id, $fields );
		self::team_redirect( $is_update ? $post_id : 0, 'team-saved' );
	}

	public static function delete_team() {
		self::check_access();
		$team_id = isset( $_GET['team_id'] ) ? absint( $_GET['team_id'] ) : 0;
		check_admin_referer( 'ter_delete_team_' . $team_id );

		$linked_reminders = get_posts(
			array(
				'post_type'      => NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => NEUTCOMP_TER_Reminder_Post_Type::TEAM_META,
						'value' => $team_id,
					),
				),
			)
		);

		if ( $linked_reminders ) {
			self::team_redirect( 0, 'team-in-use' );
		}

		if ( NEUTCOMP_TER_Team_Post_Type::POST_TYPE === get_post_type( $team_id ) ) {
			wp_delete_post( $team_id, true );
		}

		self::team_redirect( 0, 'team-deleted' );
	}

	public static function delete() {
		self::check_access();
		$reminder_id = isset( $_GET['reminder_id'] ) ? absint( $_GET['reminder_id'] ) : 0;
		check_admin_referer( 'ter_delete_reminder_' . $reminder_id );

		if ( NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id ) ) {
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
			if ( NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE === get_post_type( $reminder_id ) && wp_delete_post( $reminder_id, true ) ) {
				$deleted++;
			}
		}

		self::redirect( 0, $deleted ? 'bulk-deleted-' . $deleted : 'nothing-selected' );
	}

	public static function run_cron() {
		self::check_access();
		check_admin_referer( 'ter_run_cron' );
		NEUTCOMP_TER_Reminder_Cron::process();
		self::redirect( 0, 'cron-run' );
	}

	public static function render_settings() {
		self::check_access();
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$settings = NEUTCOMP_TER_Reminder_Mailer::get_settings();
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Team Reminder for Clubs', 'team-reminder-for-clubs' ); ?></h1>
	<?php self::notice(); ?>
	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE . '&tab=settings' ) ); ?>"
			class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Email settings', 'team-reminder-for-clubs' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE . '&tab=import-export' ) ); ?>"
			class="nav-tab <?php echo 'import-export' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Import / Export', 'team-reminder-for-clubs' ); ?></a>
	</h2>
	<?php if ( 'import-export' === $tab ) : ?>
	<?php self::render_import_export_tab(); ?>
	<?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ter_save_settings">
		<?php wp_nonce_field( 'ter_save_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ter-reminder-days"><?php esc_html_e( 'Days before reminder', 'team-reminder-for-clubs' ); ?></label>
				</th>
				<td><input required type="number" min="0" step="1" class="small-text" id="ter-reminder-days"
						name="reminder_days" value="<?php echo esc_attr( $settings['reminder_days'] ); ?>">
					<p class="description">
						<?php esc_html_e( 'Number of calendar days before the reminder date when the email should be sent.', 'team-reminder-for-clubs' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="ter-from-email"><?php esc_html_e( 'Sender email address', 'team-reminder-for-clubs' ); ?></label>
				</th>
				<td><input required type="email" class="regular-text" id="ter-from-email" name="from_email"
						value="<?php echo esc_attr( $settings['from_email'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ter-email-subject"><?php esc_html_e( 'Email subject', 'team-reminder-for-clubs' ); ?></label></th>
				<td><input required class="large-text" id="ter-email-subject" name="subject"
						value="<?php echo esc_attr( $settings['subject'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ter-email-message"><?php esc_html_e( 'Email message', 'team-reminder-for-clubs' ); ?></label></th>
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
					<p class="description">
						<?php esc_html_e( 'Available placeholders: {name}, {team}, and {date}.', 'team-reminder-for-clubs' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save email settings', 'team-reminder-for-clubs' ) ); ?>
	</form>
	<?php endif; ?>
</div>
<?php
	}

	private static function render_import_export_tab() {
		?>
<div class="card">
	<h2><?php esc_html_e( 'Export', 'team-reminder-for-clubs' ); ?></h2>
	<p><?php esc_html_e( 'Export all teams and reminders to a CSV file.', 'team-reminder-for-clubs' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ter_export_data">
		<?php wp_nonce_field( 'ter_export_data' ); ?>
		<?php submit_button( __( 'Export CSV', 'team-reminder-for-clubs' ), 'primary', 'submit', false ); ?>
	</form>
</div>
<hr>
<div class="card">
	<h2><?php esc_html_e( 'Import', 'team-reminder-for-clubs' ); ?></h2>
	<p>
		<?php esc_html_e( 'Importing a CSV file will replace all existing teams and reminders.', 'team-reminder-for-clubs' ); ?>
	</p>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ter_import_data">
		<?php wp_nonce_field( 'ter_import_data' ); ?>
		<input type="file" name="import_file" accept=".csv,text/csv" required>
		<p class="description">
			<?php esc_html_e( 'Expected columns: type, name, team_name, email, date, status', 'team-reminder-for-clubs' ); ?></p>
		<?php submit_button( __( 'Import CSV', 'team-reminder-for-clubs' ), 'secondary', 'submit', false ); ?>
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
		$reminder_days_input = isset( $_POST['reminder_days'] ) && is_scalar( $_POST['reminder_days'] ) ? trim( wp_unslash( $_POST['reminder_days'] ) ) : '2';
		$reminder_days       = absint( $reminder_days_input );

		if ( ! is_email( $from_email ) || ! $subject || ! $message || ! preg_match( '/^\d+$/', $reminder_days_input ) ) {
			self::settings_redirect( 'error' );
		}

		update_option(
			NEUTCOMP_TER_Reminder_Mailer::SETTINGS_OPTION,
			array(
				'from_email'    => $from_email,
				'subject'       => $subject,
				'message'       => $message,
				'reminder_days' => $reminder_days,
			)
		);

		self::settings_redirect( 'settings-saved' );
	}

	public static function export_data() {
		self::check_access();
		check_admin_referer( 'ter_export_data' );

		$teams     = NEUTCOMP_TER_Team_Post_Type::get_all();
		$reminders = NEUTCOMP_TER_Reminder_Post_Type::get_all();
		$filename  = 'club-team-emailreminder-' . wp_date( 'd-m-Y' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'type', 'name', 'team_name', 'email', 'date', 'status' ) );

		foreach ( $teams as $team ) {
			fputcsv( $output, array( 'team', $team['name'], '', $team['email'], '', '' ) );
		}

		foreach ( $reminders as $reminder ) {
			$team_name = $reminder['team_id'] ? self::get_team_name( $reminder['team_id'] ) : '';
			fputcsv( $output, array( 'reminder', $reminder['name'], $team_name, '', $reminder['date'], $reminder['status'] ) );
		}

		fclose( $output );
		exit;
	}

	public static function import_data() {
		self::check_access();
		check_admin_referer( 'ter_import_data' );

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			self::settings_redirect( 'import-error', 'import-export' );
		}

		$source = $_FILES['import_file']['tmp_name'];
		if ( ! is_uploaded_file( $source ) ) {
			self::settings_redirect( 'import-error', 'import-export' );
		}

		$handle = fopen( $source, 'r' );
		if ( ! $handle ) {
			self::settings_redirect( 'import-error', 'import-export' );
		}

		$header = fgetcsv( $handle );
		if ( false === $header ) {
			fclose( $handle );
			self::settings_redirect( 'import-error', 'import-export' );
		}

		$header = array_map( 'trim', array_map( 'strtolower', $header ) );
		if ( ! in_array( 'type', $header, true ) || ! in_array( 'name', $header, true ) ) {
			fclose( $handle );
			self::settings_redirect( 'import-error', 'import-export' );
		}

		$team_map = array();
		$team_rows = array();
		$reminder_rows = array();

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( empty( array_filter( $row, static function ( $cell ) { return null !== $cell && '' !== (string) $cell; } ) ) ) {
				continue;
			}

			$data = array_combine( $header, array_pad( $row, count( $header ), '' ) );
			if ( ! $data || ! isset( $data['type'] ) ) {
				continue;
			}

			$type = strtolower( trim( (string) $data['type'] ) );
			if ( 'team' === $type ) {
				$team_rows[] = array(
					'name'  => trim( (string) $data['name'] ),
					'email' => trim( (string) ( $data['email'] ?? '' ) ),
				);
			} elseif ( 'reminder' === $type ) {
				$reminder_rows[] = array(
					'name'     => trim( (string) $data['name'] ),
					'team_name'=> trim( (string) ( $data['team_name'] ?? '' ) ),
					'date'     => trim( (string) ( $data['date'] ?? '' ) ),
					'status'   => trim( strtolower( (string) ( $data['status'] ?? '' ) ) ),
				);
			}
		}
		fclose( $handle );

		self::delete_all_data();

		foreach ( $team_rows as $team_row ) {
			if ( '' === $team_row['name'] ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => NEUTCOMP_TER_Team_Post_Type::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => $team_row['name'],
				),
				true
			);

			if ( ! is_wp_error( $post_id ) ) {
				NEUTCOMP_TER_Team_Post_Type::save( $post_id, array(
					'name'  => $team_row['name'],
					'email' => $team_row['email'],
				) );
				$team_map[ $team_row['name'] ] = $post_id;
			}
		}

		foreach ( $reminder_rows as $reminder_row ) {
			if ( '' === $reminder_row['name'] || '' === $reminder_row['date'] ) {
				continue;
			}

			$team_id = 0;
			if ( isset( $team_map[ $reminder_row['team_name'] ] ) ) {
				$team_id = $team_map[ $reminder_row['team_name'] ];
			}

			$status = in_array( $reminder_row['status'], array( 'not-sent', 'sent', 'missed' ), true ) ? $reminder_row['status'] : 'not-sent';
			$post_id = wp_insert_post(
				array(
					'post_type'   => NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => $reminder_row['name'],
				),
				true
			);

			if ( ! is_wp_error( $post_id ) ) {
				NEUTCOMP_TER_Reminder_Post_Type::save( $post_id, array(
					'name'    => $reminder_row['name'],
					'team_id' => $team_id,
					'date'    => $reminder_row['date'],
					'status'  => $status,
				) );
			}
		}

		self::settings_redirect( 'imported', 'import-export' );
	}

	private static function delete_all_data() {
		$team_ids = get_posts(
			array(
				'post_type'      => NEUTCOMP_TER_Team_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $team_ids as $team_id ) {
			wp_delete_post( $team_id, true );
		}

		$reminder_ids = get_posts(
			array(
				'post_type'      => NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $reminder_ids as $reminder_id ) {
			wp_delete_post( $reminder_id, true );
		}
	}

	private static function check_access() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage reminders.', 'team-reminder-for-clubs' ) );
		}
	}

	private static function redirect( $reminder_id, $message ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE . '&message=' . rawurlencode( $message ) );
		if ( $reminder_id ) {
			$url = add_query_arg(
				array(
					'edit'     => absint( $reminder_id ),
					'_wpnonce' => wp_create_nonce( 'ter_edit_reminder' ),
				),
				$url
			);
		}
		wp_safe_redirect( $url );
		exit;
	}

	private static function settings_redirect( $message, $tab = 'settings' ) {
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE . '&tab=' . rawurlencode( $tab ) . '&message=' . rawurlencode( $message ) ) );
		exit;
	}

	private static function team_redirect( $team_id, $message ) {
		$url = admin_url( 'admin.php?page=' . self::TEAMS_PAGE . '&message=' . rawurlencode( $message ) );
		if ( $team_id ) {
			$url = add_query_arg(
				array(
					'edit'     => absint( $team_id ),
					'_wpnonce' => wp_create_nonce( 'ter_edit_team' ),
				),
				$url
			);
		}
		wp_safe_redirect( $url );
		exit;
	}

	private static function is_valid_team( $team_id ) {
		return $team_id && NEUTCOMP_TER_Team_Post_Type::POST_TYPE === get_post_type( $team_id ) && NEUTCOMP_TER_Team_Post_Type::get_emails( $team_id );
	}

	private static function get_team_name( $team_id ) {
		if ( ! $team_id || NEUTCOMP_TER_Team_Post_Type::POST_TYPE !== get_post_type( $team_id ) ) {
			return __( 'Unknown team', 'team-reminder-for-clubs' );
		}

		return NEUTCOMP_TER_Team_Post_Type::get( $team_id )['name'];
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
			'not-sent' => __( 'Not sent', 'team-reminder-for-clubs' ),
			'sent'     => __( 'Sent', 'team-reminder-for-clubs' ),
			'missed'   => __( 'Missed', 'team-reminder-for-clubs' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	private static function notice() {
		$message = filter_input( INPUT_GET, 'message', FILTER_DEFAULT );
		if ( empty( $message ) ) {
			return;
		}
		$messages = array(
			'saved'          => __( 'Reminder saved.', 'team-reminder-for-clubs' ),
			'deleted'        => __( 'Reminder deleted.', 'team-reminder-for-clubs' ),
			'error'          => __( 'Please check the fields.', 'team-reminder-for-clubs' ),
			'cron-run'       => __( 'Reminder check completed.', 'team-reminder-for-clubs' ),
			'settings-saved' => __( 'Email settings saved.', 'team-reminder-for-clubs' ),
			'team-saved'     => __( 'Team saved.', 'team-reminder-for-clubs' ),
			'team-deleted'   => __( 'Team deleted.', 'team-reminder-for-clubs' ),
			'team-in-use'    => __( 'This team cannot be deleted because it is still linked to a reminder.', 'team-reminder-for-clubs' ),
			'imported'       => __( 'Teams and reminders imported successfully.', 'team-reminder-for-clubs' ),
			'import-error'   => __( 'The import file is invalid or empty.', 'team-reminder-for-clubs' ),
		);
		$key      = sanitize_key( $message );
		if ( 0 === strpos( $key, 'bulk-deleted-' ) ) {
			$count = absint( substr( $key, strlen( 'bulk-deleted-' ) ) );
			/* translators: %d: numter of reminders deleted. */
			$deleted_message = sprintf( _n( '%d reminder deleted.', '%d reminders deleted.', $count, 'team-reminder-for-clubs' ), $count );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $deleted_message ) . '</p></div>';
			return;
		}
		if ( 'nothing-selected' === $key ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Select at least one reminder first.', 'team-reminder-for-clubs' ) . '</p></div>';
			return;
		}
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice ' . ( 'error' === $key || 'import-error' === $key ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
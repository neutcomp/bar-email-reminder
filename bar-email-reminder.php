<?php
/**
 * Plugin Name: Bar Email Reminder
 * Description: Manage dated reminders and send notification emails two days before their date.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Bjorn van der Neut
 * Text Domain: bar-email-reminder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BER_VERSION', '1.0.0' );
define( 'BER_FILE', __FILE__ );
define( 'BER_PATH', plugin_dir_path( __FILE__ ) );

require_once BER_PATH . 'includes/class-ber-reminder-post-type.php';
require_once BER_PATH . 'includes/class-ber-reminder-mailer.php';
require_once BER_PATH . 'includes/class-ber-reminder-cron.php';
require_once BER_PATH . 'admin/class-ber-reminder-admin.php';

BER_Reminder_Post_Type::init();
BER_Reminder_Cron::init();
BER_Reminder_Admin::init();

register_activation_hook( BER_FILE, array( 'BER_Reminder_Cron', 'activate' ) );
register_deactivation_hook( BER_FILE, array( 'BER_Reminder_Cron', 'deactivate' ) );
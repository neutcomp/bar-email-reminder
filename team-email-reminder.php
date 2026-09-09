<?php
/**
 * Plugin Name: Team Email Reminder
 * Description: Manage dated reminders and send notification emails two days before their date.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * license: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Bjorn van der Neut
 * Text Domain: team-email-reminder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TER_VERSION', '1.0.0' );
define( 'TER_FILE', __FILE__ );
define( 'TER_PATH', plugin_dir_path( __FILE__ ) );

require_once TER_PATH . 'includes/class-ter-reminder-post-type.php';
require_once TER_PATH . 'includes/class-ter-team-post-type.php';
require_once TER_PATH . 'includes/class-ter-reminder-mailer.php';
require_once TER_PATH . 'includes/class-ter-reminder-cron.php';
require_once TER_PATH . 'includes/class-ter-reminder-shortcode.php';
require_once TER_PATH . 'admin/class-ter-reminder-admin.php';

TER_Reminder_Post_Type::init();
TER_Team_Post_Type::init();
TER_Reminder_Cron::init();
TER_Reminder_Shortcode::init();
TER_Reminder_Admin::init();

register_activation_hook( TER_FILE, array( 'TER_Reminder_Cron', 'activate' ) );
register_deactivation_hook( TER_FILE, array( 'TER_Reminder_Cron', 'deactivate' ) );
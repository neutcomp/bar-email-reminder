<?php
/**
 * Plugin Name: Team Reminder for Clubs
 * Description: Manage dated reminders and send notification emails two days before their date.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * license: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Bjorn van der Neut
 * Text Domain: team-reminder-for-clubs
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEUTCOMP_TER_VERSION', '1.0.0' );
define( 'NEUTCOMP_TER_FILE', __FILE__ );
define( 'NEUTCOMP_TER_PATH', plugin_dir_path( __FILE__ ) );

require_once NEUTCOMP_TER_PATH . 'includes/class-ter-reminder-post-type.php';
require_once NEUTCOMP_TER_PATH . 'includes/class-ter-team-post-type.php';
require_once NEUTCOMP_TER_PATH . 'includes/class-ter-reminder-mailer.php';
require_once NEUTCOMP_TER_PATH . 'includes/class-ter-reminder-cron.php';
require_once NEUTCOMP_TER_PATH . 'includes/class-ter-reminder-shortcode.php';
require_once NEUTCOMP_TER_PATH . 'admin/class-ter-reminder-admin.php';

NEUTCOMP_TER_Reminder_Post_Type::init();
NEUTCOMP_TER_Team_Post_Type::init();
NEUTCOMP_TER_Reminder_Cron::init();
NEUTCOMP_TER_Reminder_Shortcode::init();
NEUTCOMP_TER_Reminder_Admin::init();

register_activation_hook( NEUTCOMP_TER_FILE, array( 'NEUTCOMP_TER_Reminder_Cron', 'activate' ) );
register_deactivation_hook( NEUTCOMP_TER_FILE, array( 'NEUTCOMP_TER_Reminder_Cron', 'deactivate' ) );
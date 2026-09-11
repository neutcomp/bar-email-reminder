=== Team Reminder for Clubs ===
Contributors: neutcomp
Tags: reminders, email, scheduling, wp-cron, teams
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage dated reminders for teams and send notification emails a configurable number of days before each reminder date.

== Description ==

Team Reminder for Clubs helps administrators and editors manage dated team reminders and automatically notify the selected team by email.

Features include:

* Create, edit, and delete reminders.
* Assign each reminder to a team.
* Store one or more email addresses for each team.
* Send notifications a configurable number of calendar days before a reminder date.
* Process reminders automatically with WordPress Cron every 30 minutes.
* Run the reminder check manually from the WordPress administration area.
* Track reminders as not sent, sent, or missed.
* Delete multiple reminders at once.
* Customize the sender address, subject, email message, and notification delay.
* Use the `[neutcomp-schedule]` shortcode to display reminders in a public table.
* Display one or two date and team columns with the `split="true"` attribute.
* Display numeric dates with the `dateFormat="short"` attribute.
* Export all teams and reminders to CSV and import a generated file to replace the data set.
* Support English and Dutch based on the WordPress site language.

The email message supports the following placeholders:

* `{name}` - the reminder name.
* `{team}` - the selected team name.
* `{date}` - the reminder date in `dd-mm-yyyy` format.

The plugin uses the site's configured timezone for date validation, display, and scheduling. Email delivery uses WordPress `wp_mail()`, so the site must have a working mail configuration.

== Installation ==

1. Upload the `team-reminder-for-clubs` directory to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open **Settings > Team Reminder for Clubs** to manage email settings and CSV import/export.
4. Open **Reminders** and **Teams** to manage reminders and add at least one team with one or more valid email addresses.
5. Create a reminder and assign it to a team.
6. Optionally open the Email settings tab to customize the sender, subject, message, and notification delay.
7. Add `[neutcomp-schedule]` to a post or page to display the reminders publicly.

For reliable email delivery, configure WordPress with a suitable SMTP or transactional email provider.

== Import / Export ==

The plugin includes a CSV import/export feature under **Settings > Team Reminder for Clubs > Import / Export**.

* Export creates a file named `club-team-emailreminder-[dd-mm-yyyy].csv`.
* The export includes both teams and reminders.
* Import accepts the generated CSV and replaces all existing teams and reminders with the imported data.

This is useful for backup/restore or moving the plugin data between environments.

== Frequently Asked Questions ==

= When is an email sent? =

The plugin attempts to send an email the configured number of calendar days before the reminder date, using the WordPress site's timezone. Configure this under **Reminders > Email settings**.

= Why was a reminder marked as missed? =

A reminder is marked as missed when its send window has passed before the plugin can process it. The plugin does not send late notifications.

= How often does the plugin check reminders? =

WordPress Cron checks reminders every 30 minutes. WordPress Cron depends on site traffic unless it is triggered by a server scheduler.

= Can a team have multiple email addresses? =

Yes. Separate multiple email addresses with semicolons in the team email field.

= How do I change the email content? =

Open **Settings > Team Reminder for Clubs > Email settings** in the WordPress administration area. You can change the sender address, subject, and message. The message supports `{name}`, `{team}`, and `{date}` placeholders.

= How do I display reminders on a page? =

Add `[neutcomp-schedule]` to the page or post. Use `[neutcomp-schedule split="true"]` for two date and team pairs per row, or `[neutcomp-schedule dateFormat="short"]` for numeric dates.

= Which languages are supported? =

English and Dutch are included. The plugin follows the language configured for the WordPress site.

== Screenshots ==

![Reminder menu](https://ps.w.org/team-reminder-for-clubs/assets/reminder-menu.png)

The WordPress administration menu for Team Reminder for Clubs.

![Reminder list](https://ps.w.org/team-reminder-for-clubs/assets/reminder-list.png)

The reminder overview with reminder status and actions.

![Add reminder](https://ps.w.org/team-reminder-for-clubs/assets/reminder-add.png)

The form for adding or editing a reminder.

![Teams overview](https://ps.w.org/team-reminder-for-clubs/assets/teams-overview.png)

The teams overview.

![Add team](https://ps.w.org/team-reminder-for-clubs/assets/teams-add.png)

The form for adding or editing a team.

![Email settings](https://ps.w.org/team-reminder-for-clubs/assets/email-settings.png)

The email settings screen.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added reminder and team management.
* Added scheduled email notifications.
* Added customizable email settings.
* Added the `[neutcomp-schedule]` shortcode.
* Added CSV import/export for teams and reminders.
* Added English and Dutch translations.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

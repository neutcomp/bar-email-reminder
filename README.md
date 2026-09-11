# Team Reminder for Clubs

Team Reminder for Clubs is a small WordPress plugin for managing dated email reminders.

## Features

- Reminder overview with add, edit, and delete actions for Administrators and Editors.
- Supports selecting and deleting multiple reminders at once.
- Reminder fields: Name, Team, and Date.
- Teams have a Name and Email field. Multiple team email addresses can be separated with semicolons.
- Sends an email a configurable number of calendar days before Date.
- Uses the WordPress site's configured timezone.
- Checks reminders every 30 minutes through WP-Cron.
- Keeps failed deliveries as `not-sent` so they can be retried.
- Changes successful deliveries to `sent`.
- Changes reminders whose send window has passed to `missed`.
- Includes an administrator button to run the reminder check immediately.
- Includes a global WordPress Settings page named “Team Reminder for Clubs” for email configuration and CSV import/export.
- The email message uses the WordPress HTML editor and supports safe formatting such as bold text.
- Provides a `[neutcomp-schedule]` shortcode for displaying a public table with the date and name of each reminder.
- Supports CSV export of all teams and reminders, plus full replacement import from a generated file.
- Supports English and Dutch based on the WordPress site language.

The email template supports `{name}`, `{team}`, and `{date}` placeholders. The date placeholder is formatted as `dd-mm-yyyy`.

## Shortcode

Add `[neutcomp-schedule]` to a post or page. It displays reminders sorted by date with a Dutch date format such as `Donderdag 10 augustus`. Add `split="true"` to display two date/team pairs next to each other:

```text
[neutcomp-schedule split="true"]
```

Use `dateFormat="short"` for numeric dates in `dd-mm-yyyy` format. Parameters can be combined:

```text
[neutcomp-schedule split="true" dateFormat="short"]
```

## Installation

1. Copy this directory to `wp-content/plugins/team-reminder-for-clubs`.
2. Activate **Team Reminder for Clubs** from the WordPress Plugins screen.
3. Open the plugin menus in the WordPress administration area. Use **Settings > Team Reminder for Clubs** for email settings and CSV import/export.
4. Open **Reminders** and **Teams** to manage reminders and create the teams that can be selected on reminders.

The site's mail configuration must support `wp_mail()`. For reliable delivery, configure WordPress with a suitable SMTP or transactional mail provider.

## Import / Export

The plugin includes a CSV import/export feature under **Settings > Team Reminder for Clubs > Import / Export**.

- Export creates a file named `club-team-emailreminder-[dd-mm-yyyy].csv`.
- The export includes both teams and reminders.
- Import accepts the generated CSV and replaces all existing teams and reminders with the imported data.

This is useful for backup/restore or moving the plugin data between environments.

## Scheduling

The plugin registers a WP-Cron event with a 30-minute interval. WP-Cron runs when WordPress receives traffic, so the check is not guaranteed to run at an exact wall-clock time. Sites that need predictable processing should trigger `wp-cron.php` from a server scheduler and disable the default visitor-triggered cron according to their hosting setup.

On each run, the plugin compares the reminder Date with the current date in the WordPress timezone:

- Date is exactly the configured number of days ahead: attempt delivery.
- Date is earlier than the configured number of days ahead: mark `missed` without sending late.
- Date is more than the configured number of days ahead: leave as `not-sent`.
- `wp_mail()` returns `false`: leave as `not-sent` for the next check.

## Development checks

Run a PHP syntax check for every source file:

```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Test the plugin in a WordPress site with the timezone explicitly configured. Verify administrator permissions, CRUD actions, dates around month/year boundaries, successful and failed mail delivery, and repeated cron runs.
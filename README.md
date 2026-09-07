# Bar Email Reminder

Bar Email Reminder is a small WordPress plugin for managing dated email reminders.

## Features

- Administrator-only reminder overview with add, edit, and delete actions.
- Supports selecting and deleting multiple reminders at once.
- Reminder fields: Name, Team, and Date.
- Teams have a Name and Email field. Multiple team email addresses can be separated with semicolons.
- Sends a fixed email template exactly two calendar days before Date.
- Uses the WordPress site's configured timezone.
- Checks reminders every 30 minutes through WP-Cron.
- Keeps failed deliveries as `not-sent` so they can be retried.
- Changes successful deliveries to `sent`.
- Changes reminders whose send window has passed to `missed`.
- Includes an administrator button to run the reminder check immediately.
- Includes an Email Settings page where administrators can change the From address, subject, and message.
- The email message uses the WordPress HTML editor and supports safe formatting such as bold text.
- Provides a `[bardienst]` shortcode for displaying a public table with the date and name of each reminder.

The email template supports `{name}`, `{team}`, and `{date}` placeholders. The date placeholder is formatted as `dd-mm-yyyy`.

## Shortcode

Add `[bardienst]` to a post or page. It displays reminders sorted by date with a Dutch date format such as `Donderdag 10 augustus`. Add `split="true"` to display two date/team pairs next to each other:

```text
[bardienst split="true"]
```

Use `dateFormat="short"` for numeric dates in `dd-mm-yyyy` format. Parameters can be combined:

```text
[bardienst split="true" dateFormat="short"]
```

## Installation

1. Copy this directory to `wp-content/plugins/bar-email-reminder`.
2. Activate **Bar Email Reminder** from the WordPress Plugins screen.
3. Open **Reminders** in the WordPress administration menu.
4. Open **Teams** to create the teams that can be selected on reminders.

The site's mail configuration must support `wp_mail()`. For reliable delivery, configure WordPress with a suitable SMTP or transactional mail provider.

## Scheduling

The plugin registers a WP-Cron event with a 30-minute interval. WP-Cron runs when WordPress receives traffic, so the check is not guaranteed to run at an exact wall-clock time. Sites that need predictable processing should trigger `wp-cron.php` from a server scheduler and disable the default visitor-triggered cron according to their hosting setup.

On each run, the plugin compares the reminder Date with the current date in the WordPress timezone:

- Date is exactly two days ahead: attempt delivery.
- Date is earlier than two days ahead: mark `missed` without sending late.
- Date is more than two days ahead: leave as `not-sent`.
- `wp_mail()` returns `false`: leave as `not-sent` for the next check.

## Development checks

Run a PHP syntax check for every source file:

```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Test the plugin in a WordPress site with the timezone explicitly configured. Verify administrator permissions, CRUD actions, dates around month/year boundaries, successful and failed mail delivery, and repeated cron runs.
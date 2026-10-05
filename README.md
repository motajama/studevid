# Studevid

Studevid is a PHP/MySQL teacher and student consultation workspace. It includes regular consultation booking, ad-hoc consultation records, PhD diaries, publication tracking, presentation registration, e-learning administration, and teacher dashboards.

This directory is a sanitized distribution copy. It contains no production database dumps, passwords, private backups, or deployment credentials.

## Requirements

- PHP 8.1 or newer with PDO MySQL, mbstring and iconv
- MySQL 8 / MariaDB 10.5 or newer
- HTTPS in production
- A web server configured to keep `_private/` outside public access (the included rules help, but verify your server configuration)

## Installation

1. Create an empty database and a least-privilege database user.
2. Set `STUDEVID_DB_DSN`, `STUDEVID_DB_USER`, and `STUDEVID_DB_PASSWORD` in the web-server environment.
3. Import the SQL files in `sql/` into the selected database. `presentations_reset.sql` creates the presentation tables; the other files add optional modules.
4. Deploy this directory as `/studevid` (or update the absolute paths in the shared header and web server configuration).
5. Configure PHP sessions and HTTPS, then create teacher/student accounts using the application’s existing account workflow.

## Module migrations

- `consultation_scheduler.sql` – consultation slot activation
- `adhoc_consultations.sql` – ad-hoc students and consultations
- `diary_notes.sql` – private weekly diary notes
- `elearning_management.sql` – e-learning management tables
- `presentations_reset.sql` – presentation subjects, dates and registrations

Run migrations against a backup and review foreign-key dependencies before applying them to an existing installation.

## Security

Do not commit `.env`, database dumps, uploaded files, or production logs. Use HTTPS, secure session cookies, a restricted database account, and regular backups. See `SECURITY.md` for reporting vulnerabilities.

## Development

Run `php -l` on changed PHP files and test the affected module against a disposable database. The application uses server-rendered PHP and PDO prepared statements; no package manager is required for the core modules.

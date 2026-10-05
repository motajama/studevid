# Deployment

This distribution is designed to run as a standalone `/studevid` application. Keep `_private/` inaccessible to direct HTTP requests and provide database credentials through the `STUDEVID_DB_*` environment variables. Import the SQL migrations from `sql/` before first use. Do not copy production backups, service credentials, or private assets into this repository.

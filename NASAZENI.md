# Deployment

This distribution is designed to run as a standalone `/studevid` application. Keep `_private/` inaccessible to direct HTTP requests and provide database credentials through the `STUDEVID_DB_*` environment variables. Import the SQL migrations from `sql/` before first use. Do not copy production backups, service credentials, or private assets into this repository.

For booking links, set `STUDENTS_BOOKING_SECRET` to at least 32 characters or
allow PHP to create `_private/.booking-secret`. Preserve that file across
deployments. A read-only deployment can use `STUDENTS_BOOKING_SECRET_FILE` to
point to a writable private file instead.

# Database restore runbook

This runbook defines the recovery workflow for SQLite (local/testing) and MySQL (staging/production) environments. Follow it whenever you need to restore service after a data loss event. Capture timestamps, operators, and validation results for post-incident review.

## Roles and responsibilities
- **Incident commander** – Owns the incident channel, coordinates communication, and approves the restore window.
- **Database operator** – Executes the technical recovery steps for the target engine.
- **Application owner** – Validates functional checks and communicates completion to stakeholders.

## Global prerequisites
1. Confirm the incident commander has authorised the restore and notified affected stakeholders.
2. Ensure you have the latest validated backup artefacts:
   - Nightly snapshots stored in the backup bucket (MySQL).
   - Hourly copies created by automated jobs (SQLite).
3. Freeze deploys by disabling the GitHub Actions `deploy.yml` workflow or notifying the release manager.
4. Export the current `.env` file from the target environment in case connection strings or credentials are required during validation.

## SQLite recovery (development and single-node environments)
1. **Locate backup**
   - Identify the desired snapshot in the backup directory (`storage/backups/sqlite/`).
   - Verify checksum against the catalogue file `checksums.txt`.
2. **Restore**
   - Stop the Laravel queue workers and any scheduled jobs: `php artisan queue:restart` and `php artisan schedule:finish`.
   - Replace the active database: `cp storage/backups/sqlite/<timestamp>.sqlite database/database.sqlite`.
   - Apply file permissions: `chown www-data:www-data database/database.sqlite` (adjust user for your stack).
3. **Validation**
   - Run `php artisan migrate:status` to confirm schema alignment.
   - Execute smoke tests: `php artisan test --testsuite=Smoke` or the minimal API health check.
   - Review application logs for unexpected errors over the last 10 minutes.
4. **Handover**
   - Application owner validates business workflows and signs off.
   - Incident commander announces completion and closes the incident.

## MySQL recovery (staging and production)
1. **Locate backup**
   - Choose the restore point from the backup catalogue (AWS S3 bucket `caope-db-backups` or equivalent).
   - Validate integrity by running `mysqlbinlog --verify-binlog-checksum` for the incremental logs, when applicable.
2. **Prepare environment**
   - Place the application in maintenance mode: `php artisan down --render="errors::503-maintenance"`.
   - Ensure there is sufficient disk space in the target data directory.
   - Create a manual snapshot of the current database before overwriting (`mysqldump --single-transaction`). Store it for 30 days.
3. **Restore**
   - Import the full dump: `mysql -u <user> -p<password> <database> < full-backup.sql`.
   - Replay incremental binlogs up to the desired restore time: `mysqlbinlog --start-datetime="YYYY-MM-DD HH:MM:SS" binlog.* | mysql -u <user> -p<password> <database>`.
   - Rebuild indexes if the engine reports corruption: `mysqlcheck --auto-repair --optimize <database>`.
4. **Validation**
   - Run `SELECT COUNT(*)` sanity checks on critical tables (users, transactions, audit_logs) and compare with the incident report.
   - Execute Laravel migrations in dry-run to detect divergence: `php artisan migrate --pretend`.
   - Application owner performs end-to-end functional checks (authentication, payment flow, reporting dashboard).
   - Review monitoring dashboards for error rates, replication lag, and query latency.
5. **Handover**
   - Exit maintenance mode: `php artisan up`.
   - Incident commander records the restore time, data loss window, and outstanding remediation tasks in the post-incident document.
   - Update [`docs/security-checklist.md`](security-checklist.md) with the drill/incident date.

## Post-restore follow-up
- File a ticket for any automation gaps or manual pain points discovered during the process.
- Schedule a blameless post-incident review within three business days.
- Rotate credentials exposed during the incident (database users, backup storage keys).

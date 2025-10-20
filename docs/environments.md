# Environment Overview

This document summarizes the baseline requirements, data stores, and operational guidance for each CAOPE deployment environment. Secrets are **never** committed to the repository; store them in `.env` files that are excluded from version control or in the secret manager of your hosting provider.

## Local development

| Item | Details |
| --- | --- |
| Purpose | Day-to-day feature development and manual QA. |
| Runtime | PHP 8.2+, Composer, Node 18+ (or Docker, see below). |
| Database | SQLite file stored at `backend/database/database.sqlite`. |
| Storage | Local disk via Laravel's `storage/` directory. |
| Credentials | `.env` created from `.env.example`. API keys or SMTP credentials, if needed, live only in that file. |

### Key setup notes
- Ensure the SQLite database file exists: `touch backend/database/database.sqlite`.
- Set `DB_CONNECTION=sqlite` and `DB_DATABASE=${APP_PATH}/database/database.sqlite` (already scaffolded in `.env.example`).
- When using Docker, the provided `docker-compose.yml` runs Apache + PHP with SQLite mounted from the host so migrations and seeders work transparently.
- Asset compilation (`npm run dev`) can be run on the host machine or through `docker compose run --rm node npm run dev` if you add a Node container.

## Staging

| Item | Details |
| --- | --- |
| Purpose | Preview releases, stakeholder demos, QA prior to production. |
| Runtime | PHP 8.2+ containerized via `docker-compose.staging.yml`. |
| Database | MariaDB/MySQL (containerized). Configure credentials via environment variables or `.env.staging`. |
| Object storage | Optional MinIO (S3-compatible) service for file uploads. Disable the service if an external S3 bucket is provided. |
| Credentials | Store in `.env.staging` (excluded from Git) or in platform secrets (e.g., GitHub Actions, Render, Fly.io). |

### Provisioning template
- Copy `docker-compose.staging.yml` and customize port bindings, database credentials, and MinIO bucket names.
- Provide a `.env.staging` file with at least `APP_KEY`, `APP_URL`, `DB_*` variables, and (if MinIO is enabled) `FILESYSTEM_DISK=s3` plus MinIO-specific keys (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`).
- Protect the staging URL using basic auth (e.g., `htpasswd` with Nginx/Apache), VPN/IP allow lists, or an auth proxy. See "Staging publication" below.

### Deployment flow
1. Build frontend assets (`npm run build`) and commit the compiled files or serve them through Vite with `APP_ENV=staging`.
2. Deploy the stack: `docker compose -f docker-compose.staging.yml --env-file .env.staging up -d`.
3. Run migrations/seeds: `docker compose -f docker-compose.staging.yml exec app php artisan migrate --seed`.
4. Verify connectivity to database and MinIO (if enabled) before handing the URL to stakeholders.

## Production

| Item | Details |
| --- | --- |
| Purpose | Live system for CAOPE operations. |
| Runtime | Managed PHP hosting or container platform supporting PHP 8.2+. Ensure HTTPS termination. |
| Database | Managed MariaDB/MySQL (e.g., AWS RDS, Azure Database for MySQL, DigitalOcean Managed DB). |
| Object storage | External S3-compatible bucket (AWS S3, DigitalOcean Spaces, etc.). |
| Credentials | Managed secret store (AWS Secrets Manager, GCP Secret Manager, Vault) or encrypted `.env` deployment pipeline. |

### Production checklist
- ✅ **Database**: Provision a managed MariaDB/MySQL instance with automated backups enabled (daily minimum, with point-in-time recovery if available).
- ✅ **App key**: Generate a unique `APP_KEY` and store it in the hosting secret manager.
- ✅ **File storage**: Configure Laravel to use the managed S3 bucket and ensure lifecycle policies/backups exist for uploaded files.
- ✅ **HTTPS**: Terminate TLS using Let's Encrypt (via cert-manager, Traefik, or platform-managed certificates) or upload a purchased certificate.
- ✅ **Monitoring**: Enable application logging (e.g., Stackdriver, CloudWatch) and database metrics/alerts.
- ✅ **Disaster recovery**: Document restoration procedures for the database backups and object storage.

## Staging publication (protected access)

1. **Choose the protection method:**
   - Basic auth with `htpasswd` (Nginx/Apache).
   - Reverse proxy that supports OAuth/SAML.
   - VPN or IP allow list in front of the load balancer.
2. **Automate credential distribution:** Share staging credentials with the QA team via a secure channel (password manager or encrypted vault).
3. **Automate teardown:** On release, archive artifacts (DB dumps, storage exports) and stop containers using `docker compose down` to avoid stale environments.

## Managed credentials summary

| Secret | Local | Staging | Production |
| --- | --- | --- | --- |
| `APP_KEY` | `.env` (developer local copy) | `.env.staging` or secret manager | Secret manager / encrypted CI variable |
| DB username/password | Not required for SQLite | `.env.staging` or secret manager | Managed database secret store |
| S3/MinIO keys | Optional for local testing | In `.env.staging` if MinIO enabled | Managed secret store |
| Mail / third-party APIs | `.env` only when needed | `.env.staging` / secret manager | Secret manager + rotation policy |

Keep `.env*` files out of version control (`.gitignore`) and rotate credentials if they are exposed.

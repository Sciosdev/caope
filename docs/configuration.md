# Backend configuration

The Laravel backend ships with an `.env.example` file containing the variables expected by the application. Copy it before bootstrapping a new environment:

```
cp backend/.env.example backend/.env
```

After copying the file, review the inline comments to tailor the configuration for the target environment. In particular:

- **Application & database**: update `APP_URL` and database connection parameters when you are not using the default SQLite database.
- **Logging**: `LOG_CHANNEL` is configured to use the `daily` channel through the stack, rotating files automatically, and `LOG_LEVEL` defaults to `info`. Increase or decrease the level according to the environment's needs.
- **Caching & queues**: the application falls back to the database driver when no explicit driver is configured. Update the cache and queue sections if you use Redis, SQS, or another backend.
- **Mail & storage**: provide the SMTP credentials (`MAIL_*` variables) and the filesystem disk (`FILESYSTEM_DISK`) appropriate for the deployment.

Once the values are set you can continue with the standard Laravel bootstrap steps (installing dependencies, running migrations, etc.). Running `php artisan migrate` will create the cache, queue, and failed job tables required by the database drivers configured above.

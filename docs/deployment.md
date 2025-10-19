# Deployment and GitHub Actions secrets

This project ships with two GitHub Actions workflows:

- `ci.yml` validates the Laravel backend, lints Blade templates, and optionally builds the Vite assets.
- `deploy.yml` connects to the production server over SSH (cPanel compatible) and performs a `git pull` so the remote files stay in sync with `main`.

To keep both workflows functional you must configure a few secrets and prepare the destination server. This document summarises the requirements and the exact steps to follow.

## Required GitHub secrets

Create the following secrets from **Settings → Secrets and variables → Actions** in your GitHub repository:

| Secret | Description |
| --- | --- |
| `DEPLOY_HOST` | Public hostname or IP address of the server that hosts the application. |
| `DEPLOY_USER` | SSH user with permissions to run `git pull` and manage the Laravel project (often the cPanel user). |
| `DEPLOY_SSH_KEY` | Private SSH key (in PEM format) that authenticates the workflow. Store only the private key here. |
| `DEPLOY_PATH` | Absolute path of the project on the server (e.g. `/home/cpaneluser/domains/example.com/app`). |
| `DEPLOY_PORT` *(optional)* | Custom SSH port. Omit the secret to use the default port `22`. |
| `DEPLOY_POST_COMMANDS` *(optional)* | Additional commands to run after `git pull` (e.g. `composer install --no-dev && php artisan migrate --force`). |

The deployment workflow also honours the `ref` input when triggered manually. This allows you to deploy a feature branch or a tag by providing the exact ref name through the **Run workflow** dialog.

## Generating and uploading SSH keys

1. Generate a dedicated key pair for GitHub Actions from your local machine (do **not** reuse personal keys):

   ```bash
   ssh-keygen -t ed25519 -C "deploy@caope" -f ./caope_actions
   ```

2. Add the **public** key (`caope_actions.pub`) to the server:
   - For cPanel, use **SSH Access → Manage SSH Keys → Import Key** and authorise it.
   - On a standard Linux server, append the public key to the `~/.ssh/authorized_keys` file of the deployment user.

3. Add the **private** key to the `DEPLOY_SSH_KEY` secret, including the `-----BEGIN/END OPENSSH PRIVATE KEY-----` delimiters.

4. (Optional) Restrict the key on the server side to limit the commands it may run, or disable password logins for extra security.

## Server requirements

- Git must be installed and configured to access the same repository as GitHub Actions (HTTPS or SSH).
- The deployment user needs permissions to read and write within `DEPLOY_PATH`, including storage directories and the `.git` folder.
- PHP and Composer should already be available on the server if you run post-deployment commands such as database migrations.
- Ensure the `.env` on the server contains production-ready values (database, cache, queues, mail, etc.). The workflows never overwrite `.env`.

## Running the workflows

- **Continuous Integration (`ci.yml`)** runs automatically on every pull request and push to `main`. It installs Composer dependencies, runs `php artisan test --testsuite=Feature`, enforces Pint formatting, validates Blade templates with `php artisan blade:validate`, and builds Vite assets when `backend/package.json` is present.
- **Deployment (`deploy.yml`)** runs automatically on pushes to `main` and can be triggered manually. It performs an SSH login with the configured secrets, checks out the requested ref, pulls the latest code, and optionally executes your custom post-deploy commands.

With the secrets configured and the server prepared, deployments become a one-click action while the CI workflow keeps code quality under control.

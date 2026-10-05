# Deploy FYP to Vercel

This adaptation keeps the existing PHP pages and XAMPP defaults. It adds a single PHP function router, environment-based database configuration, and shared MySQL sessions. It has not been deployed or integration-tested against a live database.

## 1. Apply the patch in your existing repository

Save `vercel-deployment.patch` outside the repository, then run from the repository root:

```sh
git apply --check ../vercel-deployment.patch
git apply ../vercel-deployment.patch
```

If your download is elsewhere, replace that path. If the check fails, stop: your checkout may differ from the source revision. Do not overwrite your local changes.

## 2. Create an external MySQL database

For example, create a Free MySQL service at https://console.aiven.io/ (choose Free explicitly, not a paid plan). Wait for Running. Record the host, port, database, username, password and download the CA certificate from the connection settings.

Use a fresh demo database. In a MySQL client, connect using the service's Quick connect instructions with TLS certificate verification enabled. Choose the target database and run `database/cloud-import.sql`. This contains the original sample schema/data plus `app_sessions` and deliberately omits CREATE DATABASE / USE, so it works with the database name supplied by the provider.

If the project database already exists, import ONLY `database/vercel-sessions.sql`; do not import sample data into an existing database.

The session handler requires MySQL named locks (GET_LOCK / RELEASE_LOCK). Standard MySQL supports these; do not substitute a partially compatible database without checking. Keep connection counts in mind under load.

Example in the MySQL CLI after connecting and selecting the correct database:

```sql
SOURCE C:/path/to/FYP/database/cloud-import.sql;
SHOW TABLES;
```

## 3. Configure Vercel

Use the project connected to **ChAzharmehmood/FYP**. Confirm the Git repository under project Settings > Git; the existing project's name alone does not prove which repository is connected.

Settings > Build and Deployment:

| Setting | Value |
| --- | --- |
| Framework Preset | Other |
| Root Directory | Repository root (`./`), NOT `pms` |
| Build Command | Override with an empty value (no build step) |
| Output Directory | Leave override disabled |
| Install Command | Leave override disabled |
| Node.js | 22.x for the pinned community PHP runtime |

`vercel.json` selects `vercel-php@0.9.0` and routes all requests through `api/index.php`. Images are served by that router; configuration, SQL, vendor files and source files are not exposed as static assets. Application routes are explicitly allowlisted.

## 4. Add environment variables

Add these in Vercel Settings > Environment Variables for Production and Preview:

| Variable | Value |
| --- | --- |
| DB_HOST | Database provider hostname, never localhost |
| DB_PORT | Provider port |
| DB_NAME | Provider database name, for example defaultdb |
| DB_USER | Provider database username |
| DB_PASSWORD | Database password |
| DB_SSL | true |
| DB_SSL_CA_PEM | Entire CA certificate including BEGIN/END lines and actual line breaks |

Enter secrets directly into Vercel; do not commit them to Git or paste them into chat.

For duty email notifications also set `SMTP_DUTIES_USER` and `SMTP_DUTIES_PASS` (Gmail address and app password, as the existing code uses Gmail SMTP). The shared mail adapter additionally supports `SMTP_ALERTS_USER` and `SMTP_ALERTS_PASS`. Without SMTP settings, email delivery is not configured. The standalone test email script is not publicly routed.

## 5. Push and deploy

Review the changes, commit the changed/new files listed by `git status`, then push to your Vercel production branch. New deployments use the environment variables you saved. You can also select Deployments > latest deployment > Redeploy after settings changes.

## 6. Verify

- `/` opens staff login; `/admin_login.php` opens admin login.
- `/img/logo.jpg` displays the image.
- Login survives repeated requests and logout clears access.
- Verify each role's dashboard and representative database operations using sample data.
- Requests for `/pms/db_pms.sql`, `/config.php`, `/vendor/autoload.php`, `/api/sessions.php` and unknown paths must return 404.
- Check function logs for missing `app_sessions`, TLS errors, connection errors, or missing SMTP configuration.

## Existing application limitations

This is a deployment adaptation, not a security audit. Existing code compares plaintext passwords, an admin Remember Me flow stores a password in a cookie, and several endpoints lack authorization checks or interpolate SQL. Use synthetic demonstration data and deployment access protection until these are fixed. Do not use real police/personnel records.

The existing `pms/text.php` has a syntax error; it is an unused prototype and is deliberately not routed. `test.php`, config files, the internal alerts include and the standalone email test are also excluded from public routing.

Validation performed here: PHP static parsing for the deployable application and new helpers; JSON parsing; patch applicability and whitespace checks. PHP/MySQL execution and Vercel builds still need to be verified in the target environment.

References:
- https://github.com/vercel-community/php
- https://aiven.io/docs/products/mysql/get-started
- https://aiven.io/docs/products/mysql/concepts/mysql-free-tier

# Email setup and troubleshooting

Email is used for duty notifications and password-reset links. It is **off by default**; every feature works without it (duties are saved with `notify_status = failed` and can be re-sent later; password resets can be done by an admin from the Staff page).

## Configure (Gmail example)

1. In the Google account: enable 2-step verification, then create an **App Password** (Security → App passwords). Never use the normal account password.
2. In `pms/config.local.php`:

   ```php
   'mail_enabled'   => true,
   'smtp_host'      => 'smtp.gmail.com',
   'smtp_port'      => 587,
   'smtp_secure'    => 'tls',          // or 465 + 'ssl'
   'smtp_user'      => 'your.address@gmail.com',
   'smtp_pass'      => 'xxxx xxxx xxxx xxxx',   // the 16-character app password
   'mail_from'      => 'your.address@gmail.com',
   'mail_from_name' => 'Police Management System',
   ```

3. Test: assign a duty to a staff member who has an email address, then open the duty; the *Notification* box shows *Sent* or the error.

## Credentials previously committed to git

The original code base contained two Gmail app passwords in `send_email.php` and `assign_duties.php`. They were removed from the working tree, but **they remain in the repository history** and moving them into `config.local.php` does not revoke them. The owner of each Google account should delete those app passwords (Google Account → Security → App passwords) and create new ones. This project cannot verify that this has been done and does not claim it.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Email is not configured" on the duty page | `mail_enabled` is false or `smtp_user` empty in `config.local.php`. |
| "SMTP Error: Could not authenticate" (see `storage/logs/php-error.log`) | Wrong app password, 2-step verification not enabled, or the account blocks SMTP. |
| "SMTP connect() failed" | Port 587/465 blocked by a firewall or antivirus; try the other port/security pair. |
| Emails go to spam | Set `mail_from` to the same address as `smtp_user`. |
| Password-reset emails never arrive but the flow must be tested locally | With email disabled and `app_env = local`, each generated link is appended to `storage/logs/reset-links.log`. |
| "Recipient has no valid email address" | Add an email on the staff member's profile or edit page. |

Technical details of failures are written to `storage/logs/php-error.log`; users only see a short message.

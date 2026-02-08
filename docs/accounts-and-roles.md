# Accounts and Role Emails

This document defines the organizational email naming used for third-party services, alerts, and operations.

## Recommended role addresses

- `platform@propon.org`
  - Owner/admin identity for technical tools (hosting, DNS, Sentry, analytics, uptime, etc.)
  - Used rarely for high-privilege actions.
- `alerts@propon.org`
  - Alert destination alias/distribution list.
  - Should forward to at least one primary and one backup inbox.
- `security@propon.org`
  - Security contact address and destination for authentication/security alerts.
- `billing@propon.org`
  - Invoices, subscriptions, renewals, and payment communication.
- `noreply@propon.org`
  - Transactional sender identity for app emails.
- `contact@propon.org`
  - Public contact mailbox.
- `support@propon.org`
  - User support mailbox (can forward to `contact@...` initially).

## Operational model

- Keep two identities in third-party tools:
  - Owner identity: `platform@propon.org` (high privilege)
  - Daily operator identity: personal account with least privilege
- Do not tie critical tools to a single personal mailbox.

## Security baseline

- Enable 2FA on all role accounts and third-party tools.
- Store recovery codes in a password manager.
- Rotate API tokens/passwords periodically and on role changes.

## App configuration

The following role emails are configured in `config/services.yaml`:

- `app.email.platform`
- `app.email.alerts`
- `app.email.security`
- `app.email.billing`
- `app.email.noreply`
- `app.email.contact`
- `app.email.support`

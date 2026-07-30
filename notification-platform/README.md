# Alphil Notification Platform

Self-hosted, multi-tenant Web Push and email notifications for Zambia Job Alerts, Prime Job Alerts, Prime Scholarship Alerts, and ZinstaBlog.

## Architecture

- The Express API accepts browser subscriptions, email subscriptions, signed site webhooks, tracking events, and admin actions.
- The MySQL database keeps tenants, subscriptions, campaigns, deliveries, retry state, and delivery events separate by tenant.
- A dedicated worker schedules campaigns and digests, matches subscriber filters, sends each delivery independently, retries transient failures with exponential backoff, and expires HTTP 404/410 push endpoints.
- Every website owns its root-scoped `/custom-push-sw.js`; the shared `public/client.js` handles permission, registration, filters, unsubscribe, and migration from Webpushr VAPID subscriptions.
- The Laravel and WordPress integrations publish content through per-site webhook secrets. Existing mobile app notifications are not part of this service.

## Production locations

- Application: `/opt/notification-platform`
- Environment: `/etc/notification-platform.env` (mode 0600)
- Tenant webhook secrets: `/etc/notification-tenant-secrets.json` (mode 0600)
- Admin credentials: `/root/notification-platform-admin.txt` (mode 0600)
- API and dashboard: `https://api.alphilnetworks.com/notifications`
- Services: `notification-platform.service` and `notification-platform-worker.service`

Never commit any of the production files in `/etc` or `/root`.

## Local checks

1. Copy `.env.example` to `.env` and provide local database, SMTP, and security values.
2. Run `npm ci`.
3. Run `npm run migrate` and `npm run seed`.
4. Run `npm test`.
5. Start the API with `npm start` and the worker with `npm run worker`.

The production-only setup utility will not overwrite an existing `/etc/notification-platform.env`; it intentionally avoids rotating active credentials during a redeployment.

## Operations

- Health: `GET /notifications/health`
- Admin: `/notifications/admin` with HTTP Basic authentication
- Logs: `journalctl -u notification-platform -u notification-platform-worker`
- Test email: `npm run test-email -- <site-key> <email>`
- Failure isolation: `npm run test-isolation -- <site-key> <email>`

Valid site keys are `zambia-job-alerts`, `prime-job-alerts`, `prime-scholarship-alerts`, and `zinstablog`.
## Security and reliability

- HTTPS-only public endpoints and strict origin allowlists
- Per-tenant VAPID key pairs and webhook secrets
- Rate limiting, request validation, security headers, IP hashing, CSRF protection, and authenticated admin access
- Individual delivery records prevent one failed recipient or channel from stopping other deliveries
- Four attempts with exponential backoff for transient failures
- Immediate retirement of HTTP 404/410 push endpoints
- Idempotent content events prevent duplicate campaigns
- Email verification and signed unsubscribe, open, and click links

# Website integration snapshots

These directories mirror the production-relative files used to connect each site to the shared notification platform. They are versioned here because the three Laravel production directories and the active WordPress installation do not share the scraper repository.

- `prime-job-alerts`: preserves its existing filtered Laravel email alerts and sends browser campaigns in an independent queued job.
- `prime-scholarship-alerts`: preserves its existing scholarship email alerts and sends browser campaigns independently.
- `zinstablog`: sends matching push and central email campaigns for newly published jobs and posts without changing its mobile notification outbox.
- `zambia-job-alerts`: provides the custom WordPress plugin, subscription panel, cron-backed publish retry, central dashboard link, and root service worker.

Production secrets are intentionally absent. Laravel reads them from `NOTIFICATION_PLATFORM_*` environment values and WordPress reads `CUSTOM_NOTIFICATIONS_KEY` from `wp-config.php`.

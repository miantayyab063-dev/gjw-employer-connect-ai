# GJW v4 Bug Audit (static review)

## Corrected
- HIGH: Concurrent sending jobs could independently read the daily quota and exceed it. MySQL advisory lock now serializes batches.
- HIGH: Permission approval/sending did not require nonempty permission basis consistently. Both gates now require it.
- MEDIUM: Arbitrary validation status was accepted from a forged form. Now strictly allowlisted.
- LOW: Extra closing HTML form in proposals page removed.

## Outstanding / not production-ready
- Search results are not verified employer identities and may include irrelevant domains.
- Email validation is manual; there is no external verification integration.
- Delivery webhooks, inbound reply sync, PDF attachments, multi-user roles and secure install wizard are not implemented.
- Auth rate limiting, secret management, privacy review and production deployment testing remain necessary.
- Existing approved proposals may be stale after contact permission changes: sender rechecks eligibility and suppression at send time.
- Sending status `failed` is used as an in-progress claim; operationally this should be migrated to a dedicated `processing` status in a later database migration.
- MySQL/PHP live integration tests were not run.

## Deployment caution
Back up database and files. Deploy to staging first. Do not enable cron sending until provider approval and live end-to-end tests.

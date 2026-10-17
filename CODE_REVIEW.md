# Code Review - AI WhatsApp Bot Builder

This document records what the review found, what was fixed, and what is still a known limitation.

## 1. State before this pass

The first version was never run. When it was finally run against MariaDB and PHP 8.3, it turned out to be largely non-functional:

| Problem | Effect |
|---|---|
| About 45 of the ~50 views referenced by controllers did not exist | Every page except the dashboard and bot list returned 500 "View not found" |
| `Model::create()` always inserted `updated_at`, but `messages`, `broadcast_messages` and `webhook_logs` have no such column | **Every message insert failed**, so the send-message API, webhook receiver, agent replies and broadcasts were all broken |
| Contact search passed `search` as a column filter | `/contacts?search=` crashed (SQL error) |
| Seed API keys were fake bcrypt strings | The public API could not be used with the demo data |
| `ask_question` saved `_awaiting_input` but nothing read it; `call_webhook`, `branch_condition`, `menu` and `button` were no-ops | Flows could not collect data or branch |
| No quota enforcement, outgoing webhooks were never fired, broadcasts never executed, uploads were never used, email notifications were absent | Large parts of the specification were missing |

Security problems in that version:

- **Cross-tenant writes:** agent replies, flows, steps, bots (channel_id), KB entries, broadcasts and webhooks accepted foreign IDs from other tenants.
- **Privilege escalation:** a tenant admin could create or promote users to `platform_admin`. Any role could change the plan or pay invoices.
- **Fail-open models:** a platform admin (tenant NULL) on tenant pages saw everyone's data, and models without `tenant_id` silently ignored the tenant filter.
- **No real CSRF on login/registration:** the form field was rendered but never checked.
- **Logout via GET:** `session_destroy()` also wiped the flash messages.

## 2. What was changed

### Foundation
- `app/bootstrap.php`: one bootstrap (env, UTC, autoloader) shared by web, cron and tests. The DB connection forces `time_zone = '+00:00'`.
- `Model`: tables declare `$tenantScoped`, `$hasUpdatedAt` and `$searchable`. Column names are whitelisted, LIMIT/OFFSET are cast to int, and the model **fails closed**: asking a table without `tenant_id` for a tenant scope throws instead of leaking.
- `Controller`: `requireTenant($roles)`, `requireRole()`, `findOwned()` (404 for other tenants' records), `requireCsrf()` (POST plus token), `failValidation()` (inline errors + old input), and local-only redirects.
- Role matrix: agents get inbox, contacts and analytics. Developers get bots, channels, KB, templates, broadcasts, webhooks and API keys. Tenant admins additionally get team, billing, subscription and settings.

### Features completed to the specification
Flow engine (pause/resume, branching, webhooks, variables, all trigger types), mock AI with KB search + tone + max length + rewrite, quota enforcement with 80%/100% emails, outgoing webhooks with signatures and retries, broadcasts (segments, per-contact variables, scheduling, cron), document upload and indexing, logo upload, CSV import, simulated emails, analytics reports with CSV export, invoices on plan change, renewals and grace period, dummy payments (with a decline test card), the bot simulator, all views, and a cron entry point.

### Security hardening
- Login throttling (5 per email+IP and 20 per email per 15 minutes). `X-Forwarded-For` is trusted only from `TRUSTED_PROXIES`.
- Users of suspended tenants are logged out on their next request.
- Session cookies are HttpOnly + SameSite=Lax (+ Secure on HTTPS) with strict mode. Basic security headers are sent on every response.
- Channel credentials are encrypted (AES-256-CBC + HMAC). API keys are stored as SHA-256 only and shown once.
- Uploads: extension + MIME + image validation, random names, files outside the web root, `realpath` traversal check, tenant-checked downloads.
- Webhooks and the `call_webhook` step: private/reserved IPs are blocked, the resolved IP is pinned (no DNS rebinding), redirects are off, and there is a timeout.
- XSS: `e()` everywhere, `safe_url()` for user links (blocks `javascript:`), media URLs are limited to http(s), JS escaping covers attribute quotes, and email previews are shown in a sandboxed iframe.

### Second review (after the rewrite)
An independent review of the rewritten code found 10 issues, and all of them are fixed with regression tests:

1. Subscriptions never renewed, so all tenants stopped after one month. Fixed: renewal invoices via cron, payment extends the period, and a 7-day grace period.
2. Users of a suspended tenant kept their existing sessions.
3. The login throttle could be bypassed by spoofing `X-Forwarded-For`.
4. Broadcasts stopped at 10,000 recipients and could stay "running" forever. Fixed: batching, always finalised, stale runs resumable.
5. Multi-reply flows could exceed the monthly message limit. Fixed: every extra reply is quota-checked.
6. DOM XSS in the simulator's quick-reply attribute.
7. Stored XSS through `javascript:` media URLs.
8. Steps sharing a `step_order` with a question were skipped on resume. Fixed: resume by step ID.
9. Simulator traffic fired real webhooks, used quota and joined broadcast segments.
10. DNS rebinding in the webhook SSRF check, and synchronous delivery delaying API calls.

## 3. SQL performance notes

- Composite indexes match the hot queries: `messages(tenant_id, created_at)`, `messages(conversation_id, created_at)`, `messages(conversation_id, direction, status)` (unread counts), `conversations(tenant_id, status, last_message_at)` (inbox), `conversations(contact_id, channel_id, status)` (find open conversation), `flows(bot_id, status, priority)`, `broadcasts(status, scheduled_at)` (cron), `broadcast_messages(broadcast_id, status)`, and `login_attempts(email, ip_address, created_at)`.
- Inbox unread counts use a single grouped derived table instead of a correlated subquery per row.
- `UsageTracking::recalculate()` is one query plus a folder scan.
- **At scale:** the "last message" preview in the inbox and `Analytics::averageResponseTimes()` use correlated subqueries. They are fine for thousands of messages per tenant, but for millions add a `conversations.last_message_preview` column and precomputed response-time rollups. `messages` is the table to partition (by month) first.
- Knowledge base search uses InnoDB FULLTEXT. Note that `innodb_ft_min_token_size` (default 3) ignores very short words, and the keyword-scoring fallback covers those.

## 4. Scalability notes

- The app is stateless apart from PHP sessions. For several web servers behind a load balancer, move sessions to Redis/Memcached (`session.save_handler`) and put `storage/uploads` on shared storage or S3.
- Webhook delivery happens after the response (PHP-FPM) but still in the web worker. Broadcasts sent with "Send now" run synchronously. For high volume, move `BroadcastRunner::run()` and `WebhookDispatcher::deliver()` into a queue worker (both are already isolated, side-effect-free entry points).
- Quota checks run COUNT queries per message. That is fine up to roughly hundreds of messages per second. Beyond that, keep counters in Redis.
- Multi-tenancy uses a shared schema with `tenant_id`. Tenants can later be sharded by `tenant_id`, since no query joins across tenants.

## 5. Architecture evaluation

| | |
|---|---|
| **Strengths** | Clear MVC with no SQL in controllers. Business logic lives in small services (`MessageProcessor` is the single pipeline used by the API, webhook, inbox, broadcasts and simulator). Tenant isolation is enforced in two layers (controller guards + model scoping). The code is beginner-readable and has no dependencies. |
| **Trade-offs** | Static service helpers (`QuotaService`, `WebhookDispatcher`) are simple but harder to mock than injected objects. The tests use a fake transport hook instead. The router only matches numeric `{params}`, which is deliberate. |
| **Known limitations** | WhatsApp sending and the AI engine are simulated. PDF text extraction is best-effort (no OCR). There is no password reset or email verification (no mail transport). There is no API rate limiting beyond plan quotas. The UI has a light accessibility pass only. |

## 6. Verification

`php tests/run.php` runs 86 automated tests, including HTTP tests that drive every page and form of the running app. All of them pass on PHP 8.3 + MariaDB 10.11. A static scan found no syntax newer than PHP 7.0 (no short list destructuring, nullable/return types, arrow functions, `str_contains`, `?->`, multi-catch or const visibility), but the suite has not been executed on a PHP 7.0 interpreter.

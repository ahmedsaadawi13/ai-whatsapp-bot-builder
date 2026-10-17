# AI WhatsApp Bot Builder

A multi-tenant SaaS platform, in plain PHP and MySQL, for businesses and agencies to build WhatsApp bots that combine **structured flows** with **AI answers from a knowledge base**. It includes an agent inbox, broadcasts, outgoing webhooks, a public REST API, subscription plans with enforced limits, and dummy billing.

- PHP **7.0 – 8.x**, MySQL 5.7+ / MariaDB 10.3+ (InnoDB), PDO prepared statements only
- Custom lightweight MVC, with no framework and no Composer dependencies
- Vanilla JavaScript and CSS
- WhatsApp providers and the AI engine are **simulated**. Both sit behind small classes you can swap for real integrations (see *Extending*).

---

## Features

| Area | What you get |
|---|---|
| **Multi-tenancy** | One database. Every tenant-owned row has `tenant_id`, and models refuse cross-tenant queries (tables without `tenant_id` fail closed). |
| **Roles** | `platform_admin` (manages tenants), `tenant_admin` (everything in a workspace), `developer` (bots, channels, webhooks, API keys), `agent` (inbox, contacts, analytics). |
| **Channels** | WhatsApp numbers with provider type (Cloud API / Twilio / sandbox), credentials **encrypted at rest** (AES-256 + HMAC), and a per-channel webhook verify token. |
| **Bot builder** | Bots with language, AI tone, max answer length, AI fallback on/off, and a fallback message. Includes a **chat simulator** that also works for draft bots. |
| **Flows** | Triggers: keyword, message contains, menu option, button click, default. Actions: send text, send media, ask question (quick replies + save the answer), call AI, call webhook, set variable, branch condition. Flows **pause on a question and resume** with the answer. `{{variables}}` work in every text. |
| **AI & knowledge base** | Q&A entries plus document upload (TXT/PDF/DOCX/DOC, with text extracted and indexed). MySQL FULLTEXT search with a keyword-scoring fallback, plus tone and max-length handling. *Improve with AI* rewrites agent drafts. |
| **Inbox** | Open/pending/closed tabs, filters, unread counts, full history, agent replies (free text or template), assignment, status changes. **Human takeover:** an assigned conversation silences the bot. |
| **Contacts** | Unique phone per tenant (E.164), tags, custom fields, notes, CSV import, search and filters. |
| **Templates & broadcasts** | `{{placeholders}}` with per-contact mapping (`{{name}}`, `{{custom.city}}`, static values). Segments: all, tag, or custom field. Send now, schedule in the tenant's timezone, or save as draft. Per-recipient delivery stats. |
| **Outgoing webhooks** | Events `message.received`, `message.sent`, `conversation.opened`, `conversation.closed`, `contact.created`. HMAC-SHA256 signature, delivery log, automatic retries, SSRF protection, delivery after the response is sent. |
| **Analytics** | Messages per day, response time (AI vs flow vs agent), top flows and keywords, conversations by status, usage per channel, broadcast performance, CSV export. |
| **Subscriptions & billing** | Plans with `max_channels`, `max_bots`, `max_contacts`, `max_messages_per_month`, `max_storage_mb`, enforced in the UI and API (HTTP 429). Downgrade protection, invoices, renewal invoices, 7-day grace period, and a dummy payment gateway. |
| **Notifications** | Simulated emails (welcome, 80%/100% quota warnings, daily summary). They are stored in the database, viewable under *Email log*, and written to `storage/logs/mail.log`. |

---

## Quick start (local)

```bash
git clone <your-repo-url> ai-whatsapp-bot-builder
cd ai-whatsapp-bot-builder
cp .env.example .env            # set DB_* and APP_KEY, APP_DEBUG=true for development
mysql -u root -p < database.sql # creates database ai_whatsapp_bot with demo data
php -S localhost:8000 -t public # built-in dev server
```

Open http://localhost:8000 and log in with a demo account below.

### Requirements

- PHP 7.0+ with extensions **pdo_mysql, mbstring, openssl, fileinfo, json**. The **curl** extension is recommended (needed for webhooks to hostnames) and **zip** is optional (DOCX text extraction).
- MySQL 5.7+ or MariaDB 10.3+.
- Apache with `mod_rewrite` or Nginx. See [DEPLOYMENT.md](DEPLOYMENT.md).

### Demo accounts (password: `password`)

| Email | Role | Tenant |
|---|---|---|
| admin@platform.com | platform_admin | - |
| john@techcorp.com | tenant_admin | TechCorp Solutions (Professional plan) |
| mike@techcorp.com | developer | TechCorp Solutions |
| sarah@techcorp.com | agent | TechCorp Solutions |
| emma@marketingmasters.com | tenant_admin | Marketing Masters Agency (Enterprise plan) |
| olivia@marketingmasters.com | developer | Marketing Masters Agency |
| david@marketingmasters.com | agent | Marketing Masters Agency |

Demo API keys: TechCorp `awb_demo_techcorp_key_000000000000000000000001`, Marketing Masters `awb_demo_marketing_key_00000000000000000000002`.

Try it: as John, open **Bots → Support Bot** and type `hi`, then `1`, an email address, and a problem description into the simulator. Then try `What is your refund policy?`.

---

## Public API

Base URL `{APP_URL}/api/v1`. JSON in, JSON out. Errors look like `{"error": "...", "details": {...}}`.

| Status | Meaning |
|---|---|
| 200 / 201 | OK / outbound message created |
| 400 | Body is not a JSON object |
| 401 | Missing or invalid API key / webhook token |
| 404 | Bot or conversation not found (or belongs to another tenant) |
| 409 | Channel inactive / contact unsubscribed |
| 415 | Content-Type is not `application/json` |
| 422 | Validation failed (`details` lists fields) |
| 429 | Plan limit reached (messages or contacts) |

Authenticate with `X-API-KEY: <key>` (or `Authorization: Bearer <key>`). Developers and tenant admins create keys under **Developers → API Keys**. A key is shown once, and only its SHA-256 hash is stored.

### POST /send-message: trigger a bot conversation

The message is treated as written by the contact. The contact and conversation are created or updated, the bot runs (flows, then AI), and its replies are returned.

```bash
curl -X POST http://localhost:8000/api/v1/send-message \
  -H "X-API-KEY: awb_demo_techcorp_key_000000000000000000000001" \
  -H "Content-Type: application/json" \
  -d '{"bot_id": 1, "phone_number": "+15551230000", "message": "hello",
       "contact_name": "Jane Doe", "metadata": {"source": "website"}}'
```

```json
{
  "success": true,
  "conversation_id": 8,
  "conversation_created": true,
  "contact_id": 8,
  "inbound_message_id": 68,
  "bot_status": "active",
  "handed_off_to_agent": false,
  "messages": [
    {
      "id": 69,
      "type": "interactive",
      "content": "Welcome to TechCorp, Jane Doe! How can we help?\n1. Support\n2. Sales\n3. Business hours",
      "media_url": null,
      "quick_replies": ["Support", "Sales", "Business hours"],
      "triggered_by": "flow"
    }
  ]
}
```

Fields: `bot_id` (int, required), `phone_number` (E.164, required; spaces and dashes are stripped), `message` (1–4096 chars, required), `contact_name`, `metadata` (object), and `direction` (`inbound` by default, or `outbound`).

**Outbound** (the business sends a message to a contact, with no bot processing): add `"direction": "outbound"`. The response is `201` with the stored message.

### GET /bots and GET /conversations/{id}

```bash
curl -H "X-API-KEY: awb_demo_techcorp_key_000000000000000000000001" http://localhost:8000/api/v1/bots
curl -H "X-API-KEY: awb_demo_techcorp_key_000000000000000000000001" http://localhost:8000/api/v1/conversations/1
```

`/conversations/{id}` returns status, contact, collected variables (e.g. `email`), and messages with `direction`, `type`, `content`, `triggered_by` and `created_at` (UTC).

### Webhook receiver (simulated WhatsApp provider)

Each channel has a verify token (shown on the Channels page). The provider calls:

```bash
curl -X POST http://localhost:8000/api/v1/webhook \
  -H "X-Webhook-Token: verify_token_125" \
  -H "Content-Type: application/json" \
  -d '{"to_phone": "+15559876543", "from_phone": "+15558009999",
       "message": "What services do you offer?", "contact_name": "Sam", "message_id": "wamid.123"}'
```

```json
{"success": true, "conversation_id": 9, "bot_id": 4,
 "messages": [{"id": 71, "type": "text", "content": "We offer social media management, SEO, content marketing and paid advertising campaigns. 😊",
               "media_url": null, "quick_replies": null, "triggered_by": "ai"}]}
```

If the contact's conversation is assigned to an agent, the message is stored and `messages` is empty, because the bot stays silent during human takeover. The tenant and bot are identified by `to_phone` (the channel's number) plus the token. A wrong or foreign token gets 401. If the channel has no active bot, the message is still stored for agents. Verification handshake: `GET /api/v1/webhook?hub.mode=subscribe&hub.verify_token=<token>&hub.challenge=123` echoes `123`.

### Outgoing webhooks

Configure under **Developers → Webhooks**. Each delivery is a POST like this:

```json
{"event": "message.received", "tenant_id": 1, "webhook_id": 3, "timestamp": "2026-10-17T10:00:00+00:00",
 "data": {"conversation_id": 1, "message_id": 42, "from": "+15557001001", "content": "Hello"}}
```

Verify it with the webhook secret:

```php
$expected = 'sha256=' . hash_hmac('sha256', file_get_contents('php://input'), $secret);
$valid = hash_equals($expected, $_SERVER['HTTP_X_SIGNATURE'] ?? '');
```

Any non-2xx response is logged and retried by cron, up to the webhook's attempt limit. URLs that resolve to private or internal IPs are refused (SSRF protection).

---

## Flow engine cheat sheet

- **Triggers** (comma-separated values, case-insensitive): `keyword` = exact message, `message_contains` = substring, `menu` = option such as `1` or `support`, `button` = button payload, `default` = when nothing matches. The highest priority wins.
- **ask_question** with a *variable* pauses the flow. The next message is stored in that variable, then the flow continues. Quick-reply *options* are returned as `quick_replies`. Typing another flow's trigger (e.g. `menu`) leaves the questionnaire.
- **branch_condition** compares a variable (`== != > >= < <= contains exists`; numbers like `2,500` work), then sends a then/else message and/or jumps to another flow of the same bot.
- **call_webhook** POSTs the conversation context. A JSON reply `{"reply": "...", "variables": {"k": "v"}}` is sent and stored.
- **Placeholders:** any saved variable, `{{contact_name}}`, `{{contact_phone}}`, `{{contact_email}}`, `{{custom.<field>}}`.
- If no flow matches: AI answer (when enabled), otherwise the bot's fallback message.

---

## Scheduled jobs

```bash
php cron/run.php all      # every minute: due broadcasts, webhook retries, usage snapshot, renewal invoices
php cron/run.php daily    # once a day: daily summary email to tenant admins
```

```cron
* * * * *  php /var/www/ai-whatsapp-bot-builder/cron/run.php all   >> /var/log/awb-cron.log 2>&1
0 7 * * *  php /var/www/ai-whatsapp-bot-builder/cron/run.php daily >> /var/log/awb-cron.log 2>&1
```

---

## Tests

```bash
php tests/run.php                  # whole suite (86 tests)
php tests/run.php HttpTest         # one class
php tests/run.php BotEngineTest branch
```

The runner **drops and re-creates** `TEST_DB_NAME` (default `ai_whatsapp_bot_test`, which must end in `_test`) from `database.sql` before each class, using the DB credentials from `.env`. `HttpTest` starts PHP's built-in server on a random port and drives the real app over HTTP.

| Test class | Covers |
|---|---|
| AuthTest | password hashing, login, throttling (per IP and per account), inactive users, suspended tenants, CSRF, proxy headers |
| TenantIsolationTest | scoped find/list/update/delete, cross-tenant message writes, fail-closed models, API key ownership |
| CrudTest | tenants, bots, flows, steps (reordering), contacts and tags, conversations, templates, plans, invoices, payments, encryption |
| QuotaTest | bot, channel, contact, message and storage limits, expiry and grace period, renewals, warning emails |
| BotEngineTest | every trigger and action type, pause/resume, branching, AI fallback, takeover, simulator isolation, per-reply quota |
| BroadcastWebhookTest | segments, variable mapping, scheduling, double-send protection, signatures, retries, SSRF, upload validation |
| HttpTest | the public API (all status codes), webhook receiver, CSRF, roles, cross-tenant URLs (404), all forms, every page rendered without PHP notices |

The manual checklist is in [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md).

---

## Project structure

```
app/
  bootstrap.php        env loading, UTC, autoloader (used by web, cron, tests)
  core/                Router, Controller, Model, View, Database, Request, Session,
                       Auth, CSRF, Validator, FileUpload, Crypto, BotEngine, helpers
  services/            MessageProcessor, AiEngine, QuotaService, WebhookDispatcher,
                       BroadcastRunner, Notifier, DocumentText
  models/              one class per table (+ Analytics read model)
  controllers/         one per module; no SQL in controllers
  views/               layouts/, partials/, emails/, one folder per module
config/                app.php, database.php
cron/run.php           scheduled jobs
public/                index.php (front controller + routes), .htaccess, assets/
storage/uploads/       tenant_<id>/<logos|media|knowledge>/ - outside the web root
storage/logs/          mail.log
tests/                 run.php, TestCase.php, *Test.php
database.sql           schema, indexes, foreign keys, demo seed data
```

Request flow: `public/index.php` → `Router` → `Controller` (auth/role/tenant guard, CSRF, validation) → `Model`/`Service` (prepared SQL) → `View` (escaped output) inside `layouts/layout.php`.

---

## Security notes

- **SQL:** prepared statements everywhere, column names whitelisted, and LIMIT/OFFSET cast to int.
- **Tenant isolation:** `requireTenant()` plus `findOwned()` in controllers, `tenant_id` in every query, foreign IDs (bot, channel, template, tag, flow) verified on write, and models without `tenant_id` throw if asked for a tenant scope.
- **Auth:** `password_hash` / `password_verify`, session ID regeneration, HttpOnly + SameSite cookies (Secure on HTTPS), login throttling (5 per email+IP and 20 per email per 15 minutes), and suspended tenants are logged out immediately.
- **CSRF** token on every POST form (`hash_equals`). Logout is a POST.
- **XSS:** all output goes through `e()`, user-controlled links go through `safe_url()`, emails are previewed in a sandboxed iframe, and CSV exports are protected against formula injection.
- **Uploads:** extension whitelist, MIME sniffing (`finfo`), image check, size limit, random names, storage outside `public/`, `realpath` traversal check, and per-tenant access control when serving files.
- **Webhooks:** HMAC signatures, private-IP blocking with DNS pinning, no redirects, 5-second timeout.
- **Secrets:** channel credentials encrypted with `APP_KEY`; API keys stored only as SHA-256 hashes.

## Extending

- **Real WhatsApp provider:** outbound messages are created in `MessageProcessor::storeOutbound()`. Send them to the provider there (or queue them) using `Channel::decodeConfig()` credentials.
- **Real LLM:** replace the body of `AiEngine::answer()` (pass the knowledge-base hits as context) and `AiEngine::rewrite()`.
- **Async jobs:** `BroadcastRunner::run()` and `WebhookDispatcher::deliver()` are already isolated, so they are easy to move into a queue worker.

## GitHub setup

```bash
git init
git add .
git commit -m "Initial commit"
git branch -M main
gh repo create ai-whatsapp-bot-builder --public --source=. --remote=origin
git push -u origin main
```

See also: [DEPLOYMENT.md](DEPLOYMENT.md) · [CODE_REVIEW.md](CODE_REVIEW.md) · [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md) · [ADVANCED_NEXT_PHASE.md](ADVANCED_NEXT_PHASE.md)

# Testing Checklist - AI WhatsApp Bot Builder

Automated: `php tests/run.php` (86 tests, about 5 seconds). The **Auto** column names the test that covers an item. Items marked *manual* need a browser.

Demo password for every account: `password`.

## Authentication
| Check | Auto |
|---|---|
| Login works; wrong password / unknown email rejected with the same message | AuthTest |
| 5 failures per email+IP and 20 per email lock out for 15 min; spoofed X-Forwarded-For ignored | AuthTest |
| Inactive users and users of suspended tenants cannot log in; active sessions end on suspension | AuthTest |
| Login and every POST form reject a missing or invalid CSRF token | HttpTest |
| Registration creates tenant + tenant_admin + subscription + invoice + welcome email | HttpTest |
| Logout (POST) ends the session and shows the flash message | *manual* |

## CRUD
| Check | Auto |
|---|---|
| Tenants (platform admin): create with admin user, edit status/timezone, delete cascades | CrudTest, *manual UI* |
| Channels: create (E.164, globally unique number, JSON credentials encrypted), edit keeps blank credentials, regenerate token | HttpTest (create), *manual edit* |
| Bots: create, edit, toggle active/paused, delete cascades to flows/steps | CrudTest, HttpTest |
| Flows and steps: every action type form, reorder up/down, edit, delete | CrudTest, HttpTest, *manual per action* |
| Knowledge base: create/edit/delete entry, upload TXT/PDF/DOCX (text indexed), wrong type rejected | HttpTest, BroadcastWebhookTest |
| Contacts: create with tags + custom fields, edit, delete, CSV import (invalid rows skipped, limit respected) | CrudTest, HttpTest |
| Conversations: agent reply, template reply, assign/unassign, open/pending/closed | HttpTest (reply), *manual assign/status* |
| Templates: variables auto-detected, insert-variable buttons | HttpTest, *manual buttons* |
| Users: tenant admin cannot create platform_admin, cannot delete self or the last admin | HttpTest, *manual last-admin* |

## Tenant isolation
| Check | Auto |
|---|---|
| Models: find/list/update/delete never cross tenants | TenantIsolationTest |
| Messages cannot be written into another tenant's conversation | TenantIsolationTest |
| Tables without tenant_id fail closed | TenantIsolationTest |
| URLs of another tenant's bot/contact/conversation/broadcast/invoice/webhook/flow return 404 (GET and POST) | HttpTest |
| API key of tenant B cannot read tenant A's conversation or use its bots | HttpTest |
| Uploaded logo/documents only downloadable by the owning tenant | HttpTest |

## Subscription enforcement
| Check | Auto |
|---|---|
| Bot, channel, contact, message and storage limits block creation with a clear message | QuotaTest |
| API returns 429 when the message limit is reached | HttpTest |
| Multi-reply flows never exceed the monthly limit | BotEngineTest |
| 80% warning and 100% exceeded emails, once per month | QuotaTest |
| Downgrade blocked while usage exceeds the new plan | HttpTest |
| Expired subscription: 7-day grace, then blocked; renewal invoice issued once; payment extends | QuotaTest |
| Dummy payment: 4242... succeeds, a card ending 0002 is declined | CrudTest, HttpTest |

## Public API & webhook receiver
| Check | Auto |
|---|---|
| send-message: 401 / 415 / 400 / 422 / 404 / 200 / 201 (outbound) / 429 | HttpTest |
| Response contains conversation ID, contact ID and bot replies (text, media, quick replies) | HttpTest |
| GET /bots, GET /conversations/{id} | HttpTest |
| Webhook receiver: wrong or foreign token gives 401; correct token routes to the channel's bot and tenant | HttpTest |
| Verification handshake echoes hub.challenge | HttpTest |

## Conversation flow (incoming message → bot logic → AI → log)
| Check | Auto |
|---|---|
| keyword / contains / menu / button / default triggers | BotEngineTest, CrudTest |
| ask_question pauses, stores the answer, resumes (also with duplicate step orders) | BotEngineTest |
| branch_condition on numbers; set_variable; {{variables}} in text and media captions | BotEngineTest |
| AI answers from the knowledge base; fallback lists topics; max length applied | BotEngineTest |
| Bot disabled for AI uses the default flow / fallback message | BotEngineTest |
| Inbound + outbound messages logged with triggered_by | BotEngineTest |
| Assigned conversation silences the bot (human takeover); paused bot stores only | BotEngineTest |
| Simulator works for draft bots without webhooks, quota or broadcast side effects | BotEngineTest, HttpTest |

## Broadcasts & outgoing webhooks
| Check | Auto |
|---|---|
| Send now / scheduled via cron; unsubscribed contacts excluded; per-contact variables | BroadcastWebhookTest, HttpTest |
| A broadcast is never sent twice; crashed runs resume | BroadcastWebhookTest |
| Webhook payloads signed (X-Signature), logged, retried up to the limit | BroadcastWebhookTest |
| Private / metadata / loopback URLs rejected | BroadcastWebhookTest, HttpTest |
| "Send test" button on a webhook, logs page filters | *manual* (needs a reachable URL, e.g. webhook.site) |

## UI (manual)
- [ ] Every page renders for each role (also covered automatically by `testMainPagesRenderWithoutPhpErrors`).
- [ ] Sidebar shows only the sections the role may open; mobile menu toggle works.
- [ ] Dashboard and analytics charts render; date range and CSV export work.
- [ ] Flow step form shows only the fields of the selected action; broadcast form shows tag/custom/schedule fields when chosen.
- [ ] Simulator: quick-reply buttons send the option; "start a fresh conversation" resets.
- [ ] "Improve with AI" rewrites the reply draft.
- [ ] Invoice page prints cleanly.

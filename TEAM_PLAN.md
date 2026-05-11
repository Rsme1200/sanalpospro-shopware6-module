# SanalPosPro / Shopware 6 — Team Work Plan
**Based on PayThor/Magento methodology**
**Date:** 2026-05-08 | **PM:** Reem | **Team:** 3 Developers

---

## Table of Contents
1. [Tech Stack Comparison](#1-tech-stack-comparison)
2. [Day 0 — Contracts (read before writing any code)](#2-day-0--contracts)
3. [Phase 0 — PM (Skeleton)](#3-phase-0--pm-skeleton)
4. [Dev 1 — Core Backend](#4-dev-1--core-backend)
5. [Dev 2 — Storefront](#5-dev-2--storefront)
6. [Dev 3 — Admin + Webhook](#6-dev-3--admin--webhook)
7. [Git Workflow](#7-git-workflow)
8. [Dependency Map](#8-dependency-map)
9. [Integration Checklist (Day 7)](#9-integration-checklist-day-7)

---

## 1. Tech Stack Comparison

| Component | Magento 2 (existing) | Shopware 6 (new) |
|---|---|---|
| Module registration | `registration.php` + `etc/module.xml` | `SanalPosPro.php` (Bundle) + `composer.json` |
| Dependency Injection | `etc/di.xml` | `Resources/config/services.php` (Symfony DI) |
| Database schema | `etc/db_schema.xml` (declarative) | `Migration/*.php` (DBAL plain SQL) |
| Payment handler | `Model/PaymentMethod.php` (AbstractMethod) | `AbstractPaymentHandler` → `pay()` + `finalize()` |
| Admin frontend | Knockout.js + RequireJS | Vue.js (`Shopware.Module.register`) |
| Storefront JS | RequireJS modules | Plugin class (`window.PluginManager.register`) |
| Configuration | `etc/adminhtml/system.xml` | `Resources/config/config.xml` |
| Webhook endpoint | `Controller/Webhook/Notify.php` | `Core/Api/WebhookController.php` (API scope) |
| Transaction log | `paythor_transaction_log` (db_schema.xml) | `sanalpospro_webhook_log` (Migration) |
| Installments display | `Block/Product/Installments.php` + `.phtml` | DAL Entity + Vue.js CRUD Admin module |

---

## 2. Day 0 — Contracts

**All three developers must read and agree on these contracts before writing any code.**

---

### Contract A — Dev 1 (Payment Handler) ↔ Dev 2 (Storefront Controller)

```
transactionId format : UUID v4 string (Shopware order_transaction.id)
iframe route pattern : GET /sanalpospro/iframe/{transactionId}
returnUrl passing    : query param ?returnUrl={url} or Shopware's built-in finalize URL

postMessage payload (same structure as Callback.php in Magento):
{
  "source":    "paythor_sanalpospro",
  "status":    "success" | "failure" | "cancel",
  "reference": "{transactionId}",
  "message":   "human readable (optional)"
}
```

---

### Contract B — Dev 1 (Payment Handler) ↔ Dev 3 (Webhook)

```
Webhook HTTP method : POST
Webhook route       : /api/sanalpospro/webhook
Auth header         : X-Paythor-Signature: HMAC-SHA256(webhookSecret, rawBody)

Expected payload:
{
  "merchant_order_id": "{shopware_order_transaction_id}",
  "transaction_id":    "{paythor_gateway_tx_id}",
  "status":            "success" | "authorized" | "declined" | "cancelled" | "refunded" | ...,
  "message":           "optional human readable"
}

Status normalization (same as Notify.php):
  approved  : success, approved, authorized, tamamlandi
  failed    : declined, cancelled, failed, reddedildi
  refunded  : refunded, iade edildi
  pending   : pending, processing, baslatildi
```

---

### Contract C — Dev 2 (Admin) ↔ Dev 3 (Webhook)

**`sanalpospro_webhook_log` table schema — agreed column names:**

```sql
id              BINARY(16) PRIMARY KEY   -- UUID
order_tx_id     VARCHAR(128) NOT NULL    -- Shopware order_transaction.id
paythor_tx_id   VARCHAR(128) NULL        -- PayThor gateway transaction_id
action          VARCHAR(32)  NOT NULL    -- 'webhook' | 'callback'
status          VARCHAR(32)  NOT NULL    -- 'success' | 'failed' | 'pending' | 'refunded'
amount          DECIMAL(20,4) NULL
currency        CHAR(3) NULL
raw_payload     LONGTEXT NULL            -- full JSON
created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
```

**`sanalpospro_installment` table schema:**

```sql
id              BINARY(16) PRIMARY KEY   -- UUID
bank_name       VARCHAR(128) NOT NULL
card_type       VARCHAR(64)  NULL
installment_count INT NOT NULL
interest_rate   DECIMAL(5,2) NOT NULL DEFAULT 0.00
is_active       TINYINT(1) NOT NULL DEFAULT 1
created_at      DATETIME(3) NOT NULL
updated_at      DATETIME(3) NULL
```

---

## 3. Phase 0 — PM (Skeleton)

**Who:** Reem (PM)
**When:** Day 1, ~3 hours
**Branch:** push directly to `main`

**Goal:** A working plugin skeleton that all three developers pull before they start.

### Files to create:

| File | Purpose |
|---|---|
| `custom/plugins/SanalPosPro/composer.json` | Plugin registration, type: `shopware-platform-plugin` |
| `custom/plugins/SanalPosPro/src/SanalPosPro.php` | Extends `Plugin`, install/uninstall hooks |
| `custom/plugins/SanalPosPro/src/Resources/config/config.xml` | `publicApiKey`, `secretApiKey`, `webhookSecret` |
| `custom/plugins/SanalPosPro/src/Resources/config/services.php` | Empty Symfony DI container with section comments |
| `custom/plugins/SanalPosPro/src/Resources/config/routes.php` | Empty routes file |

### Acceptance Criteria:
- [ ] `bin/console plugin:install SanalPosPro` runs without error
- [ ] `bin/console plugin:activate SanalPosPro` runs without error
- [ ] Plugin config fields appear in Admin → Extensions → SanalPosPro
- [ ] No PHP fatal errors on page load

---

## 4. Dev 1 — Core Backend

**Branch:** `feature/dev1-core-backend`
**Starts:** After Phase 0 is merged to `main`
**Duration:** 3–4 days
**Depends on:** Phase 0 only

---

### Task 1.1 — Database Layer (DAL)

**Files:**
```
src/Core/Content/WebhookLog/
├── WebhookLogDefinition.php
├── WebhookLogEntity.php
└── WebhookLogCollection.php

src/Core/Content/Installment/
├── InstallmentDefinition.php
├── InstallmentEntity.php
└── InstallmentCollection.php

src/Migration/Migration1710000000InstallmentAndLog.php
```

**Key patterns from PayThor:**
- `WebhookLogDefinition` maps to `paythor_transaction_log` in Magento — use Contract C column names exactly
- `InstallmentDefinition` is new (was a Block in Magento, now a proper entity)
- Migration uses raw DBAL SQL (no ORM), same as `db_schema.xml` was declarative

**Register in `services.php` (Dev 1 section):**
```php
$services->tag('shopware.entity.definition', WebhookLogDefinition::class);
$services->tag('shopware.entity.definition', InstallmentDefinition::class);
```

**Acceptance Criteria:**
- [ ] `bin/console database:migrate SanalPosPro` creates both tables
- [ ] `bin/console database:migrate:rollback SanalPosPro` drops them cleanly
- [ ] `$container->get('sanalpospro_webhook_log.repository')` resolves without error
- [ ] `$container->get('sanalpospro_installment.repository')` resolves without error
- [ ] Column names match Contract C exactly

---

### Task 1.2 — Payment Handler

**File:** `src/Core/Checkout/Payment/SanalPosProPaymentHandler.php`

**Logic (translated from PayThor Callback.php):**

```
pay() method:
  1. Read publicApiKey from SystemConfigService
  2. Validate key is not empty → throw PaymentException if missing
  3. Build iframe URL: /sanalpospro/iframe/{transaction->getId()}
  4. Attach returnUrl as query param (Shopware provides it via AsyncPaymentTransactionStruct)
  5. Return RedirectResponse to iframe URL

finalize() method:
  1. Read request params: status, reference
  2. Read X-Paythor-Signature header → verify HMAC (reuse WebhookSignatureVerifier service)
  3. status = 'success' → no exception (Shopware treats no exception as paid)
  4. status = 'failure'/'cancel' → throw PaymentException::asyncProcessInterrupted()
```

**Register in `services.php` (Dev 1 section):**
```php
$services->tag('shopware.payment.method', SanalPosProPaymentHandler::class);
```

**Acceptance Criteria:**
- [ ] Payment method appears in checkout payment selection
- [ ] `pay()` redirects to `/sanalpospro/iframe/{uuid}` with correct returnUrl
- [ ] `finalize()` with status=success → order moves to `paid` state
- [ ] `finalize()` with status=failure → order moves to `failed` state
- [ ] Missing API key → payment method is unavailable (not shown in checkout)

---

## 5. Dev 2 — Storefront

**Branch:** `feature/dev2-storefront`
**Starts:** After Phase 0 (completely independent of Dev 1 and Dev 3)
**Duration:** 3 days
**Depends on:** Phase 0 + Contract A (agreed transactionId format)

---

### Task 2.1 — Storefront Controller

**File:** `src/Storefront/Controller/SanalPosProController.php`

**Two routes (same two cases as Callback.php in Magento):**

```
Route 1: GET /sanalpospro/iframe/{transactionId}
  _routeScope: storefront
  1. Validate transactionId is not empty → redirect to cart if missing
  2. Read returnUrl from request query param
  3. Read publicApiKey from SystemConfigService
  4. Render iframe.html.twig with: transactionId, returnUrl, publicApiKey

Route 2: GET /sanalpospro/callback
  _routeScope: storefront
  = same as handlePostMessageBridge() in Callback.php
  Reads: status, reference, message from query params
  Validates status is one of: success | failure | cancel
  Returns raw HTML page that calls window.parent.postMessage(payload)
```

**Register route in `routes.php`:**
```php
$routes->add('sanalpospro.iframe', '/sanalpospro/iframe/{transactionId}')->setMethods(['GET']);
$routes->add('sanalpospro.callback', '/sanalpospro/callback')->setMethods(['GET']);
```

**Acceptance Criteria:**
- [ ] GET `/sanalpospro/iframe/test-uuid` → 200, renders Twig template
- [ ] GET `/sanalpospro/callback?status=success&reference=abc` → 200, returns HTML with postMessage script
- [ ] GET `/sanalpospro/callback?status=invalid` → treated as `failure`
- [ ] Missing transactionId → redirect to cart (no 500)

---

### Task 2.2 — Twig Template

**File:** `src/Resources/views/storefront/page/checkout/iframe.html.twig`

**Requirements:**
- Extends `@Storefront/storefront/base.html.twig`
- Wrapper div has `data-sanalpospro-iframe="true"` (JS plugin binding)
- Data attributes: `data-transaction-id`, `data-return-url`, `data-iframe-url`
- iframe tag pointing to PayThor payment gateway URL
- CSP-compatible (no inline JS in this file)

**Acceptance Criteria:**
- [ ] Page renders without Twig errors
- [ ] `data-sanalpospro-iframe="true"` attribute is present in DOM
- [ ] All data attributes are populated with correct values

---

### Task 2.3 — JavaScript Plugin

**File:** `src/Resources/app/storefront/src/sanalpospro-iframe/sanalpospro-iframe.plugin.js`
**Register in:** `storefront/src/main.js`

**Logic (translated from sanalpospro-method.js in Magento):**

```javascript
init() {
  this.returnUrl = this.el.dataset.returnUrl;
  window.addEventListener('message', this._onMessage.bind(this));
}

_onMessage(event) {
  // Security: only handle messages from same origin
  if (event.origin !== window.location.origin) return;
  if (!event.data || event.data.source !== 'paythor_sanalpospro') return;

  const { status, reference } = event.data;

  if (status === 'success') {
    window.location.replace(this.returnUrl);
  } else {
    window.location.replace('/checkout/cart');
  }
}
```

**Acceptance Criteria:**
- [ ] Plugin registers without JS errors in browser console
- [ ] postMessage with `{ source: 'paythor_sanalpospro', status: 'success' }` → redirects to returnUrl
- [ ] postMessage with `{ source: 'paythor_sanalpospro', status: 'failure' }` → redirects to cart
- [ ] Messages from wrong source are ignored (security)
- [ ] Messages from different origin are ignored (security)

---

## 6. Dev 3 — Admin + Webhook

**Branch:** `feature/dev3-admin-webhook`
**Starts:** After Phase 0 + Contract C agreed
**Duration:** 4 days
**Depends on:** Dev 1's DAL merge for final integration testing (can develop with stubs first)

---

### Task 3.1 — Webhook Controller

**File:** `src/Core/Api/WebhookController.php`

**Logic (direct translation of Notify.php from Magento):**

```
POST /api/sanalpospro/webhook
_routeScope: api
CSRF: disabled (external server-to-server call)

Step 1 — Signature verification (same as Notify.php line 68):
  Read X-Paythor-Signature header
  Compute HMAC-SHA256(webhookSecret, rawBody)
  Use hash_equals() for constant-time comparison
  → 401 if invalid, log: remote IP, body length

Step 2 — Decode payload:
  json_decode with JSON_THROW_ON_ERROR
  → 400 if malformed JSON

Step 3 — Extract fields:
  merchant_order_id (= Shopware order_transaction.id)
  transaction_id, status, message
  → 400 if merchant_order_id or status is empty

Step 4 — Idempotency guard (same as Notify.php line 119):
  Load order_transaction from repository
  If state is already 'paid' or 'cancelled' → return 200 "already finalized"

Step 5 — Status routing:
  approved  → StateMachineRegistry::transition(order_transaction, 'paid')
  failed    → StateMachineRegistry::transition(order_transaction, 'cancel')
  refunded  → StateMachineRegistry::transition(order_transaction, 'refund')
  pending   → log only, no transition (same as Notify.php line 162)
  unknown   → add order comment, no transition

Step 6 — Log to webhook_log.repository:
  Always log regardless of outcome (action='webhook')
```

**Register in `services.php` (Dev 3 section):**
```php
$services->tag('controller.service_arguments', WebhookController::class);
```

**Register route in `routes.php`:**
```php
$routes->add('sanalpospro.webhook', '/api/sanalpospro/webhook')->setMethods(['POST']);
```

**Acceptance Criteria:**
- [ ] POST without signature header → 401
- [ ] POST with wrong signature → 401
- [ ] POST with malformed JSON → 400
- [ ] POST with missing `merchant_order_id` → 400
- [ ] POST with `status=success` → `order_transaction` transitions to `paid`
- [ ] POST with `status=declined` → `order_transaction` transitions to `cancelled`
- [ ] POST same payload twice → second call returns 200 "already finalized" (idempotency)
- [ ] Every POST creates a record in `sanalpospro_webhook_log`
- [ ] Signature verification uses `hash_equals()` (not `===`)

---

### Task 3.2 — Administration Vue.js Module

**Files:**
```
src/Resources/app/administration/src/
├── module/
│   ├── sanalpospro-installment/
│   │   ├── page/
│   │   │   ├── sanalpospro-installment-list/index.js
│   │   │   └── sanalpospro-installment-detail/index.js
│   │   └── index.js
│   └── sanalpospro-webhook-log/
│       ├── page/sanalpospro-webhook-log-list/index.js
│       └── index.js
└── main.js
```

**Installment Module:**
- CRUD grid using `this.repositoryFactory.create('sanalpospro_installment')`
- Columns: bank_name, card_type, installment_count, interest_rate, is_active
- Create / Edit / Delete with confirmation dialog on delete

**Webhook Log Module:**
- Read-only list using `this.repositoryFactory.create('sanalpospro_webhook_log')`
- Columns: created_at, order_tx_id, paythor_tx_id, action, status, amount, currency
- Sortable by created_at DESC
- No create/edit/delete (audit log, read-only)

**Acceptance Criteria:**
- [ ] Both modules appear in Admin sidebar under "SanalPosPro"
- [ ] Installment list loads without JS errors
- [ ] Create new installment → appears in list
- [ ] Edit installment → changes saved correctly
- [ ] Delete installment → removed from list
- [ ] Webhook log list loads and shows records
- [ ] Webhook log has no create/edit/delete buttons

---

## 7. Git Workflow

```
main
├── feature/dev1-core-backend    ← DAL + Payment Handler
├── feature/dev2-storefront      ← Controller + Twig + JS
└── feature/dev3-admin-webhook   ← Vue Admin + Webhook Controller
```

**Day-by-day schedule:**

| Day | Action | Who |
|---|---|---|
| 1 | Phase 0 skeleton → push to `main` | PM |
| 1 | Pull `main`, create branches, start coding | All 3 devs |
| 2–4 | Development on individual branches | Dev 1, 2, 3 |
| 5 | Dev 1 opens PR → `main` (DAL first, others need it) | Dev 1 |
| 5 | Dev 2 opens PR → `main` (independent, any order) | Dev 2 |
| 5 | PM reviews + merges Dev 1 and Dev 2 PRs | PM |
| 6 | Dev 3 opens PR → `main` (after Dev 1 merged) | Dev 3 |
| 6 | PM reviews + merges Dev 3 PR | PM |
| 7 | Full E2E integration test | PM + All |

**PR rules:**
- Each PR must pass its own Acceptance Criteria before review
- No force push to `main`
- Conflicts resolved by rebasing on `main` (not merging `main` into feature)

---

## 8. Dependency Map

```
Phase 0 (Skeleton) ────────────────────────────────────┐
         │                                              │
         ▼                                              ▼
Dev 1: DAL (Task 1.1)                     Dev 2: Storefront (completely independent)
         │
         ▼
Dev 1: Payment Handler (Task 1.2)
         │
         ├──────────────────────────────────────────────┐
         │                                              │
         ▼                                              ▼
Dev 3: Webhook Controller (needs DAL)     Dev 3: Admin Vue (needs DAL entity names only = Contract C)
```

**The only blocking dependency:**
Dev 3's Webhook Controller needs `sanalpospro_webhook_log.repository` to be registered.
**Solution:** Dev 3 writes the controller fully and tests with a stub repository.
Real integration happens after Dev 1's PR is merged (Day 5-6).

---

## 9. Integration Checklist (Day 7)

Run these tests in order after all branches are merged:

### Payment Flow (Happy Path)
- [ ] Customer proceeds to checkout, selects SanalPosPro
- [ ] `pay()` redirects to `/sanalpospro/iframe/{transactionId}`
- [ ] Iframe loads PayThor payment form
- [ ] Customer completes payment in iframe
- [ ] PayThor posts `window.postMessage` with `status: 'success'`
- [ ] JS plugin catches message, redirects to returnUrl
- [ ] Shopware `finalize()` is called with correct params
- [ ] Order transitions to `paid`
- [ ] Customer sees order success page

### Payment Flow (Failure Path)
- [ ] Customer cancels payment in iframe
- [ ] postMessage with `status: 'failure'` is sent
- [ ] JS plugin redirects to cart
- [ ] Order transitions to `failed`

### Webhook Flow
- [ ] PayThor server sends POST to `/api/sanalpospro/webhook`
- [ ] Signature is verified
- [ ] Order transaction state updates correctly
- [ ] Record appears in `sanalpospro_webhook_log`
- [ ] Admin webhook log module shows the new record

### Idempotency
- [ ] Browser callback + webhook both fire for same order → order finalized once only

### Admin Module
- [ ] Can create installment plan for a bank
- [ ] Installment plan appears in list
- [ ] Can edit and delete installment plan

### Security
- [ ] Webhook without signature → 401
- [ ] Webhook with wrong signature → 401
- [ ] postMessage from different origin → ignored

---

*Document version: 1.0 — 2026-05-08*

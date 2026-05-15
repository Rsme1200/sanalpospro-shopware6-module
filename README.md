# SanalPosPro Shopware 6 Module

SanalPosPro payment integration for Shopware 6. This plugin adds the PayThor payment flow, an admin CDN panel, webhook handling, and storefront installment tabs.

## English

### Features

- Payment method `Sanal Pos Pro` with redirect to the iframe flow
- Storefront iframe page that lists gateways and creates a payment session via IAPI
- Callback handler for postMessage and full-page redirects
- Signed API webhook endpoint with audit logging
- Admin module that loads the PayThor CDN React panel (account & management)
- Installment CRUD module and product detail installment tab (modern/classic)

### Requirements

- Shopware Core ~6.7.0
- PayThor merchant credentials (public/secret keys) and a webhook secret

### Installation

1. Place the plugin under `custom/plugins/SanalPosPro` (this repo).
2. Install and activate:

```
bin/console plugin:refresh
bin/console plugin:install --activate SanalPosPro
```

3. Run migrations:

```
bin/console database:migrate SanalPosPro
```

### Configuration (Admin)

The plugin does not ship a `config.xml` form. Configuration is stored in system config via the PayThor CDN panel and IAPI bridge.

- Admin > Extensions > SanalPos Pro > Account & Management
- Settings stored under these keys (examples):
  - `SanalPosPro.config.publicApiKey`
  - `SanalPosPro.config.secretApiKey`
  - `SanalPosPro.config.appId`
  - `SanalPosPro.config.accessToken`
  - `SanalPosPro.config.webhookSecret`
  - `SanalPosPro.config.orderStatus`
  - `SanalPosPro.config.currencyConvert`
  - `SanalPosPro.config.showInstallmentsTabs`
  - `SanalPosPro.config.paymentPageTheme`
  - `SanalPosPro.config.installments`

### Payment Flow (Storefront)

1. Checkout payment handler redirects to `/sanalpospro/iframe/{transactionId}?returnUrl=...`.
2. The iframe page calls `/sanalpospro/iapi/index` to fetch gateways and create a payment session, then loads the PayThor iframe URL.
3. PayThor callback:
   - postMessage flow: `/sanalpospro/callback` returns HTML that posts `{ source: 'paythor_sanalpospro', status, reference, message }`.
   - full-page redirect: PayThor appends `p_id`; controller verifies status via PayThor API and redirects to `returnUrl&p_id=...` on success.
4. `SanalPosProPaymentHandler::finalize()` marks the transaction paid when `p_id` is present; otherwise it fails the transaction.

### Webhook

- `POST /api/sanalpospro/webhook` (recommended)
  - Signature header: `X-Paythor-Signature` (HMAC-SHA256 of raw body, key = `SanalPosPro.config.webhookSecret`)
  - Idempotent and writes to `sanalpospro_webhook_log`
- `POST /sanalpospro/webhook` (legacy storefront)
  - No signature validation; expects `transaction_id` and `status`

### Admin Modules

- `sanalpospro-connect`: PayThor CDN React panel (account & management)
- `sanalpospro-installment`: CRUD UI for `sanalpospro_installment`
- `sanalpospro-webhook-log`: Read-only list for `sanalpospro_webhook_log`

### Storefront Installments Tab

- Product detail tabs include an Installments section when `showInstallmentsTabs` is enabled.
- Themes: `modern` (tabbed) or `classic` (grid).
- JS plugin `SanalPosProInstallment` handles card-family tab switching.

### Database

Migrations create the following tables:

- `sanalpospro_webhook_log`
- `sanalpospro_installment`
- `yes` (example entity)

### Build / Assets

Run these after installing or changing storefront/admin code:

```
bin/build-administration.sh
bin/build-storefront.sh
```

Alternative for storefront only:

```
bin/console theme:compile
```

## Deutsch

### Funktionen

- Zahlungsart `Sanal Pos Pro` mit Weiterleitung in den Iframe-Flow
- Storefront-iframe-Seite, die Gateways listet und eine Zahlungssitzung via IAPI erstellt
- Callback-Handler fuer postMessage und Full-Page Redirects
- Signierter API-Webhook mit Audit-Logging
- Admin-Modul, das das PayThor CDN React Panel laedt (Account & Management)
- Installment CRUD Modul und Produktdetail-Tab (modern/classic)

### Voraussetzungen

- Shopware Core ~6.7.0
- PayThor Merchant-Credentials (public/secret keys) und ein webhook secret

### Installation

1. Plugin unter `custom/plugins/SanalPosPro` ablegen (dieses Repo).
2. Installieren und aktivieren:

```
bin/console plugin:refresh
bin/console plugin:install --activate SanalPosPro
```

3. Migrationen ausfuehren:

```
bin/console database:migrate SanalPosPro
```

### Konfiguration (Admin)

Das Plugin liefert kein `config.xml` Formular. Konfiguration wird ueber das PayThor CDN Panel und die IAPI Bridge in der System-Config gespeichert.

- Admin > Extensions > SanalPos Pro > Account & Management
- Einstellungen unter diesen Keys (Beispiele):
  - `SanalPosPro.config.publicApiKey`
  - `SanalPosPro.config.secretApiKey`
  - `SanalPosPro.config.appId`
  - `SanalPosPro.config.accessToken`
  - `SanalPosPro.config.webhookSecret`
  - `SanalPosPro.config.orderStatus`
  - `SanalPosPro.config.currencyConvert`
  - `SanalPosPro.config.showInstallmentsTabs`
  - `SanalPosPro.config.paymentPageTheme`
  - `SanalPosPro.config.installments`

### Zahlungsfluss (Storefront)

1. Checkout Payment Handler leitet zu `/sanalpospro/iframe/{transactionId}?returnUrl=...` weiter.
2. Die Iframe-Seite ruft `/sanalpospro/iapi/index` auf, holt Gateways, erstellt eine Session und laedt die PayThor Iframe-URL.
3. PayThor Callback:
   - postMessage Flow: `/sanalpospro/callback` liefert HTML, das `{ source: 'paythor_sanalpospro', status, reference, message }` postet.
   - Full-Page Redirect: PayThor haengt `p_id` an; der Controller prueft den Status ueber die PayThor API und leitet auf `returnUrl&p_id=...` weiter.
4. `SanalPosProPaymentHandler::finalize()` setzt die Transaktion auf bezahlt, wenn `p_id` vorhanden ist; sonst fehlgeschlagen.

### Webhook

- `POST /api/sanalpospro/webhook` (empfohlen)
  - Signature Header: `X-Paythor-Signature` (HMAC-SHA256 ueber raw body, key = `SanalPosPro.config.webhookSecret`)
  - Idempotent und schreibt nach `sanalpospro_webhook_log`
- `POST /sanalpospro/webhook` (legacy storefront)
  - Keine Signaturpruefung; erwartet `transaction_id` und `status`

### Admin Module

- `sanalpospro-connect`: PayThor CDN React Panel (account & management)
- `sanalpospro-installment`: CRUD UI fuer `sanalpospro_installment`
- `sanalpospro-webhook-log`: Read-only Liste fuer `sanalpospro_webhook_log`

### Storefront Raten-Tab

- Produktdetail-Tabs zeigen Installments, wenn `showInstallmentsTabs` aktiviert ist.
- Themes: `modern` (tabbed) oder `classic` (grid).
- JS Plugin `SanalPosProInstallment` steuert Card-Family Tabs.

### Datenbank

Migrationen erzeugen folgende Tabellen:

- `sanalpospro_webhook_log`
- `sanalpospro_installment`
- `yes` (example entity)

### Build / Assets

Nach Aenderungen an Storefront/Admin ausfuehren:

```
bin/build-administration.sh
bin/build-storefront.sh
```

Alternative nur Storefront:

```
bin/console theme:compile
```

## Turkce

### Ozellikler

- `Sanal Pos Pro` odeme yontemi ve iframe akisi
- IAPI ile gateway listesi ve odeme oturumu olusturan iframe sayfasi
- postMessage ve full-page redirect icin callback handler
- Imzali API webhook ve audit log
- PayThor CDN React panelini yukleyen admin modul
- `sanalpospro_installment` CRUD ve urun detay taksit sekmesi (modern/classic)

### Gereksinimler

- Shopware Core ~6.7.0
- PayThor merchant bilgileri (public/secret key) ve webhook secret

### Kurulum

1. Plugini `custom/plugins/SanalPosPro` altina koyun (bu repo).
2. Kurun ve aktif edin:

```
bin/console plugin:refresh
bin/console plugin:install --activate SanalPosPro
```

3. Migrasyonlari calistirin:

```
bin/console database:migrate SanalPosPro
```

### Yapilandirma (Admin)

Plugin `config.xml` formu getirmez. Ayarlar PayThor CDN paneli ve IAPI bridge uzerinden system config icine kaydedilir.

- Admin > Extensions > SanalPos Pro > Account & Management
- Ornek config anahtarlari:
  - `SanalPosPro.config.publicApiKey`
  - `SanalPosPro.config.secretApiKey`
  - `SanalPosPro.config.appId`
  - `SanalPosPro.config.accessToken`
  - `SanalPosPro.config.webhookSecret`
  - `SanalPosPro.config.orderStatus`
  - `SanalPosPro.config.currencyConvert`
  - `SanalPosPro.config.showInstallmentsTabs`
  - `SanalPosPro.config.paymentPageTheme`
  - `SanalPosPro.config.installments`

### Odeme Akisi (Storefront)

1. Checkout payment handler `/sanalpospro/iframe/{transactionId}?returnUrl=...` adresine yonlendirir.
2. Iframe sayfasi `/sanalpospro/iapi/index` ile gateway ceker, odeme oturumu olusturur ve PayThor iframe URL'ini yukler.
3. PayThor callback:
   - postMessage flow: `/sanalpospro/callback` `{ source: 'paythor_sanalpospro', status, reference, message }` gonderen HTML verir.
   - full-page redirect: PayThor `p_id` ekler; controller PayThor API ile dogrular ve basarida `returnUrl&p_id=...` yonlendirir.
4. `SanalPosProPaymentHandler::finalize()` `p_id` varsa odemeyi basarili sayar; yoksa basarisiz.

### Webhook

- `POST /api/sanalpospro/webhook` (onerilir)
  - Signature header: `X-Paythor-Signature` (raw body uzerinden HMAC-SHA256, key = `SanalPosPro.config.webhookSecret`)
  - Idempotent ve `sanalpospro_webhook_log` yazar
- `POST /sanalpospro/webhook` (legacy storefront)
  - Imza dogrulama yok; `transaction_id` ve `status` bekler

### Admin Modulleri

- `sanalpospro-connect`: PayThor CDN React panel (account & management)
- `sanalpospro-installment`: `sanalpospro_installment` CRUD arayuzu
- `sanalpospro-webhook-log`: `sanalpospro_webhook_log` read-only liste

### Storefront Taksit Sekmesi

- `showInstallmentsTabs` acik ise urun detayinda taksit sekmesi gorunur.
- Tema: `modern` (tabbed) veya `classic` (grid).
- JS plugin `SanalPosProInstallment` kart sekmelerini yonetir.

### Veritabani

Migrasyonlar su tabloları olusturur:

- `sanalpospro_webhook_log`
- `sanalpospro_installment`
- `yes` (example entity)

### Build / Assets

Storefront/Admin degisikliklerinden sonra:

```
bin/build-administration.sh
bin/build-storefront.sh
```

Sadece storefront icin:

```
bin/console theme:compile
```

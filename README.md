# EnvoiSMS PHP SDK

[![Packagist version](https://img.shields.io/packagist/v/envoisms/envoisms-php.svg)](https://packagist.org/packages/envoisms/envoisms-php)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/n4ouri/envoisms-php/blob/main/LICENSE)

Official PHP SDK for [EnvoiSMS.ma](https://envoisms.ma) — the direct-operator **SMS**, **WhatsApp Business (WABA)** and **OTP verification** API platform for Morocco (Maroc).

```bash
composer require envoisms/envoisms-php
```

## What is EnvoiSMS.ma?

EnvoiSMS.ma routes transactional and marketing messages through direct connections to Morocco's three mobile operators, plus the official WhatsApp Cloud API — no aggregator, no gray SIM routes.

| Channel | What it's for | Covered by this SDK |
| --- | --- | --- |
| [SMS Direct Opérateurs](https://envoisms.ma/fr/api-sms-maroc/) — IAM, Inwi, Orange | OTP codes, delivery alerts, marketing SMS, from 0.48 MAD/SMS | ✅ `send()`, `sendBulk()` |
| [WhatsApp Business API (Meta WABA)](https://envoisms.ma/fr/whatsapp-business-api-maroc/) | Approved templates, interactive buttons, catalog, multi-agent inbox, from 0.65 MAD/message | ✅ `send(['channel' => 'whatsapp'])` |
| [OTP / 2FA Verification](https://envoisms.ma/fr/services/otp/) | Send + check one-time codes over SMS or WhatsApp | ✅ `sendOtp()`, `checkOtp()` |
| [Numéro Virtuel (+212)](https://envoisms.ma/fr/whatsapp/numero-virtuel/) | Cloud Moroccan business line, no physical SIM, shared team inbox | Manage from the [dashboard](https://envoisms.ma/fr/login/) |
| [Assistant IA Conversationnel](https://envoisms.ma/fr/whatsapp-ai-bot/) | Darija/French AI agent for COD order confirmation & support handoff | Manage from the [dashboard](https://envoisms.ma/fr/login/) |

Numéros Virtuels and the AI assistant are configured from your EnvoiSMS.ma dashboard today; dedicated SDK endpoints for them are on the roadmap. Everything below (`send`, OTP, billing, webhooks) works with the SDK right now.

## Quick Start — Send an SMS

```php
<?php

require_once 'vendor/autoload.php';

use EnvoiSMS\Client;

$client = new Client(getenv('ENVOISMS_API_KEY'));

$response = $client->send([
    'to' => '+212600000000',
    'message' => 'Votre code de vérification est 492018',
    'from' => 'MonBusiness', // validated Sender ID, or omit to use your default
]);

echo 'Message ID: ' . $response['message_id'];
```

## Send a WhatsApp Business Message

Same client, same method — just switch the channel. Requires WhatsApp connected in your [dashboard](https://envoisms.ma/fr/whatsapp/).

```php
$response = $client->send([
    'to' => '+212600000000',
    'message' => 'Bonjour ! Votre commande #89240 a été expédiée.',
    'channel' => 'whatsapp',
]);
```

### Automatic channel fallback (cascade)

Send over WhatsApp and drop back to SMS automatically when a number is unreachable or has no WhatsApp — the same fallback used for VTC riders on flaky mobile data.

```php
$response = $client->send([
    'to' => '+212600000000',
    'message' => 'Votre chauffeur arrive dans 2 minutes.',
    'channel' => 'whatsapp',
    'cascade' => true,
]);
```

## OTP / 2FA Verification

```php
// 1. Send OTP (channel defaults to sms; pass channel => 'whatsapp' to send over WhatsApp instead)
$otpResponse = $client->sendOtp([
    'to' => '+212600000000',
    'brand' => 'MonBusiness',
    'code_length' => 6,
    'expiry' => 600, // seconds
]);

$sessionId = $otpResponse['session_id'];

// 2. Check the code the user typed in
$verifyResult = $client->checkOtp($sessionId, '492018');

if (!empty($verifyResult['verified'])) {
    echo "OTP verified successfully!";
}

// Optional: inspect a session's status without consuming an attempt
$session = $client->getOtpSession($sessionId);
```

## Bulk Sending

```php
$client->sendBulk([
    'messages' => [
        ['to' => '+212600000001', 'message' => 'Promo -20% ce week-end'],
        ['to' => '+212600000002', 'message' => 'Promo -20% ce week-end'],
    ],
    'from' => 'MonBusiness',
]);
```

## Message Status & Delivery

```php
$status = $client->getMessage('msg_123');
$recent = $client->listMessages(50, 0);
```

## Verifying Delivery Webhooks (DLR)

If you configure a delivery-status webhook, verify its `X-EnvoiSMS-Signature` header before trusting the payload:

```php
use EnvoiSMS\Client;

$rawBody = file_get_contents('php://input'); // raw request body, not the parsed array
$isValid = Client::verifyWebhookSignature(
    $rawBody,
    $_SERVER['HTTP_X_ENVOISMS_SIGNATURE'],
    getenv('ENVOISMS_WEBHOOK_SECRET')
);
```

## Account & Billing

```php
$balance = $client->getBalance();
$packs = $client->listPacks();
$paymentMethods = $client->listPaymentMethods();

$client->createTopup(['amount_mad' => 200, 'payment_method' => 'stripe']);
```

## Analytics & API Keys

```php
$stats = $client->analytics(30);
$newKey = $client->createApiKey(['name' => 'Server key']);
```

## Compliance: Opt-outs (STOP)

```php
$client->createOptout('+212600000000');
```

## Error Handling & Retries

The client retries `5xx` responses and cURL errors up to `maxRetries` times (default 2) with exponential backoff, and throws `EnvoiSMSError` — with `statusCode` and `errorCode` properties — on any failure:

```php
use EnvoiSMS\Client;
use EnvoiSMS\EnvoiSMSError;

$client = new Client(getenv('ENVOISMS_API_KEY'), maxRetries: 3, timeoutSeconds: 20);

try {
    $client->send(['to' => '+212600000000', 'message' => 'Test']);
} catch (EnvoiSMSError $e) {
    echo "Send failed ({$e->statusCode} {$e->errorCode}): {$e->getMessage()}";
}
```

## Why teams pick EnvoiSMS.ma over an aggregator

- **Direct routes to IAM, Inwi and Orange** — no international transit hop, no gray-route ban risk.
- **Sub-2-second OTP latency**, measured — aggregators routing through Europe typically land in the 10s+ range.
- **Billing in MAD**, no EUR/USD conversion surprises.
- **Local support** based in Casablanca, not an offshore ticket queue.

See the full breakdown on [envoisms.ma](https://envoisms.ma).

## Documentation & Pricing

- Full API reference: [envoisms.ma/fr/docs](https://envoisms.ma/fr/docs/)
- Pricing & credit packs: [envoisms.ma/fr/tarifs](https://envoisms.ma/fr/tarifs/)
- Real customer use cases: [envoisms.ma/fr/cas-usage](https://envoisms.ma/fr/cas-usage)
- Create a free account (5 MAD credit included): [envoisms.ma/fr/register](https://envoisms.ma/fr/register/)

## Other official SDKs

- Python: [`pip install envoisms`](https://pypi.org/project/envoisms/)
- Node.js / TypeScript: [`npm install envoisms`](https://www.npmjs.com/package/envoisms)
- WooCommerce, Shopify, Zapier and Google Sheets integrations: [envoisms.ma/fr/integrations](https://envoisms.ma/fr/integrations/)

## Support

- Email: [support@envoisms.ma](mailto:support@envoisms.ma)
- Sales: [sales@envoisms.ma](mailto:sales@envoisms.ma)

## License

MIT

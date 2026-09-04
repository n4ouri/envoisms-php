# EnvoiSMS PHP SDK

Official PHP SDK for [EnvoiSMS.ma](https://envoisms.ma) — [Passerelle SMS & WhatsApp Business](https://envoisms.ma/fr/docs) and [API SMS Maroc](https://envoisms.ma/fr/tarifs).

For complete API documentation and integration guides, visit the [Passerelle SMS & WhatsApp Business](https://envoisms.ma/fr/docs). For pricing plans and credit packs, visit [API SMS Maroc](https://envoisms.ma/fr/tarifs).

## Installation

Install via Composer:

```bash
composer require envoisms/envoisms-php
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use EnvoiSMS\Client;

$client = new Client(getenv('ENVOISMS_API_KEY'));

// Send SMS
$response = $client->send([
    'to' => '+212600000000',
    'message' => 'Votre code de vérification est 492018',
    'from' => 'MonBusiness',
]);

echo 'Message ID: ' . $response['id'];
```

## OTP Verification

```php
// 1. Send OTP
$otpResponse = $client->sendOtp([
    'to' => '+212600000000',
    'brand' => 'MonBusiness',
    'code_length' => 6,
    'expiry' => 600,
]);

$sessionId = $otpResponse['session_id'];

// 2. Check OTP Code
$verifyResult = $client->checkOtp($sessionId, '492018');

if (!empty($verifyResult['verified'])) {
    echo "OTP verified successfully!";
}
```

## Documentation & Tarifs

- Documentation technique & intégration : [Passerelle SMS & WhatsApp Business](https://envoisms.ma/fr/docs)
- Grille tarifaire et packs de crédits : [API SMS Maroc](https://envoisms.ma/fr/tarifs)

## License

MIT

<?php

declare(strict_types=1);

namespace EnvoiSMS;

use RuntimeException;

class EnvoiSMSError extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $errorCode = null
    ) {
        parent::__construct($message, $statusCode);
    }
}

final class Client
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.envoisms.ma',
        private readonly int $maxRetries = 2,
        private readonly int $timeoutSeconds = 15
    ) {}

    // --- Messages ---
    public function send(array $payload): array
    {
        return $this->request('POST', '/v1/messages', $payload);
    }

    public function sendBulk(array $payload): array
    {
        return $this->request('POST', '/v1/messages/bulk', $payload);
    }

    public function getMessage(string $messageId): array
    {
        return $this->request('GET', '/v1/messages/' . urlencode($messageId));
    }

    public function listMessages(int $limit = 50, int $offset = 0): array
    {
        return $this->request('GET', "/v1/messages?limit={$limit}&offset={$offset}");
    }

    // --- Verify / OTP ---
    public function sendOtp(array $payload): array
    {
        return $this->request('POST', '/v1/verify/send', $payload);
    }

    public function checkOtp(string $sessionId, string $code): array
    {
        return $this->request('POST', '/v1/verify/check', ['session_id' => $sessionId, 'code' => $code]);
    }

    public function getOtpSession(string $sessionId): array
    {
        return $this->request('GET', '/v1/verify/' . urlencode($sessionId));
    }

    // --- Account & Billing ---
    public function getBalance(): array
    {
        return $this->request('GET', '/v1/billing/balance');
    }

    public function listPacks(): array
    {
        return $this->request('GET', '/v1/billing/packs');
    }

    public function listPaymentMethods(): array
    {
        return $this->request('GET', '/v1/billing/payment-methods');
    }

    public function createTopup(array $payload): array
    {
        return $this->request('POST', '/v1/billing/topups', $payload);
    }

    // --- Analytics & API Keys ---
    public function analytics(int $days = 30): array
    {
        return $this->request('GET', '/v1/analytics?days=' . $days);
    }

    public function createApiKey(array $payload): array
    {
        return $this->request('POST', '/v1/api-keys', $payload);
    }

    // --- Compliance ---
    public function createOptout(string $phone): array
    {
        return $this->request('POST', '/v1/optouts', ['phone' => $phone]);
    }

    // --- Webhook Signature Verification ---
    public static function verifyWebhookSignature(
        string $rawBody,
        string $signatureHeader,
        string $secret,
        int $toleranceSeconds = 300
    ): bool {
        if (empty($rawBody) || empty($signatureHeader) || empty($secret)) {
            return false;
        }

        // Timestamped format: t=1234567890,v1=abcdef...
        if (str_contains($signatureHeader, 't=') && str_contains($signatureHeader, 'v1=')) {
            $parts = [];
            foreach (explode(',', $signatureHeader) as $pair) {
                $item = explode('=', trim($pair), 2);
                if (count($item) === 2) {
                    $parts[$item[0]] = $item[1];
                }
            }

            $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
            $signature = $parts['v1'] ?? '';

            if (!$timestamp || empty($signature)) {
                return false;
            }

            if (abs(time() - $timestamp) > $toleranceSeconds) {
                return false;
            }

            $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);
            return hash_equals($expected, $signature);
        }

        // Direct sha256=... header
        $cleanSig = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $cleanSig);
    }

    // --- Internal Request Helper with Retries ---
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $lastError = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'User-Agent: EnvoiSMS-PHPSDK/1.1.0',
                ],
            ]);

            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $lastError = new EnvoiSMSError("cURL error: {$curlErr}", 0);
                if ($attempt < $this->maxRetries) {
                    usleep((int) (pow(2, $attempt) * 500000));
                    continue;
                }
                break;
            }

            if ($status >= 500 && $attempt < $this->maxRetries) {
                usleep((int) (pow(2, $attempt) * 500000));
                continue;
            }

            $data = json_decode((string) $body, true) ?: [];
            if ($status >= 400) {
                throw new EnvoiSMSError(
                    $data['error']['message'] ?? "EnvoiSMS API error ({$status})",
                    $status,
                    $data['error']['code'] ?? null
                );
            }

            return $data;
        }

        throw $lastError ?? new EnvoiSMSError('Request failed');
    }
}

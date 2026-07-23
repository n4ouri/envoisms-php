<?php

namespace EnvoiSMS;

use RuntimeException;

class Client
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.envoisms.ma')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function send(array $payload): array
    {
        return $this->request('POST', '/v1/messages', $payload);
    }

    public function sendBulk(array $payload): array
    {
        return $this->request('POST', '/v1/messages/bulk', $payload);
    }

    public function sendOtp(array $payload): array
    {
        return $this->request('POST', '/v1/verify/send', $payload);
    }

    public function checkOtp(string $sessionId, string $code): array
    {
        return $this->request('POST', '/v1/verify/check', [
            'session_id' => $sessionId,
            'code' => $code,
        ]);
    }

    public function analytics(int $days = 30): array
    {
        return $this->request('GET', '/v1/analytics?days=' . $days);
    }

    public function listMessages(int $limit = 50): array
    {
        return $this->request('GET', '/v1/messages?limit=' . $limit);
    }

    public function createApiKey(array $payload): array
    {
        return $this->request('POST', '/v1/api-keys', $payload);
    }

    public function createOptout(string $phone): array
    {
        return $this->request('POST', '/v1/optouts', ['phone' => $phone]);
    }

    public function listPaymentMethods(): array
    {
        return $this->request('GET', '/v1/billing/payment-methods');
    }

    public function createTopup(array $payload): array
    {
        return $this->request('POST', '/v1/billing/topups', $payload);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($payload !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL request failed: " . $errorMsg);
        }

        curl_close($ch);

        $data = json_decode((string) $response, true) ?: [];

        if ($status >= 400) {
            $message = $data['error']['message'] ?? "EnvoiSMS API error status {$status}";
            throw new RuntimeException($message, $status);
        }

        return $data;
    }
}

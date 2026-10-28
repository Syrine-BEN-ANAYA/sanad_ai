<?php

declare(strict_types=1);

namespace SanadAi\Integrations;

use RuntimeException;

final class WhatsAppClient
{
    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return (bool)$this->config['enabled']
            && $this->config['phone_number_id'] !== ''
            && $this->config['access_token'] !== '';
    }

    public function sendText(string $to, string $message): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException('WhatsApp integration is not configured.');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->config['graph_version'],
            $this->config['phone_number_id']
        );

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error !== '') {
            throw new RuntimeException('WhatsApp transport failed.');
        }

        $data = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('WhatsApp API rejected the message.');
        }

        return is_array($data) ? $data : [];
    }

    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        if ($mode !== 'subscribe' || $token === '' || !hash_equals((string)$this->config['verify_token'], $token)) {
            return null;
        }
        return $challenge;
    }

    /**
     * Validate Meta's X-Hub-Signature-256 header against the raw request body.
     * Fails closed when no app secret is configured.
     */
    public function isValidSignature(string $rawBody, string $header): bool
    {
        $secret = (string)($this->config['app_secret'] ?? '');
        if ($secret === '' || !str_starts_with($header, 'sha256=')) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $header);
    }
}

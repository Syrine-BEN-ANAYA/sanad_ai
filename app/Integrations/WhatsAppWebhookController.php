<?php

declare(strict_types=1);

namespace SanadAi\Integrations;

use PDO;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Support\Uuid;

final class WhatsAppWebhookController
{
    public function __construct(
        private PDO $pdo,
        private WhatsAppClient $client,
        private Response $response,
        private int $tenantId,
    ) {}

    public function verify(Request $request): void
    {
        $challenge = $this->client->verifyWebhook(
            (string)$request->query('hub_mode', $request->query('hub.mode', '')),
            (string)$request->query('hub_verify_token', $request->query('hub.verify_token', '')),
            (string)$request->query('hub_challenge', $request->query('hub.challenge', '')),
        );

        if ($challenge === null) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
    }

    public function receive(Request $request): void
    {
        $raw = (string)file_get_contents('php://input');

        // Default-deny: reject any payload whose HMAC signature is missing or invalid.
        $signature = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
        if (!$this->client->isValidSignature($raw, $signature)) {
            $this->response->json(['success' => false, 'message' => 'Invalid signature.'], 403);
            return;
        }

        $decoded = json_decode($raw, true);
        $payload = is_array($decoded) ? $decoded : [];
        if ($this->tenantId <= 0) {
            $this->response->json(['success'=>false,'message'=>'WhatsApp tenant is not configured.'],500);
            return;
        }

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                foreach (($change['value']['messages'] ?? []) as $message) {
                    $from = (string)($message['from'] ?? '');
                    if ($from === '') continue;
                    $body = (string)($message['text']['body'] ?? '');
                    $type = (string)($message['type'] ?? 'text');
                    $st = $this->pdo->prepare('INSERT INTO whatsapp_conversations (public_id,tenant_id,phone,direction,message_type,body,status,created_at) VALUES (:public_id,:tenant,:phone,:direction,:message_type,:body,:status,:created_at)');
                    $st->execute(['public_id'=>Uuid::v4(),'tenant'=>$this->tenantId,'phone'=>$from,'direction'=>'inbound','message_type'=>$type,'body'=>$body,'status'=>'open','created_at'=>date('Y-m-d H:i:s')]);
                }
            }
        }

        $this->response->json(['success'=>true]);
    }
}

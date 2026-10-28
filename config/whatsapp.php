<?php

declare(strict_types=1);

return [
    'enabled' => filter_var($_ENV['WHATSAPP_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'graph_version' => $_ENV['WHATSAPP_GRAPH_VERSION'] ?? 'v23.0',
    'phone_number_id' => $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '',
    'access_token' => $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? '',
    'app_secret' => $_ENV['WHATSAPP_APP_SECRET'] ?? '',
    'verify_token' => $_ENV['WHATSAPP_VERIFY_TOKEN'] ?? '',
    'webhook_tenant_id' => (int)($_ENV['WHATSAPP_WEBHOOK_TENANT_ID'] ?? 0),
];

<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use PDO;
use SanadAi\Http\Response;

/**
 * Public list of demo logins for the login page.
 *
 * Returns an empty list (never an error) when demo logins are off:
 * APP_ENV=production, DEMO_LOGINS=false, or accounts not seeded.
 * Only accounts that really exist and are active are listed.
 */
final class DemoAccountsController
{
    /**
     * @param array{tenant: array{slug: string}, password: string, accounts: list<array{username: string, full_name: string, role: string}>} $config
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Response $response,
        private readonly array $config,
    ) {
    }

    public function index(): void
    {
        $this->response->json(['success' => true, 'accounts' => $this->accounts()]);
    }

    /**
     * @return list<array{username: string, password: string, full_name: string, role: string}>
     */
    private function accounts(): array
    {
        $flag = strtolower((string) ($_ENV['DEMO_LOGINS'] ?? 'true'));

        if (APP_ENV === 'production' || in_array($flag, ['false', '0', 'off', 'no'], true)) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT u.username
            FROM users u
            INNER JOIN tenants t ON t.id = u.tenant_id
            WHERE t.slug = :slug
              AND t.status = 'active'
              AND u.status = 'active'
        ");

        $stmt->execute(['slug' => $this->config['tenant']['slug']]);

        $existing = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        $out = [];

        foreach ($this->config['accounts'] as $account) {
            if (isset($existing[$account['username']])) {
                $out[] = [
                    'username' => $account['username'],
                    'password' => $this->config['password'],
                    'full_name' => $account['full_name'],
                    'role' => $account['role'],
                ];
            }
        }

        return $out;
    }
}

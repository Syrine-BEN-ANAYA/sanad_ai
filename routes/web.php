<?php

declare(strict_types=1);

use SanadAi\Auth\AccountController;
use SanadAi\Auth\AuthenticationService;
use SanadAi\Auth\Authorization;
use SanadAi\Auth\CookieManager;
use SanadAi\Auth\CsrfManager;
use SanadAi\Auth\CsrfMiddleware;
use SanadAi\Auth\DemoAccountsController;
use SanadAi\Auth\LoginThrottle;
use SanadAi\Auth\PermissionMiddleware;
use SanadAi\Auth\SessionContext;
use SanadAi\Auth\SessionManager;
use SanadAi\Auth\SessionMiddleware;
use SanadAi\Clients\ClientController;
use SanadAi\Clients\ClientRepository;
use SanadAi\Clients\ClientService;
use SanadAi\Config\Database;
use SanadAi\Documents\DocumentController;
use SanadAi\Documents\DocumentManager;
use SanadAi\Documents\DocumentRepository;
use SanadAi\Documents\DocumentStorage;
use SanadAi\Documents\DocumentValidator;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Http\Router;
use SanadAi\Modules\ModuleController;
use SanadAi\Integrations\WhatsAppClient;
use SanadAi\Integrations\WhatsAppWebhookController;
use SanadAi\Services\MinistryRepository;
use SanadAi\Services\ServiceController;
use SanadAi\Services\ServiceManager;
use SanadAi\Services\ServiceRepository;
use SanadAi\Tenancy\TenantContext;
use SanadAi\Transactions\TransactionController;
use SanadAi\Transactions\TransactionManager;
use SanadAi\Transactions\TransactionRepository;
use SanadAi\Users\UserController;
use SanadAi\Users\UserRepository;
use SanadAi\Users\UserService;
use SanadAi\Support\LocalStore;

/*
 * Composition root: every object is built here once per request and
 * injected, so the domain code never reaches for globals.
 */

$pdo = Database::connection();
$localStore = new LocalStore();

$request = new Request();
$response = new Response();

$tenantContext = new TenantContext();
$sessionContext = new SessionContext();

$sessionManager = new SessionManager($localStore);
$csrfManager = new CsrfManager($localStore);

// "Secure" cookies whenever the request really is HTTPS (also behind a
// proxy / cPanel's SSL terminator) or the app runs in production.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    || APP_ENV === 'production';

$cookieManager = new CookieManager(name: 'SANAD_SESSION', secure: $isHttps);

$sessionMiddleware = new SessionMiddleware(
    sessionManager: $sessionManager,
    cookies: $cookieManager,
    csrfManager: $csrfManager,
    pdo: $pdo,
    tenantContext: $tenantContext,
    sessionContext: $sessionContext
);

$csrfMiddleware = new CsrfMiddleware(csrfManager: $csrfManager, cookies: $cookieManager);

$authorization = new Authorization(pdo: $pdo, tenantContext: $tenantContext);

$authentication = new AuthenticationService($pdo, $sessionManager, $csrfManager, $tenantContext);

$userRepository = new UserRepository($pdo);
$userService = new UserService($userRepository);
$moduleController = new ModuleController($pdo, $tenantContext, $sessionContext, $response);
$whatsappConfig = require __DIR__ . '/../config/whatsapp.php';
$whatsappClient = new WhatsAppClient($whatsappConfig);
$whatsappWebhook = new WhatsAppWebhookController($pdo, $whatsappClient, $response, (int)($whatsappConfig['webhook_tenant_id'] ?? 0));

$demoAccountsController = new DemoAccountsController($pdo, $response, require __DIR__ . '/../config/demo.php');

$clientRepository = new ClientRepository($pdo);
$serviceRepository = new ServiceRepository($pdo);
$ministryRepository = new MinistryRepository($pdo);
$transactionRepository = new TransactionRepository($pdo);

$transactionManager = new TransactionManager(
    $transactionRepository,
    $clientRepository,
    $serviceRepository,
    $pdo
);

$documentManager = new DocumentManager(
    $pdo,
    new DocumentRepository($pdo),
    $transactionRepository,
    new DocumentValidator(),
    $documentStorage = new DocumentStorage(dirname(__DIR__))
);

$accountController = new AccountController(
    $authentication,
    $sessionMiddleware,
    $sessionContext,
    $cookieManager,
    $authorization,
    new LoginThrottle($localStore),
    $userService,
    $pdo,
    $response
);

$clientController = new ClientController(
    new ClientService($clientRepository),
    $clientRepository,
    $tenantContext,
    $response
);

$serviceController = new ServiceController(
    new ServiceManager($serviceRepository, $ministryRepository),
    $ministryRepository,
    $tenantContext,
    $response
);

$transactionController = new TransactionController(
    $transactionManager,
    $transactionRepository,
    $clientRepository,
    $serviceRepository,
    $userRepository,
    $tenantContext,
    $sessionContext,
    $response
);

$documentController = new DocumentController(
    $documentManager,
    $documentStorage,
    $tenantContext,
    $sessionContext,
    $response
);

$userController = new UserController(
    $userService,
    $userRepository,
    $tenantContext,
    $sessionContext,
    $response
);

return static function (Router $router) use (
    $request,
    $response,
    $sessionMiddleware,
    $csrfMiddleware,
    $authorization,
    $sessionContext,
    $accountController,
    $demoAccountsController,
    $clientController,
    $serviceController,
    $transactionController,
    $documentController,
    $userController,
    $moduleController,
    $whatsappWebhook
): void {
    /*
     * Middleware stack for a protected route:
     *   1. valid session (401)
     *   2. CSRF token on state-changing requests (403)
     *   3. permission (403)
     *
     * Everything is default-deny: a route without a permission is
     * only reachable by an authenticated user, never anonymously.
     *
     * @return list<callable>
     */
    $guard = static function (?string $permission = null, bool $csrf = false) use (
        $sessionMiddleware,
        $csrfMiddleware,
        $authorization,
        $sessionContext
    ): array {
        $stack = [static function () use ($sessionMiddleware): void {
            $sessionMiddleware->authenticate();
        }];

        if ($csrf) {
            $stack[] = static function () use ($csrfMiddleware): void {
                $csrfMiddleware->validate();
            };
        }

        if ($permission !== null) {
            $stack[] = new PermissionMiddleware($authorization, $sessionContext, $permission);
        }

        return $stack;
    };

    /*
     * ---------------------------------------------------------
     * Public
     * ---------------------------------------------------------
     */

    $router->get('/health', static function () use ($response): void {
        $response->json(['success' => true, 'status' => 'healthy']);
    });

    $router->get('/webhooks/whatsapp', static function () use ($whatsappWebhook, $request): void {
        $whatsappWebhook->verify($request);
    });

    $router->post('/webhooks/whatsapp', static function () use ($whatsappWebhook, $request): void {
        $whatsappWebhook->receive($request);
    });

    $router->get('/api/demo-accounts', static function () use ($demoAccountsController): void {
        $demoAccountsController->index();
    });

    $router->post('/api/login', static function () use ($accountController, $request): void {
        $accountController->login($request);
    });

    /*
     * ---------------------------------------------------------
     * Account
     * ---------------------------------------------------------
     */

    $router->post('/api/logout', static function () use ($accountController, $request): void {
        $accountController->logout($request);
    }, $guard(null, true));

    $router->get('/api/me', static function () use ($accountController, $request): void {
        $accountController->me($request);
    }, $guard());

    $router->post('/api/me/password', static function () use ($accountController, $request): void {
        $accountController->changePassword($request);
    }, $guard(null, true));

    /*
     * ---------------------------------------------------------
     * Clients
     * ---------------------------------------------------------
     */

    $router->get('/api/clients', static function () use ($clientController, $request): void {
        $clientController->index($request);
    }, $guard('clients.view'));

    $router->post('/api/clients', static function () use ($clientController, $request): void {
        $clientController->store($request);
    }, $guard('clients.create', true));

    $router->get('/api/clients/{publicId}', static function (string $id) use ($clientController, $request): void {
        $clientController->show($request, $id);
    }, $guard('clients.view'));

    $router->put('/api/clients/{publicId}', static function (string $id) use ($clientController, $request): void {
        $clientController->update($request, $id);
    }, $guard('clients.update', true));

    $router->delete('/api/clients/{publicId}', static function (string $id) use ($clientController, $request): void {
        $clientController->destroy($request, $id);
    }, $guard('clients.delete', true));

    /*
     * ---------------------------------------------------------
     * Services & ministries
     * ---------------------------------------------------------
     */

    $router->get('/api/ministries', static function () use ($serviceController, $request): void {
        $serviceController->ministries($request);
    }, $guard('services.view'));

    $router->get('/api/services', static function () use ($serviceController, $request): void {
        $serviceController->index($request);
    }, $guard('services.view'));

    $router->post('/api/services', static function () use ($serviceController, $request): void {
        $serviceController->store($request);
    }, $guard('services.manage', true));

    $router->get('/api/services/{publicId}', static function (string $id) use ($serviceController, $request): void {
        $serviceController->show($request, $id);
    }, $guard('services.view'));

    $router->put('/api/services/{publicId}', static function (string $id) use ($serviceController, $request): void {
        $serviceController->update($request, $id);
    }, $guard('services.manage', true));

    $router->delete('/api/services/{publicId}', static function (string $id) use ($serviceController, $request): void {
        $serviceController->destroy($request, $id);
    }, $guard('services.manage', true));

    /*
     * ---------------------------------------------------------
     * Transactions
     * ---------------------------------------------------------
     */

    $router->get('/api/transactions', static function () use ($transactionController, $request): void {
        $transactionController->index($request);
    }, $guard('transactions.view'));

    $router->get('/api/transactions/stats', static function () use ($transactionController, $request): void {
        $transactionController->stats($request);
    }, $guard('transactions.view'));

    $router->post('/api/transactions', static function () use ($transactionController, $request): void {
        $transactionController->store($request);
    }, $guard('transactions.create', true));

    $router->get('/api/transactions/{publicId}', static function (string $id) use ($transactionController, $request): void {
        $transactionController->show($request, $id);
    }, $guard('transactions.view'));

    $router->put('/api/transactions/{publicId}', static function (string $id) use ($transactionController, $request): void {
        $transactionController->update($request, $id);
    }, $guard('transactions.update', true));

    $router->post('/api/transactions/{publicId}/status', static function (string $id) use ($transactionController, $request): void {
        $transactionController->changeStatus($request, $id);
    }, $guard('transactions.update', true));

    /*
     * ---------------------------------------------------------
     * Documents
     * ---------------------------------------------------------
     */

    $router->get('/api/transactions/{publicId}/documents', static function (string $id) use ($documentController, $request): void {
        $documentController->index($request, $id);
    }, $guard('documents.view'));

    $router->post('/api/transactions/{publicId}/documents', static function (string $id) use ($documentController, $request): void {
        $documentController->upload($request, $id);
    }, $guard('documents.upload', true));

    $router->get('/api/documents/{publicId}/download', static function (string $id) use ($documentController, $request): void {
        $documentController->download($request, $id);
    }, $guard('documents.view'));

    $router->post('/api/documents/{publicId}/status', static function (string $id) use ($documentController, $request): void {
        $documentController->changeStatus($request, $id);
    }, $guard('documents.verify', true));

    /*
     * ---------------------------------------------------------
     * Users & roles
     * ---------------------------------------------------------
     */

    $router->get('/api/users', static function () use ($userController, $request): void {
        $userController->index($request);
    }, $guard('users.view'));

    $router->get('/api/users/assignable', static function () use ($userController, $request): void {
        $userController->assignable($request);
    }, $guard('transactions.view'));

    $router->post('/api/users', static function () use ($userController, $request): void {
        $userController->store($request);
    }, $guard('users.create', true));

    $router->put('/api/users/{publicId}', static function (string $id) use ($userController, $request): void {
        $userController->update($request, $id);
    }, $guard('users.update', true));

    $router->get('/api/roles', static function () use ($userController, $request): void {
        $userController->roles($request);
    }, $guard('users.view'));

    /*
     * ---------------------------------------------------------
     * Operational modules: finance, HR, clearance, communication,
     * notifications, reports, WhatsApp inbox and AI reports.
     * ---------------------------------------------------------
     */

    $router->get('/api/dashboard/summary', static function () use ($moduleController, $request): void {
        $moduleController->dashboard($request);
    }, $guard('transactions.view'));

    $moduleRoutes = [
        'payments' => 'payments.view',
        'invoices' => 'payments.view',
        'attendance' => 'attendance.view',
        'clearance' => 'clearance.view',
        'discussions' => 'discussions.view',
        'notifications' => 'notifications.view',
        'whatsapp' => 'whatsapp.view',
        'ai_reports' => 'reports.view',
    ];

    foreach ($moduleRoutes as $module => $permission) {
        $router->get('/api/'.$module, static function () use ($moduleController, $request, $module): void {
            $moduleController->list($request, $module);
        }, $guard($permission));
    }

    $createPermissions = [
        'payments' => 'payments.create',
        'invoices' => 'payments.create',
        'attendance' => 'attendance.manage',
        'clearance' => 'clearance.manage',
        'discussions' => 'discussions.manage',
        'notifications' => 'notifications.manage',
        'whatsapp' => 'whatsapp.reply',
        'ai_reports' => 'reports.export',
    ];

    foreach ($createPermissions as $module => $permission) {
        $router->post('/api/'.$module, static function () use ($moduleController, $request, $module): void {
            $moduleController->store($request, $module);
        }, $guard($permission, true));
    }

    $updatePermissions = [
        'payments' => 'payments.update',
        'invoices' => 'payments.update',
        'attendance' => 'attendance.manage',
        'clearance' => 'clearance.manage',
        'discussions' => 'discussions.manage',
        'whatsapp' => 'whatsapp.reply',
        'ai_reports' => 'reports.export',
    ];

    foreach ($updatePermissions as $module => $permission) {
        $router->put('/api/'.$module.'/{publicId}', static function (string $id) use ($moduleController, $request, $module): void {
            $moduleController->update($request, $module, $id);
        }, $guard($permission, true));
    }

    $router->post('/api/notifications/{publicId}/read', static function (string $id) use ($moduleController, $request): void {
        $moduleController->markRead($request, $id);
    }, $guard('notifications.view', true));

    $router->get('/api/reports/summary', static function () use ($moduleController, $request): void {
        $moduleController->report($request);
    }, $guard('reports.view'));

    $router->post('/api/reports/ai', static function () use ($moduleController, $request): void {
        $moduleController->generateAiReport($request);
    }, $guard('reports.export', true));
};

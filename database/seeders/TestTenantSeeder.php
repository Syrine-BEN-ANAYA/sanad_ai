<?php

declare(strict_types=1);

namespace SanadAi\Database\Seeders;

use PDO;
use SanadAi\Support\Uuid;

final class TestTenantSeeder
{
    public static function run(PDO $pdo): void
    {
        $pdo->beginTransaction();

        try {
            self::ensureTenant($pdo, 1, 'Placeholder Office 1', 'placeholder-office-1');
            self::ensureTenant($pdo, 2, 'Placeholder Office 2', 'placeholder-office-2');
            self::ensureTenant($pdo, 3, 'Alpha Office', 'alpha-office');
            self::ensureTenant($pdo, 4, 'Beta Office', 'beta-office');

            $passwordHash = password_hash('TestPassword123!', PASSWORD_DEFAULT);
            $superAdminRoleId = self::roleId($pdo, 'super_admin');
            $managerRoleId = self::roleId($pdo, 'manager');
            $customerServiceRoleId = self::roleId($pdo, 'customer_service');
            $accountantRoleId = self::roleId($pdo, 'accountant');
            $clearanceRoleId = self::roleId($pdo, 'clearance_employee');

            self::ensureUser(
                $pdo,
                1,
                $passwordHash,
                'platform_admin',
                'Platform Admin',
                $superAdminRoleId,
                null,
                1,
                'super_admin'
            );

            self::ensureUser(
                $pdo,
                2,
                $passwordHash,
                'manager_a',
                'Manager A',
                $managerRoleId,
                3,
                0,
                'manager'
            );

            self::ensureUser(
                $pdo,
                3,
                $passwordHash,
                'manager_b',
                'Manager B',
                $managerRoleId,
                4,
                0,
                'manager'
            );

            self::ensureUser(
                $pdo,
                4,
                $passwordHash,
                'customer_service_a',
                'Customer Service A',
                $customerServiceRoleId,
                3,
                0,
                'customer_service'
            );

            self::ensureUser(
                $pdo,
                5,
                $passwordHash,
                'accountant_a',
                'Accountant A',
                $accountantRoleId,
                3,
                0,
                'accountant'
            );

            self::ensureUser(
                $pdo,
                6,
                $passwordHash,
                'clearance_a',
                'Clearance Employee A',
                $clearanceRoleId,
                3,
                0,
                'clearance_employee'
            );

            self::ensureUser(
                $pdo,
                7,
                $passwordHash,
                'customer_service_b',
                'Customer Service B',
                $customerServiceRoleId,
                4,
                0,
                'customer_service'
            );

            self::ensureUser(
                $pdo,
                8,
                $passwordHash,
                'accountant_b',
                'Accountant B',
                $accountantRoleId,
                4,
                0,
                'accountant'
            );

            self::ensureUser(
                $pdo,
                9,
                $passwordHash,
                'clearance_b',
                'Clearance Employee B',
                $clearanceRoleId,
                4,
                0,
                'clearance_employee'
            );

            $clientAId = self::ensureClient(
                $pdo,
                1,
                3,
                'person',
                'Tenant A Client',
                null,
                '+96890000001',
                null,
                'tenant-a@example.com',
                'en',
                'Seed client for tenant A.'
            );

            $clientBId = self::ensureClient(
                $pdo,
                2,
                4,
                'person',
                'Tenant B Client',
                null,
                '+96890000002',
                null,
                'tenant-b@example.com',
                'en',
                'Seed client for tenant B.'
            );

            $serviceAId = self::ensureService(
                $pdo,
                1,
                3,
                1,
                'خدمة اختبار ألف',
                'Tenant A Service',
                'وصف الخدمة للمكتب الأول',
                'Description for tenant A service.',
                1500,
                500,
                1440,
                'active'
            );

            $serviceBId = self::ensureService(
                $pdo,
                2,
                4,
                1,
                'خدمة اختبار بيتا',
                'Tenant B Service',
                'وصف الخدمة للمكتب الثاني',
                'Description for tenant B service.',
                2000,
                700,
                1200,
                'active'
            );

            $transactionAId = self::ensureTransaction(
                $pdo,
                3,
                3,
                $clientAId,
                $serviceAId,
                'SAN-1001',
                'Tenant A sample transaction',
                'Seeded sample transaction for tenant A.',
                'open',
                null,
                'dashboard',
                'normal',
                null,
                null
            );

            self::ensureTransaction(
                $pdo,
                10,
                3,
                $clientAId,
                $serviceAId,
                'SAN-1003',
                'Tenant A overdue transaction',
                'Seeded overdue transaction for tenant A.',
                'waiting_client',
                null,
                'dashboard',
                'high',
                null,
                date('Y-m-d H:i:s', strtotime('-2 days'))
            );

            self::ensureDocument(
                $pdo,
                1,
                3,
                $transactionAId,
                'passport',
                'passport.pdf',
                'document-key-tenant-a',
                'application/pdf',
                1024,
                'received',
                null,
                null,
                null
            );

            self::ensureDocument(
                $pdo,
                2,
                4,
                self::ensureTransaction(
                    $pdo,
                    4,
                    4,
                    $clientBId,
                    $serviceBId,
                    'SAN-1002',
                    'Tenant B sample transaction',
                    'Seeded sample transaction for tenant B.',
                    'open',
                    null,
                    'dashboard',
                    'normal',
                    null,
                    null
                ),
                'passport',
                'beta-passport.pdf',
                'document-key-tenant-b',
                'application/pdf',
                900,
                'received',
                null,
                null,
                null
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private static function roleId(PDO $pdo, string $slug): int
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug AND tenant_id IS NULL LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new \RuntimeException("System role '{$slug}' not found.");
        }

        return (int) $id;
    }

    private static function ensureTenant(PDO $pdo, int $id, string $name, string $slug): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO tenants (id, public_id, name, slug, status)
            VALUES (:id, :public_id, :name, :slug, "active")
            ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), status = VALUES(status)'
        );

        $stmt->execute([
            'id' => $id,
            'public_id' => Uuid::v4(),
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private static function ensureUser(
        PDO $pdo,
        int $id,
        string $passwordHash,
        string $username,
        string $fullName,
        int $roleId,
        ?int $tenantId,
        int $isPlatformAdmin,
        string $roleName
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO users (id, public_id, tenant_id, role_id, is_platform_admin, username, password_hash, full_name, role, status)
            VALUES (:id, :public_id, :tenant_id, :role_id, :is_platform_admin, :username, :password_hash, :full_name, :role, "active")
            ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), role_id = VALUES(role_id), is_platform_admin = VALUES(is_platform_admin), password_hash = VALUES(password_hash), full_name = VALUES(full_name), role = VALUES(role), status = VALUES(status)'
        );

        $stmt->execute([
            'id' => $id,
            'public_id' => Uuid::v4(),
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'is_platform_admin' => $isPlatformAdmin,
            'username' => $username,
            'password_hash' => $passwordHash,
            'full_name' => $fullName,
            'role' => $roleName,
        ]);
    }

    private static function ensureClient(
        PDO $pdo,
        int $id,
        int $tenantId,
        string $type,
        string $fullName,
        ?string $companyName,
        ?string $phone,
        ?string $whatsapp,
        ?string $email,
        string $language,
        string $notes
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO clients (id, public_id, tenant_id, client_type, full_name, company_name, phone, whatsapp_number, email, preferred_language, notes, status)
            VALUES (:id, :public_id, :tenant_id, :client_type, :full_name, :company_name, :phone, :whatsapp_number, :email, :preferred_language, :notes, "active")
            ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), client_type = VALUES(client_type), full_name = VALUES(full_name), company_name = VALUES(company_name), phone = VALUES(phone), whatsapp_number = VALUES(whatsapp_number), email = VALUES(email), preferred_language = VALUES(preferred_language), notes = VALUES(notes), status = VALUES(status)'
        );

        $publicId = Uuid::v4();
        $stmt->execute([
            'id' => $id,
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'client_type' => $type,
            'full_name' => $fullName,
            'company_name' => $companyName,
            'phone' => $phone,
            'whatsapp_number' => $whatsapp,
            'email' => $email,
            'preferred_language' => $language,
            'notes' => $notes,
        ]);

        return (int) $id;
    }

    private static function ensureService(
        PDO $pdo,
        int $id,
        int $tenantId,
        int $ministryId,
        string $nameAr,
        string $nameEn,
        ?string $descriptionAr,
        ?string $descriptionEn,
        int $officialFee,
        int $officeFee,
        ?int $targetSla,
        string $status
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO services (id, public_id, tenant_id, ministry_id, name_ar, name_en, description_ar, description_en, official_fee_baisa, office_fee_baisa, target_sla_minutes, status)
            VALUES (:id, :public_id, :tenant_id, :ministry_id, :name_ar, :name_en, :description_ar, :description_en, :official_fee_baisa, :office_fee_baisa, :target_sla_minutes, :status)
            ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), ministry_id = VALUES(ministry_id), name_ar = VALUES(name_ar), name_en = VALUES(name_en), description_ar = VALUES(description_ar), description_en = VALUES(description_en), official_fee_baisa = VALUES(official_fee_baisa), office_fee_baisa = VALUES(office_fee_baisa), target_sla_minutes = VALUES(target_sla_minutes), status = VALUES(status)'
        );

        $stmt->execute([
            'id' => $id,
            'public_id' => Uuid::v4(),
            'tenant_id' => $tenantId,
            'ministry_id' => $ministryId,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'official_fee_baisa' => $officialFee,
            'office_fee_baisa' => $officeFee,
            'target_sla_minutes' => $targetSla,
            'status' => $status,
        ]);

        return (int) $id;
    }

    private static function ensureTransaction(
        PDO $pdo,
        int $id,
        int $tenantId,
        int $clientId,
        int $serviceId,
        string $reference,
        string $title,
        ?string $description,
        string $status,
        ?int $assignedUserId,
        string $source,
        string $priority,
        ?string $openedAt,
        ?string $dueAt
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (id, public_id, tenant_id, client_id, service_id, reference, title, description, status, assigned_user_id, source, priority, opened_at, due_at, completed_at, cancelled_at)
            VALUES (:id, :public_id, :tenant_id, :client_id, :service_id, :reference, :title, :description, :status, :assigned_user_id, :source, :priority, :opened_at, :due_at, NULL, NULL)
            ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), client_id = VALUES(client_id), service_id = VALUES(service_id), reference = VALUES(reference), title = VALUES(title), description = VALUES(description), status = VALUES(status), assigned_user_id = VALUES(assigned_user_id), source = VALUES(source), priority = VALUES(priority), opened_at = VALUES(opened_at), due_at = VALUES(due_at)'
        );

        $stmt->execute([
            'id' => $id,
            'public_id' => Uuid::v4(),
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'reference' => $reference,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'assigned_user_id' => $assignedUserId,
            'source' => $source,
            'priority' => $priority,
            'opened_at' => $openedAt ?? date('Y-m-d H:i:s'),
            'due_at' => $dueAt,
        ]);

        $stmtHistory = $pdo->prepare(
            'INSERT INTO transaction_status_history (tenant_id, transaction_id, from_status, to_status, changed_by_user_id, reason)
            VALUES (:tenant_id, :transaction_id, NULL, :to_status, NULL, :reason)
            ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
        );
        $stmtHistory->execute([
            'tenant_id' => $tenantId,
            'transaction_id' => $id,
            'to_status' => $status,
            'reason' => 'seed test transaction',
        ]);

        return (int) $id;
    }

    private static function ensureDocument(
        PDO $pdo,
        int $id,
        int $tenantId,
        int $transactionId,
        string $documentType,
        string $originalName,
        string $storageKey,
        string $mimeType,
        int $sizeBytes,
        string $status,
        ?string $rejectionReason,
        ?int $uploadedByUserId,
        ?int $verifiedByUserId
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO documents (id, public_id, tenant_id, transaction_id, document_type, original_name, storage_key, mime_type, size_bytes, status, rejection_reason, expires_at, uploaded_by_user_id, verified_by_user_id, verified_at)
            VALUES (:id, :public_id, :tenant_id, :transaction_id, :document_type, :original_name, :storage_key, :mime_type, :size_bytes, :status, :rejection_reason, NULL, :uploaded_by_user_id, :verified_by_user_id, NULL)
            ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), transaction_id = VALUES(transaction_id), document_type = VALUES(document_type), original_name = VALUES(original_name), storage_key = VALUES(storage_key), mime_type = VALUES(mime_type), size_bytes = VALUES(size_bytes), status = VALUES(status), rejection_reason = VALUES(rejection_reason), uploaded_by_user_id = VALUES(uploaded_by_user_id), verified_by_user_id = VALUES(verified_by_user_id)'
        );

        $publicId = $documentType === 'passport' && $tenantId === 3
            ? 'f0272d54-f705-41fa-affd-0d40fa1f5f98'
            : Uuid::v4();

        $stmt->execute([
            'id' => $id,
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'transaction_id' => $transactionId,
            'document_type' => $documentType,
            'original_name' => $originalName,
            'storage_key' => $storageKey,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'status' => $status,
            'rejection_reason' => $rejectionReason,
            'uploaded_by_user_id' => $uploadedByUserId,
            'verified_by_user_id' => $verifiedByUserId,
        ]);
    }
}

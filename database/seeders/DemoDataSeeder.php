<?php

declare(strict_types=1);

namespace SanadAi\Database\Seeders;

use PDO;
use SanadAi\Support\Uuid;

final class DemoDataSeeder
{
    public static function run(PDO $pdo): void
    {
        $tenantId = (int) $pdo->query(
            "SELECT id FROM tenants WHERE slug = 'demo-office' LIMIT 1"
        )->fetchColumn();

        if ($tenantId <= 0) {
            return;
        }

        $clientStmt = $pdo->prepare(
            'INSERT INTO clients (public_id, tenant_id, client_type, full_name, company_name, phone, email, preferred_language, notes, status)
            VALUES (:public_id, :tenant_id, :client_type, :full_name, :company_name, :phone, :email, :preferred_language, :notes, "active")
            ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), company_name = VALUES(company_name), phone = VALUES(phone), email = VALUES(email), preferred_language = VALUES(preferred_language), notes = VALUES(notes), status = VALUES(status)'
        );

        $serviceStmt = $pdo->prepare(
            'INSERT INTO services (public_id, tenant_id, ministry_id, name_ar, name_en, description_ar, description_en, official_fee_baisa, office_fee_baisa, target_sla_minutes, status)
            VALUES (:public_id, :tenant_id, :ministry_id, :name_ar, :name_en, :description_ar, :description_en, :official_fee_baisa, :office_fee_baisa, :target_sla_minutes, "active")
            ON DUPLICATE KEY UPDATE ministry_id = VALUES(ministry_id), name_ar = VALUES(name_ar), name_en = VALUES(name_en), description_ar = VALUES(description_ar), description_en = VALUES(description_en), official_fee_baisa = VALUES(official_fee_baisa), office_fee_baisa = VALUES(office_fee_baisa), target_sla_minutes = VALUES(target_sla_minutes), status = VALUES(status)'
        );

        $ministryId = (int) $pdo->query(
            'SELECT id FROM ministries ORDER BY id ASC LIMIT 1'
        )->fetchColumn();

        $clientStmt->execute([
            'public_id' => Uuid::v4(),
            'tenant_id' => $tenantId,
            'client_type' => 'person',
            'full_name' => 'Demo Client',
            'company_name' => null,
            'phone' => '+96891234567',
            'email' => 'demo.client@example.com',
            'preferred_language' => 'en',
            'notes' => 'Seeded demo client.',
        ]);

        if ($ministryId > 0) {
            $serviceStmt->execute([
                'public_id' => Uuid::v4(),
                'tenant_id' => $tenantId,
                'ministry_id' => $ministryId,
                'name_ar' => 'خدمة تجريبية',
                'name_en' => 'Demo Service',
                'description_ar' => 'خدمة تجريبية مكتوبة بالمعيار المحلي.',
                'description_en' => 'Demo service seeded for the office.',
                'official_fee_baisa' => 1500,
                'office_fee_baisa' => 500,
                'target_sla_minutes' => 1440,
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace SanadAi\Database\Seeders;

use PDO;
use SanadAi\Support\Uuid;

final class RbacSeeder
{
    /**
     * @return array{roles: int, permissions: int}
     */
    public static function run(PDO $pdo): array
    {
        $permissions = [
            ['slug' => 'clients.view', 'name' => 'View clients'],
            ['slug' => 'clients.create', 'name' => 'Create clients'],
            ['slug' => 'clients.update', 'name' => 'Update clients'],
            ['slug' => 'clients.delete', 'name' => 'Delete clients'],
            ['slug' => 'services.view', 'name' => 'View services'],
            ['slug' => 'services.manage', 'name' => 'Manage services'],
            ['slug' => 'transactions.view', 'name' => 'View transactions'],
            ['slug' => 'transactions.create', 'name' => 'Create transactions'],
            ['slug' => 'transactions.update', 'name' => 'Update transactions'],
            ['slug' => 'documents.view', 'name' => 'View documents'],
            ['slug' => 'documents.upload', 'name' => 'Upload documents'],
            ['slug' => 'documents.verify', 'name' => 'Verify documents'],
            ['slug' => 'users.view', 'name' => 'View users'],
            ['slug' => 'users.create', 'name' => 'Create users'],
            ['slug' => 'users.update', 'name' => 'Update users'],
            ['slug' => 'payments.view', 'name' => 'View payments'],
            ['slug' => 'payments.create', 'name' => 'Create payments'],
            ['slug' => 'payments.update', 'name' => 'Update payments'],
            ['slug' => 'attendance.view', 'name' => 'View attendance'],
            ['slug' => 'attendance.manage', 'name' => 'Manage attendance'],
            ['slug' => 'clearance.view', 'name' => 'View clearances'],
            ['slug' => 'clearance.manage', 'name' => 'Manage clearances'],
            ['slug' => 'discussions.view', 'name' => 'View discussions'],
            ['slug' => 'discussions.manage', 'name' => 'Manage discussions'],
            ['slug' => 'notifications.view', 'name' => 'View notifications'],
            ['slug' => 'notifications.manage', 'name' => 'Manage notifications'],
            ['slug' => 'whatsapp.view', 'name' => 'View WhatsApp'],
            ['slug' => 'whatsapp.reply', 'name' => 'Reply on WhatsApp'],
            ['slug' => 'reports.view', 'name' => 'View reports'],
            ['slug' => 'reports.export', 'name' => 'Export reports'],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO permissions (public_id, slug, name) VALUES (:public_id, :slug, :name)
            ON DUPLICATE KEY UPDATE name = VALUES(name)'
        );

        foreach ($permissions as $permission) {
            $stmt->execute([
                'public_id' => Uuid::v4(),
                'slug' => $permission['slug'],
                'name' => $permission['name'],
            ]);
        }

        $rolePermissions = [
            'super_admin' => array_column($permissions, 'slug'),
            'manager' => array_column($permissions, 'slug'),
            'customer_service' => [
                'clients.view', 'clients.create', 'services.view',
                'transactions.view', 'transactions.create', 'transactions.update',
                'documents.view', 'documents.upload', 'notifications.view',
                'reports.view',
            ],
            'accountant' => [
                'payments.view', 'payments.create', 'payments.update',
                'transactions.view', 'clients.view', 'services.view',
                'reports.view', 'reports.export',
            ],
            'clearance_employee' => [
                'documents.view', 'documents.verify', 'clearance.view',
                'clearance.manage', 'transactions.view', 'notifications.view',
                'clients.view', 'services.view',
            ],
        ];

        $roleList = [
            'super_admin' => 'Platform Administrator',
            'manager' => 'Manager',
            'customer_service' => 'Customer Service',
            'accountant' => 'Accountant',
            'clearance_employee' => 'Clearance Employee',
        ];

        $roleStmt = $pdo->prepare(
            'INSERT INTO roles (public_id, tenant_id, slug, name, description)
            VALUES (:public_id, :tenant_id, :slug, :name, :description)
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
        );

        $roleIds = [];
        foreach ($roleList as $slug => $name) {
            $roleStmt->execute([
                'public_id' => Uuid::v4(),
                'tenant_id' => null,
                'slug' => $slug,
                'name' => $name,
                'description' => $name,
            ]);

            $roleIds[$slug] = (int) $pdo->query(
                "SELECT id FROM roles WHERE slug = '{$slug}' AND tenant_id IS NULL LIMIT 1"
            )->fetchColumn();
        }

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $permissionIds[$permission['slug']] = (int) $pdo->query(
                "SELECT id FROM permissions WHERE slug = '{$permission['slug']}' LIMIT 1"
            )->fetchColumn();
        }

        $linkStmt = $pdo->prepare(
            'INSERT INTO role_permissions (role_id, permission_id)
            VALUES (:role_id, :permission_id)
            ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)'
        );

        foreach ($rolePermissions as $slug => $granted) {
            $roleId = $roleIds[$slug];
            foreach ($granted as $permissionSlug) {
                $linkStmt->execute([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$permissionSlug],
                ]);
            }
        }

        return [
            'roles' => count($roleList),
            'permissions' => count($permissions),
        ];
    }
}

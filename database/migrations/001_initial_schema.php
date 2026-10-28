<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tenants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Muscat',
            currency VARCHAR(10) NOT NULL DEFAULT 'OMR',
            status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tenants_public_id (public_id),
            UNIQUE KEY uq_tenants_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NULL DEFAULT NULL,
            slug VARCHAR(80) NOT NULL,
            name VARCHAR(120) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_roles_public_id (public_id),
            UNIQUE KEY uq_roles_tenant_slug (tenant_id, slug),
            KEY idx_roles_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_permissions_public_id (public_id),
            UNIQUE KEY uq_permissions_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            role_id BIGINT UNSIGNED NOT NULL,
            permission_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_role_permission (role_id, permission_id),
            KEY idx_role_permissions_role (role_id),
            KEY idx_role_permissions_permission (permission_id),
            CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
            CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NULL DEFAULT NULL,
            role_id BIGINT UNSIGNED NULL DEFAULT NULL,
            is_platform_admin TINYINT(1) NOT NULL DEFAULT 0,
            username VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            role VARCHAR(80) NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_public_id (public_id),
            UNIQUE KEY uq_users_username (username),
            KEY idx_users_tenant (tenant_id),
            KEY idx_users_role (role_id),
            KEY idx_users_status (status),
            CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL,
            CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            client_type ENUM('person', 'company') NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            company_name VARCHAR(150) NULL,
            phone VARCHAR(30) NULL,
            whatsapp_number VARCHAR(30) NULL,
            email VARCHAR(150) NULL,
            preferred_language VARCHAR(20) NOT NULL DEFAULT 'en',
            notes TEXT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_clients_public_id (public_id),
            KEY idx_clients_tenant (tenant_id),
            KEY idx_clients_status (status),
            KEY idx_clients_full_name (full_name),
            CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ministries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            name_ar VARCHAR(200) NOT NULL,
            name_en VARCHAR(200) NULL,
            code VARCHAR(50) NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ministries_public_id (public_id),
            UNIQUE KEY uq_ministries_code (code),
            KEY idx_ministries_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS services (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            ministry_id BIGINT UNSIGNED NULL DEFAULT NULL,
            name_ar VARCHAR(200) NOT NULL,
            name_en VARCHAR(200) NULL,
            description_ar TEXT NULL,
            description_en TEXT NULL,
            official_fee_baisa INT NOT NULL DEFAULT 0,
            office_fee_baisa INT NOT NULL DEFAULT 0,
            target_sla_minutes INT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_services_public_id (public_id),
            KEY idx_services_tenant (tenant_id),
            KEY idx_services_ministry (ministry_id),
            KEY idx_services_status (status),
            CONSTRAINT fk_services_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_services_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            reference VARCHAR(80) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status ENUM('inquiry','open','waiting_documents','in_progress','waiting_payment','waiting_external','waiting_client','completed','cancelled') NOT NULL DEFAULT 'open',
            assigned_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            source ENUM('dashboard','whatsapp','converted_inquiry') NOT NULL DEFAULT 'dashboard',
            priority ENUM('normal','high','urgent') NOT NULL DEFAULT 'normal',
            opened_at TIMESTAMP NULL DEFAULT NULL,
            due_at TIMESTAMP NULL DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            cancelled_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_transactions_public_id (public_id),
            UNIQUE KEY uq_transactions_reference (reference),
            KEY idx_transactions_tenant (tenant_id),
            KEY idx_transactions_client (client_id),
            KEY idx_transactions_service (service_id),
            KEY idx_transactions_status (status),
            KEY idx_transactions_assignee (assigned_user_id),
            CONSTRAINT fk_transactions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_transactions_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT,
            CONSTRAINT fk_transactions_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE RESTRICT,
            CONSTRAINT fk_transactions_assignee FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transaction_status_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NOT NULL,
            from_status VARCHAR(50) NULL,
            to_status VARCHAR(50) NOT NULL,
            changed_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            reason TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_history_transaction (transaction_id),
            KEY idx_status_history_tenant (tenant_id),
            CONSTRAINT fk_status_history_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_status_history_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE CASCADE,
            CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            storage_key VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL,
            status ENUM('required','received','verified','rejected','expired') NOT NULL DEFAULT 'received',
            rejection_reason TEXT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            uploaded_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            verified_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            verified_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_documents_public_id (public_id),
            KEY idx_documents_tenant (tenant_id),
            KEY idx_documents_transaction (transaction_id),
            KEY idx_documents_status (status),
            CONSTRAINT fk_documents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_documents_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE CASCADE,
            CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT fk_documents_verified_by FOREIGN KEY (verified_by_user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NULL DEFAULT NULL,
            client_id BIGINT UNSIGNED NULL DEFAULT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'payment',
            amount_baisa BIGINT NOT NULL DEFAULT 0,
            method VARCHAR(40) NULL,
            reference VARCHAR(120) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            paid_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_payments_public_id (public_id),
            KEY idx_payments_tenant (tenant_id),
            CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_payments_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE SET NULL,
            CONSTRAINT fk_payments_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
            CONSTRAINT fk_payments_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NULL DEFAULT NULL,
            client_id BIGINT UNSIGNED NULL DEFAULT NULL,
            invoice_number VARCHAR(80) NOT NULL,
            subtotal_baisa BIGINT NOT NULL DEFAULT 0,
            official_fee_baisa BIGINT NOT NULL DEFAULT 0,
            office_fee_baisa BIGINT NOT NULL DEFAULT 0,
            total_baisa BIGINT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            due_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_invoices_public_id (public_id),
            UNIQUE KEY uq_invoices_number (invoice_number),
            KEY idx_invoices_tenant (tenant_id),
            CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_invoices_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE SET NULL,
            CONSTRAINT fk_invoices_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
            CONSTRAINT fk_invoices_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            attendance_date DATE NOT NULL,
            check_in TIMESTAMP NULL DEFAULT NULL,
            check_out TIMESTAMP NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'present',
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_attendance_public_id (public_id),
            KEY idx_attendance_tenant (tenant_id),
            CONSTRAINT fk_attendance_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clearance_operations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NULL DEFAULT NULL,
            authority VARCHAR(150) NULL,
            external_reference VARCHAR(120) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            submitted_at TIMESTAMP NULL DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_clearance_public_id (public_id),
            KEY idx_clearance_tenant (tenant_id),
            CONSTRAINT fk_clearance_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_clearance_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE SET NULL,
            CONSTRAINT fk_clearance_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS discussions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NULL DEFAULT NULL,
            user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            channel VARCHAR(30) NOT NULL DEFAULT 'internal',
            message TEXT NOT NULL,
            is_ai TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_discussions_public_id (public_id),
            KEY idx_discussions_tenant (tenant_id),
            CONSTRAINT fk_discussions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_discussions_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE SET NULL,
            CONSTRAINT fk_discussions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            type VARCHAR(60) NOT NULL DEFAULT 'info',
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            read_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_notifications_public_id (public_id),
            KEY idx_notifications_tenant (tenant_id),
            CONSTRAINT fk_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_conversations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NULL DEFAULT NULL,
            transaction_id BIGINT UNSIGNED NULL DEFAULT NULL,
            phone VARCHAR(30) NOT NULL,
            direction VARCHAR(20) NOT NULL DEFAULT 'inbound',
            message_type VARCHAR(30) NOT NULL DEFAULT 'text',
            body TEXT NULL,
            media_path VARCHAR(500) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            created_by BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_whatsapp_public_id (public_id),
            KEY idx_whatsapp_tenant (tenant_id),
            CONSTRAINT fk_whatsapp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_whatsapp_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
            CONSTRAINT fk_whatsapp_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE SET NULL,
            CONSTRAINT fk_whatsapp_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_reports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            report_type VARCHAR(80) NOT NULL,
            prompt_summary TEXT NULL,
            output LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'queued',
            generated_by BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ai_reports_public_id (public_id),
            KEY idx_ai_reports_tenant (tenant_id),
            CONSTRAINT fk_ai_reports_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_ai_reports_generator FOREIGN KEY (generated_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL DEFAULT NULL,
            action VARCHAR(60) NOT NULL,
            entity_type VARCHAR(60) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            metadata LONGTEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_audit_logs_public_id (public_id),
            KEY idx_audit_logs_tenant (tenant_id),
            CONSTRAINT fk_audit_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
};

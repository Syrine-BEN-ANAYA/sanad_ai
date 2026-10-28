<?php

declare(strict_types=1);

namespace SanadAi\Documents;

use PDO;

final class DocumentRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Find one document by public ID, restricted to the tenant.
     */
    public function findByPublicId(
        int $tenantId,
        string $publicId
    ): ?array {
        $statement = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                transaction_id,
                document_type,
                original_name,
                storage_key,
                mime_type,
                size_bytes,
                status,
                rejection_reason,
                expires_at,
                uploaded_by_user_id,
                verified_by_user_id,
                verified_at,
                created_at,
                updated_at
            FROM documents
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
            LIMIT 1
        ");

        $statement->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
        ]);

        $document = $statement->fetch(PDO::FETCH_ASSOC);

        return $document ?: null;
    }

    /**
     * List documents belonging to a transaction.
     */
    public function listByTransaction(
        int $tenantId,
        int $transactionId
    ): array {
        $statement = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                transaction_id,
                document_type,
                original_name,
                storage_key,
                mime_type,
                size_bytes,
                status,
                rejection_reason,
                expires_at,
                uploaded_by_user_id,
                verified_by_user_id,
                verified_at,
                created_at,
                updated_at
            FROM documents
            WHERE tenant_id = :tenant_id
              AND transaction_id = :transaction_id
            ORDER BY created_at ASC, id ASC
        ");

        $statement->execute([
            'tenant_id' => $tenantId,
            'transaction_id' => $transactionId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List documents belonging to a transaction using its public ID.
     */
    public function listByTransactionPublicId(
        int $tenantId,
        string $transactionPublicId
    ): array {
        $statement = $this->pdo->prepare("
            SELECT
                d.id,
                d.public_id,
                d.tenant_id,
                d.transaction_id,
                d.document_type,
                d.original_name,
                d.storage_key,
                d.mime_type,
                d.size_bytes,
                d.status,
                d.rejection_reason,
                d.expires_at,
                d.uploaded_by_user_id,
                d.verified_by_user_id,
                d.verified_at,
                d.created_at,
                d.updated_at
            FROM documents d
            INNER JOIN transactions t
                ON t.id = d.transaction_id
               AND t.tenant_id = d.tenant_id
            WHERE d.tenant_id = :tenant_id
              AND t.public_id = :transaction_public_id
            ORDER BY d.created_at ASC, d.id ASC
        ");

        $statement->execute([
            'tenant_id' => $tenantId,
            'transaction_public_id' => $transactionPublicId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a document record.
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare("
            INSERT INTO documents (
                public_id,
                tenant_id,
                transaction_id,
                document_type,
                original_name,
                storage_key,
                mime_type,
                size_bytes,
                status,
                rejection_reason,
                expires_at,
                uploaded_by_user_id
            ) VALUES (
                :public_id,
                :tenant_id,
                :transaction_id,
                :document_type,
                :original_name,
                :storage_key,
                :mime_type,
                :size_bytes,
                :status,
                :rejection_reason,
                :expires_at,
                :uploaded_by_user_id
            )
        ");

        $statement->execute([
            'public_id' => $data['public_id'],
            'tenant_id' => $data['tenant_id'],
            'transaction_id' => $data['transaction_id'],
            'document_type' => $data['document_type'],
            'original_name' => $data['original_name'],
            'storage_key' => $data['storage_key'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
            'status' => $data['status'] ?? 'received',
            'rejection_reason' => $data['rejection_reason'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'uploaded_by_user_id' => $data['uploaded_by_user_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update document status and verification metadata.
     */
    public function updateStatus(
        int $tenantId,
        string $publicId,
        string $status,
        ?string $rejectionReason = null,
        ?int $verifiedByUserId = null,
        ?string $verifiedAt = null
    ): bool {
        $statement = $this->pdo->prepare("
            UPDATE documents
            SET
                status = :status,
                rejection_reason = :rejection_reason,
                verified_by_user_id = :verified_by_user_id,
                verified_at = :verified_at
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
        ");

        $statement->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
            'status' => $status,
            'rejection_reason' => $rejectionReason,
            'verified_by_user_id' => $verifiedByUserId,
            'verified_at' => $verifiedAt,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Delete a document record.
     *
     * Physical file deletion must be handled separately by the storage layer.
     */
    public function delete(
        int $tenantId,
        string $publicId
    ): bool {
        $statement = $this->pdo->prepare("
            DELETE FROM documents
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
        ");

        $statement->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
        ]);

        return $statement->rowCount() > 0;
    }
}

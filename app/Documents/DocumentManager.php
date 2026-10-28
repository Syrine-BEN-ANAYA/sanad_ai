<?php

declare(strict_types=1);

namespace SanadAi\Documents;

use PDO;
use RuntimeException;
use SanadAi\Http\NotFoundException;
use SanadAi\Transactions\TransactionRepository;

final class DocumentManager
{
    private const ALLOWED_STATUSES = [
        'required',
        'received',
        'verified',
        'rejected',
        'expired',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly DocumentRepository $repository,
        private readonly TransactionRepository $transactionRepository,
        private readonly DocumentValidator $validator,
        private readonly DocumentStorage $storage
    ) {
    }

    /**
     * Create a document record using an already stored file.
     */
    public function create(
        int $tenantId,
        string $transactionPublicId,
        string $documentType,
        string $originalName,
        string $storageKey,
        string $mimeType,
        int $sizeBytes,
        string $status = 'received',
        ?int $uploadedByUserId = null
    ): array {
        $documentType = trim($documentType);
        $originalName = trim($originalName);
        $storageKey = trim($storageKey);
        $mimeType = trim($mimeType);

        $this->validateDocumentType($documentType);
        $this->validateOriginalName($originalName);
        $this->validateStorageKey($storageKey);
        $this->validateMimeType($mimeType);
        $this->validateSize($sizeBytes);

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new RuntimeException('Invalid document status.');
        }

        if ($status === 'verified') {
            throw new RuntimeException(
                'A new document cannot be created directly as verified.'
            );
        }

        $transaction = $this->transactionRepository->findByPublicId(
            tenantId: $tenantId,
            publicId: $transactionPublicId
        );

        if ($transaction === null) {
            throw new NotFoundException('Transaction not found.');
        }

        if (in_array(
            $transaction->status,
            ['completed', 'cancelled'],
            true
        )) {
            throw new RuntimeException(
                'Documents cannot be added to a completed or cancelled transaction.'
            );
        }

        if (!$this->storage->exists($storageKey)) {
            throw new RuntimeException(
                'Stored file does not exist.'
            );
        }

        $publicId = $this->generatePublicId();

        $transactionId = $transaction->id;

        $documentId = $this->repository->create([
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'transaction_id' => $transactionId,
            'document_type' => $documentType,
            'original_name' => $originalName,
            'storage_key' => $storageKey,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'status' => $status,
            'rejection_reason' => null,
            'expires_at' => null,
            'uploaded_by_user_id' => $uploadedByUserId,
            'verified_by_user_id' => null,
            'verified_at' => null,
        ]);

        if ($documentId <= 0) {
            throw new RuntimeException(
                'Unable to create document.'
            );
        }

        $document = $this->repository->findByPublicId(
            tenantId: $tenantId,
            publicId: $publicId
        );

        if ($document === null) {
            throw new RuntimeException(
                'Document was created but could not be loaded.'
            );
        }

        return $document;
    }

    /**
     * Validate, store and create a document.
     *
     * The source file must be a local temporary file.
     */
    public function upload(
        int $tenantId,
        string $transactionPublicId,
        string $documentType,
        string $sourcePath,
        string $originalName,
        ?int $uploadedByUserId = null
    ): array {
        $transaction = $this->transactionRepository->findByPublicId(
            tenantId: $tenantId,
            publicId: $transactionPublicId
        );

        if ($transaction === null) {
            throw new NotFoundException('Transaction not found.');
        }

        if (in_array(
            $transaction->status,
            ['completed', 'cancelled'],
            true
        )) {
            throw new RuntimeException(
                'Documents cannot be uploaded to a completed or cancelled transaction.'
            );
        }

        /*
         * Validate the real file contents before storing anything.
         */
        $validated = $this->validator->validate(
            filePath: $sourcePath,
            originalName: $originalName
        );

        /*
         * The transaction object contains the internal database ID.
         */
        $transactionId = $transaction->id;

        /*
         * Generate a random storage key.
         */
        $storageKey = $this->storage->generateStorageKey(
            tenantId: $tenantId,
            transactionId: $transactionId,
            originalName: $validated['original_name']
        );

        /*
         * Read the validated file.
         */
        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read uploaded file.'
            );
        }

        /*
         * Store outside public/.
         */
        $this->storage->store(
            storageKey: $storageKey,
            contents: $contents
        );

        try {
            /*
             * Create the database record only after successful storage.
             */
            return $this->create(
                tenantId: $tenantId,
                transactionPublicId: $transactionPublicId,
                documentType: $documentType,
                originalName: $validated['original_name'],
                storageKey: $storageKey,
                mimeType: $validated['mime_type'],
                sizeBytes: $validated['size_bytes'],
                status: 'received',
                uploadedByUserId: $uploadedByUserId
            );
        } catch (\Throwable $exception) {
            /*
             * Remove the physical file if DB creation fails.
             */
            $this->storage->delete($storageKey);

            throw $exception;
        }
    }

    public function find(
        int $tenantId,
        string $publicId
    ): ?array {
        return $this->repository->findByPublicId(
            tenantId: $tenantId,
            publicId: $publicId
        );
    }

    public function listByTransaction(
        int $tenantId,
        string $transactionPublicId
    ): array {
        $transaction = $this->transactionRepository->findByPublicId(
            tenantId: $tenantId,
            publicId: $transactionPublicId
        );

        if ($transaction === null) {
            throw new NotFoundException('Transaction not found.');
        }

        return $this->repository->listByTransactionPublicId(
            tenantId: $tenantId,
            transactionPublicId: $transactionPublicId
        );
    }

    public function changeStatus(
        int $tenantId,
        string $publicId,
        string $status,
        ?string $rejectionReason = null,
        ?int $verifiedByUserId = null
    ): array {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new RuntimeException('Invalid document status.');
        }

        $document = $this->repository->findByPublicId(
            tenantId: $tenantId,
            publicId: $publicId
        );

        if ($document === null) {
            throw new NotFoundException('Document not found.');
        }

        if ($document['status'] === 'verified') {
            throw new RuntimeException(
                'A verified document cannot be modified.'
            );
        }

        if (
            $document['status'] === 'expired'
            && $status !== 'received'
        ) {
            throw new RuntimeException(
                'An expired document can only return to received status.'
            );
        }

        if ($status === 'rejected') {
            $rejectionReason = trim((string) $rejectionReason);

            if ($rejectionReason === '') {
                throw new RuntimeException(
                    'Rejection reason is required.'
                );
            }

            if (mb_strlen($rejectionReason) > 500) {
                throw new RuntimeException(
                    'Rejection reason is too long.'
                );
            }
        } else {
            $rejectionReason = null;
        }

        $verifiedAt = null;

        if ($status === 'verified') {
            if ($verifiedByUserId === null || $verifiedByUserId <= 0) {
                throw new RuntimeException(
                    'A verifier is required.'
                );
            }

            $verifiedAt = date('Y-m-d H:i:s');
        } else {
            $verifiedByUserId = null;
        }

        $updated = $this->repository->updateStatus(
            tenantId: $tenantId,
            publicId: $publicId,
            status: $status,
            rejectionReason: $rejectionReason,
            verifiedByUserId: $verifiedByUserId,
            verifiedAt: $verifiedAt
        );

        if (!$updated) {
            throw new RuntimeException(
                'Unable to update document status.'
            );
        }

        $updatedDocument = $this->repository->findByPublicId(
            tenantId: $tenantId,
            publicId: $publicId
        );

        if ($updatedDocument === null) {
            throw new RuntimeException(
                'Document was updated but could not be loaded.'
            );
        }

        return $updatedDocument;
    }

    private function validateDocumentType(
        string $documentType
    ): void {
        if ($documentType === '') {
            throw new RuntimeException(
                'Document type is required.'
            );
        }

        if (mb_strlen($documentType) > 100) {
            throw new RuntimeException(
                'Document type is too long.'
            );
        }
    }

    private function validateOriginalName(
        string $originalName
    ): void {
        if ($originalName === '') {
            throw new RuntimeException(
                'Original file name is required.'
            );
        }

        if (mb_strlen($originalName) > 255) {
            throw new RuntimeException(
                'Original file name is too long.'
            );
        }
    }

    private function validateStorageKey(
        string $storageKey
    ): void {
        if ($storageKey === '') {
            throw new RuntimeException(
                'Storage key is required.'
            );
        }

        if (mb_strlen($storageKey) > 500) {
            throw new RuntimeException(
                'Storage key is too long.'
            );
        }
    }

    private function validateMimeType(
        string $mimeType
    ): void {
        if ($mimeType === '') {
            throw new RuntimeException(
                'MIME type is required.'
            );
        }

        if (mb_strlen($mimeType) > 100) {
            throw new RuntimeException(
                'MIME type is too long.'
            );
        }
    }

    private function validateSize(
        int $sizeBytes
    ): void {
        if ($sizeBytes <= 0) {
            throw new RuntimeException(
                'File size must be greater than zero.'
            );
        }

        if (
            $sizeBytes > $this->validator->maxSizeBytes()
        ) {
            throw new RuntimeException(
                'File exceeds the maximum allowed size.'
            );
        }
    }

    private function generatePublicId(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(
            (ord($data[6]) & 0x0f) | 0x40
        );

        $data[8] = chr(
            (ord($data[8]) & 0x3f) | 0x80
        );

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }
}

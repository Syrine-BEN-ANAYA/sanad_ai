<?php

declare(strict_types=1);

namespace SanadAi\Documents;

use RuntimeException;
use SanadAi\Auth\SessionContext;
use SanadAi\Http\HttpException;
use SanadAi\Http\Input;
use SanadAi\Http\NotFoundException;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Tenancy\TenantContext;

final class DocumentController
{
    public function __construct(
        private readonly DocumentManager $manager,
        private readonly DocumentStorage $storage,
        private readonly TenantContext $tenantContext,
        private readonly SessionContext $sessionContext,
        private readonly Response $response
    ) {
    }

    /** GET /api/transactions/{publicId}/documents */
    public function index(Request $request, string $transactionPublicId): void
    {
        $documents = $this->business(fn (): array => $this->manager->listByTransaction(
            $this->tenantContext->require(),
            $transactionPublicId
        ));

        $this->response->json([
            'success' => true,
            'data' => array_map(fn (array $d): array => $this->serialize($d), $documents),
        ]);
    }

    /**
     * POST /api/transactions/{publicId}/documents
     *
     * multipart/form-data: document_type (text), document (file)
     */
    public function upload(Request $request, string $transactionPublicId): void
    {
        $documentType = trim((string) ($_POST['document_type'] ?? ''));

        if ($documentType === '') {
            throw new \InvalidArgumentException('Document type is required.');
        }

        $file = $_FILES['document'] ?? null;

        if (!is_array($file)) {
            throw new \InvalidArgumentException('Document file is required.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException($this->uploadErrorMessage($error));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');

        if ($tmpName === '' || $originalName === '' || !is_uploaded_file($tmpName)) {
            throw new \InvalidArgumentException('Invalid uploaded file.');
        }

        $document = $this->business(fn (): array => $this->manager->upload(
            tenantId: $this->tenantContext->require(),
            transactionPublicId: $transactionPublicId,
            documentType: $documentType,
            sourcePath: $tmpName,
            originalName: $originalName,
            uploadedByUserId: $this->sessionContext->userId()
        ));

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($document),
        ], 201);
    }

    /** GET /api/documents/{publicId}/download */
    public function download(Request $request, string $publicId): void
    {
        $document = $this->manager->find($this->tenantContext->require(), $publicId);

        if ($document === null) {
            throw new NotFoundException('Document not found.');
        }

        $path = $this->storage->absolutePath((string) $document['storage_key']);

        if (!is_file($path)) {
            throw new NotFoundException('Document file is missing.');
        }

        // Never let the browser render uploaded content inline.
        $name = preg_replace('/[^\p{L}\p{N}._ -]/u', '_', (string) $document['original_name']) ?: 'document';

        header('Content-Type: ' . $document['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header("Content-Disposition: attachment; filename=\"{$name}\"; filename*=UTF-8''" . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, no-store');

        readfile($path);
    }

    /**
     * POST /api/documents/{publicId}/status
     *
     * Body: status (received|verified|rejected|expired), reason (for rejected)
     */
    public function changeStatus(Request $request, string $publicId): void
    {
        $data = $request->json();

        $document = $this->business(fn (): array => $this->manager->changeStatus(
            tenantId: $this->tenantContext->require(),
            publicId: $publicId,
            status: Input::string($data, 'status'),
            rejectionReason: Input::nullableString($data, 'reason'),
            verifiedByUserId: $this->sessionContext->userId()
        ));

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($document),
        ]);
    }

    /**
     * Document rules throw RuntimeException with client-safe messages;
     * turn them into HTTP 422. Typed HTTP exceptions pass through.
     *
     * @template T
     * @param callable(): T $action
     * @return T
     */
    private function business(callable $action): mixed
    {
        try {
            return $action();
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function serialize(array $d): array
    {
        return [
            'public_id' => $d['public_id'],
            'document_type' => $d['document_type'],
            'original_name' => $d['original_name'],
            'mime_type' => $d['mime_type'],
            'size_bytes' => (int) $d['size_bytes'],
            'status' => $d['status'],
            'rejection_reason' => $d['rejection_reason'] ?? null,
            'verified_at' => $d['verified_at'] ?? null,
            'created_at' => $d['created_at'] ?? null,
        ];
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Document file is required.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Unable to write uploaded file.',
            UPLOAD_ERR_EXTENSION => 'File upload was blocked by a PHP extension.',
            default => 'Unknown file upload error.',
        };
    }
}

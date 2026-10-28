<?php

declare(strict_types=1);

namespace SanadAi\Documents;

use RuntimeException;

final class DocumentValidator
{
    /**
     * Maximum file size: 10 MB.
     */
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    /**
     * Allowed MIME types and their corresponding extensions.
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => ['pdf'],

        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],

        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],

        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    ];

    /**
     * Validate a local uploaded file.
     *
     * @return array{
     *     original_name: string,
     *     mime_type: string,
     *     extension: string,
     *     size_bytes: int
     * }
     */
    public function validate(
        string $filePath,
        string $originalName
    ): array {
        if ($filePath === '') {
            throw new RuntimeException('File path is required.');
        }

        if (!is_file($filePath)) {
            throw new RuntimeException('File does not exist.');
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException('File is not readable.');
        }

        $size = filesize($filePath);

        if ($size === false) {
            throw new RuntimeException('Unable to determine file size.');
        }

        if ($size <= 0) {
            throw new RuntimeException('File cannot be empty.');
        }

        if ($size > self::MAX_SIZE_BYTES) {
            throw new RuntimeException(
                'File exceeds the maximum allowed size of 10 MB.'
            );
        }

        $originalName = trim($originalName);

        if ($originalName === '') {
            throw new RuntimeException('Original file name is required.');
        }

        if (mb_strlen($originalName) > 255) {
            throw new RuntimeException(
                'Original file name is too long.'
            );
        }

        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        if ($extension === '') {
            throw new RuntimeException(
                'File extension is required.'
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->file($filePath);

        if ($mimeType === false || $mimeType === '') {
            throw new RuntimeException(
                'Unable to determine file MIME type.'
            );
        }

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new RuntimeException(
                'This file type is not allowed.'
            );
        }

        $allowedExtensions = self::ALLOWED_MIME_TYPES[$mimeType];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException(
                'File extension does not match the detected MIME type.'
            );
        }

        return [
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => $size,
        ];
    }

    public function maxSizeBytes(): int
    {
        return self::MAX_SIZE_BYTES;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function allowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }
}

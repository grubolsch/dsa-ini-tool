<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores uploaded images to public/uploads with a randomized filename, returns the
 * public path (e.g. /uploads/ab12cd.png). Basic mime/size validation.
 */
class FileUploader
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private const MAX_BYTES = 8 * 1024 * 1024; // 8 MB

    public function __construct(
        private readonly string $uploadsDir,
        private readonly string $uploadsPublicPath,
    ) {
    }

    /**
     * @throws \InvalidArgumentException on validation failure
     */
    public function upload(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File too large (max 8MB).');
        }

        $mime = $file->getMimeType() ?? '';
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new \InvalidArgumentException('Unsupported file type: '.$mime);
        }

        $ext = self::ALLOWED_MIME[$mime];
        $filename = uniqid('', true);
        $filename = str_replace('.', '', $filename).'.'.$ext;

        if (!is_dir($this->uploadsDir)) {
            @mkdir($this->uploadsDir, 0775, true);
        }

        try {
            $file->move($this->uploadsDir, $filename);
        } catch (FileException $e) {
            throw new \InvalidArgumentException('Failed to store upload: '.$e->getMessage(), 0, $e);
        }

        return rtrim($this->uploadsPublicPath, '/').'/'.$filename;
    }
}

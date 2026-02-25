<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class FileValidationService
{
    /**
     * Allowed MIME types whitelist
     */
    private const ALLOWED_MIME_TYPES = [
        // Images
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        
        // Videos
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/webm',
        
        // Audio
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/ogg',
        'audio/webm',
        
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
    ];

    /**
     * Dangerous file extensions
     */
    private const DANGEROUS_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
        'sh', 'app', 'deb', 'rpm', 'dmg', 'pkg', 'run', 'bin',
        'ps1', 'psm1', 'psd1', 'ps1xml', 'psc1', 'msi', 'gadget', 'php', 'pl', 'py'
    ];

    /**
     * Default maximum file size in bytes (100MB)
     */
    private const DEFAULT_MAX_SIZE = 104857600;

    /**
     * Maximum file size in bytes
     */
    private int $maxFileSize;

    public function __construct(?int $maxFileSize = null)
    {
        $this->maxFileSize = $maxFileSize ?? config('filesystems.max_file_size', self::DEFAULT_MAX_SIZE);
    }

    /**
     * Validate file type against whitelist
     *
     * @param  string  $mimeType
     * @return array{valid: bool, error: string|null}
     */
    public function validateFileType(string $mimeType): array
    {
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return [
                'valid' => false,
                'error' => "File type '{$mimeType}' is not allowed. Please upload a valid file type.",
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Validate file size against maximum limit
     *
     * @param  int  $fileSize
     * @return array{valid: bool, error: string|null}
     */
    public function validateFileSize(int $fileSize): array
    {
        if ($fileSize > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / 1048576, 2);
            $fileSizeMB = round($fileSize / 1048576, 2);

            return [
                'valid' => false,
                'error' => "File size ({$fileSizeMB}MB) exceeds the maximum allowed size of {$maxSizeMB}MB.",
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Validate and sanitize filename
     *
     * @param  string  $originalName
     * @return array{valid: bool, error: string|null, sanitized: string|null}
     */
    public function validateFileName(string $originalName): array
    {
        // Check for empty filename
        if (empty($originalName)) {
            return [
                'valid' => false,
                'error' => 'Filename cannot be empty.',
                'sanitized' => null,
            ];
        }

        // Sanitize filename: remove special characters, keep alphanumeric, dots, dashes, underscores
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        $sanitizedBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName);
        $sanitizedBase = preg_replace('/_+/', '_', $sanitizedBase);
        $sanitizedBase = trim($sanitizedBase, '_');

        // Reconstruct filename
        $sanitized = $sanitizedBase . ($extension ? '.' . $extension : '');

        // Check if sanitized filename is too different from original (potential security issue)
        if (empty($sanitizedBase)) {
            return [
                'valid' => false,
                'error' => 'Filename contains invalid characters and cannot be sanitized.',
                'sanitized' => null,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'sanitized' => $sanitized,
        ];
    }

    /**
     * Detect potentially dangerous files
     *
     * @param  string  $filename
     * @param  string  $mimeType
     * @param  int  $fileSize
     * @return array{valid: bool, error: string|null}
     */
    public function detectDangerousFile(string $filename, string $mimeType, int $fileSize): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Check for double extensions first (e.g., file.pdf.exe)
        $parts = explode('.', $filename);
        
        if (count($parts) > 2) {
            // Check if any part before the last extension is dangerous
            $partsWithoutLast = array_slice($parts, 0, -1);
            foreach ($partsWithoutLast as $part) {
                if (in_array(strtolower($part), self::DANGEROUS_EXTENSIONS)) {
                    // Log the dangerous file attempt
                    Log::warning('Dangerous file upload attempt blocked', [
                        'filename' => $filename,
                        'reason' => 'suspicious double extension',
                        'detected_extension' => strtolower($part),
                        'file_size' => $fileSize,
                        'mime_type' => $mimeType,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                    
                    return [
                        'valid' => false,
                        'error' => 'File contains suspicious double extension and is not allowed.',
                    ];
                }
            }
        }

        // Check for dangerous extensions
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            // Log the dangerous file attempt
            Log::warning('Dangerous file upload attempt blocked', [
                'filename' => $filename,
                'reason' => 'dangerous extension',
                'extension' => $extension,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            
            return [
                'valid' => false,
                'error' => "File with extension '.{$extension}' is not allowed for security reasons.",
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Validate file metadata completely
     *
     * @param  string  $fileName
     * @param  int  $fileSize
     * @param  string  $mimeType
     * @return array{valid: bool, errors: array<string>, sanitizedFilename: string|null}
     */
    public function validateMetadata(string $fileName, int $fileSize, string $mimeType): array
    {
        $errors = [];
        $sanitizedFilename = null;

        // Validate file type
        $typeValidation = $this->validateFileType($mimeType);
        if (! $typeValidation['valid']) {
            $errors[] = $typeValidation['error'];
        }

        // Validate file size
        $sizeValidation = $this->validateFileSize($fileSize);
        if (! $sizeValidation['valid']) {
            $errors[] = $sizeValidation['error'];
        }

        // Validate filename
        $nameValidation = $this->validateFileName($fileName);
        if (! $nameValidation['valid']) {
            $errors[] = $nameValidation['error'];
        } else {
            $sanitizedFilename = $nameValidation['sanitized'];
        }

        // Detect dangerous files
        $dangerousValidation = $this->detectDangerousFile($fileName, $mimeType, $fileSize);
        if (! $dangerousValidation['valid']) {
            $errors[] = $dangerousValidation['error'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitizedFilename' => $sanitizedFilename,
        ];
    }
    
    /**
     * Validate actual UploadedFile instance
     * 
     * @param UploadedFile $file
     * @return array
     */
    public function validateFile(UploadedFile $file): array
    {
        return $this->validateMetadata(
            $file->getClientOriginalName(),
            $file->getSize(),
            $file->getMimeType()
        );
    }
}

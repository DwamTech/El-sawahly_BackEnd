<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class StorageHandlerService
{
    /**
     * Storage driver to use (local or s3)
     */
    private string $driver;

    /**
     * Base directory for file storage
     */
    private const BASE_DIRECTORY = 'files';

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? config('filesystems.default', 'local');
    }

    /**
     * Store file to local filesystem
     * 
     * @param UploadedFile $file The file to store
     * @param int $userId The user ID for directory organization
     * @param string|null $customFilename Optional custom filename
     * @param string|null $targetPath Optional target directory path
     * @return array{success: bool, path: string|null, url: string|null, error: string|null}
     */
    public function storeFile(UploadedFile $file, int $userId, ?string $customFilename = null, ?string $targetPath = null): array
    {
        try {
            // Generate organized file path
            $filePath = $this->organizeFilePath($userId, $customFilename ?? $file->getClientOriginalName(), $targetPath);
            
            // Store file to local disk
            $storedPath = $file->storeAs(
                dirname($filePath),
                basename($filePath),
                'public'
            );

            if (!$storedPath) {
                return [
                    'success' => false,
                    'path' => null,
                    'url' => null,
                    'error' => 'Failed to store file to local filesystem',
                ];
            }

            // Generate URL for the stored file
            $url = $this->generateFileUrl($storedPath, 'local');

            return [
                'success' => true,
                'path' => $storedPath,
                'url' => $url,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Local file storage failed', [
                'filename' => $file->getClientOriginalName(),
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'path' => null,
                'url' => null,
                'error' => 'Storage operation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Store file to S3 cloud storage
     * 
     * @param UploadedFile $file The file to store
     * @param int $userId The user ID for directory organization
     * @param string|null $customFilename Optional custom filename
     * @return array{success: bool, path: string|null, url: string|null, error: string|null}
     */
    public function storeFileToS3(UploadedFile $file, int $userId, ?string $customFilename = null): array
    {
        try {
            // Generate organized file path
            $filePath = $this->organizeFilePath($userId, $customFilename ?? $file->getClientOriginalName());
            
            // Store file to S3
            $storedPath = $file->storeAs(
                dirname($filePath),
                basename($filePath),
                's3'
            );

            if (!$storedPath) {
                return [
                    'success' => false,
                    'path' => null,
                    'url' => null,
                    'error' => 'Failed to upload file to S3',
                ];
            }

            // Generate URL for the stored file
            $url = $this->generateFileUrl($storedPath, 's3');

            return [
                'success' => true,
                'path' => $storedPath,
                'url' => $url,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('S3 file storage failed', [
                'filename' => $file->getClientOriginalName(),
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'path' => null,
                'url' => null,
                'error' => 'Cloud storage operation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate file URL for both local and cloud storage
     * 
     * @param string $path The file path
     * @param string $storageType The storage type (local or s3)
     * @return string The accessible URL
     */
    public function generateFileUrl(string $path, string $storageType): string
    {
        $diskName = $storageType === 's3' ? 's3' : 'public';
        
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);
        
        return $disk->url($path);
    }

    /**
     * Organize file path with logical directory structure (year/month/user_id)
     * 
     * @param int $userId The user ID
     * @param string $filename The original filename
     * @param string|null $targetPath Optional target directory path
     * @return string The organized file path
     */
    public function organizeFilePath(int $userId, string $filename, ?string $targetPath = null): string
    {
        // If targetPath is provided, use it as the base directory
        if (!empty($targetPath)) {
            // Sanitize target path
            $targetPath = trim($targetPath, '/\\');
            $targetPath = str_replace('\\', '/', $targetPath);
            
            // Generate unique filename
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            
            // Sanitize filename
            $sanitizedBaseName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName);
            $sanitizedBaseName = preg_replace('/_+/', '_', $sanitizedBaseName);
            $sanitizedBaseName = trim($sanitizedBaseName, '_');
            
            // Create unique filename with timestamp and random string
            $uniqueFilename = $sanitizedBaseName . '_' . time() . '_' . Str::random(8);
            
            if (!empty($extension)) {
                $uniqueFilename .= '.' . $extension;
            }
            
            // Return path with target directory
            return $targetPath . '/' . $uniqueFilename;
        }
        
        // Default behavior: Get current year and month
        $year = date('Y');
        $month = date('m');
        
        // Generate unique filename to prevent collisions
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        
        // Sanitize filename
        $sanitizedBaseName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName);
        $sanitizedBaseName = preg_replace('/_+/', '_', $sanitizedBaseName);
        $sanitizedBaseName = trim($sanitizedBaseName, '_');
        
        // Create unique filename with timestamp and random string
        $uniqueFilename = $sanitizedBaseName . '_' . time() . '_' . Str::random(8);
        
        if (!empty($extension)) {
            $uniqueFilename .= '.' . $extension;
        }
        
        // Construct organized path: files/year/month/user_id/filename
        return self::BASE_DIRECTORY . '/' . $year . '/' . $month . '/' . $userId . '/' . $uniqueFilename;
    }

    /**
     * Delete file from storage
     * 
     * @param string $path The file path to delete
     * @param string|null $storageType The storage type (local or s3), defaults to configured driver
     * @return array{success: bool, error: string|null}
     */
    public function deleteFile(string $path, ?string $storageType = null): array
    {
        try {
            $disk = $storageType ?? $this->driver;
            
            // Normalize path
            $path = str_replace('\\', '/', $path);
            $path = ltrim($path, '/');
            
            // Determine the correct disk
            $storageDisk = $disk === 's3' ? 's3' : 'public';
            
            // Check if file exists
            if (!Storage::disk($storageDisk)->exists($path)) {
                return [
                    'success' => false,
                    'error' => 'File not found at path: ' . $path,
                ];
            }
            
            // Delete the file
            $deleted = Storage::disk($storageDisk)->delete($path);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'error' => 'Failed to delete file from storage',
                ];
            }
            
            return [
                'success' => true,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'path' => $path,
                'storage_type' => $storageType ?? $this->driver,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => 'File deletion failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the configured storage driver
     * 
     * @return string
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Set the storage driver
     * 
     * @param string $driver
     * @return void
     */
    public function setDriver(string $driver): void
    {
        $this->driver = $driver;
    }
}

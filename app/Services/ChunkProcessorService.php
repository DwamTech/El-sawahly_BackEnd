<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileChunk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkProcessorService
{
    /**
     * Default chunk size in bytes (2MB)
     */
    private const DEFAULT_CHUNK_SIZE = 2097152; // 2MB

    /**
     * Temporary directory for storing chunks
     */
    private const TEMP_CHUNKS_DIR = 'temp/chunks';

    /**
     * Storage handler service
     */
    private StorageHandlerService $storageHandler;

    /**
     * Chunk size configuration
     */
    private int $chunkSize;

    public function __construct(StorageHandlerService $storageHandler)
    {
        $this->storageHandler = $storageHandler;
        $this->chunkSize = config('filesystems.chunk_size', self::DEFAULT_CHUNK_SIZE);
    }

    /**
     * Store a chunk temporarily
     * 
     * @param UploadedFile $chunk The uploaded chunk file
     * @param int $fileId The file ID this chunk belongs to
     * @param int $chunkIndex The index of this chunk (0-based)
     * @return array{success: bool, chunk_id: int|null, error: string|null}
     */
    public function storeChunk(UploadedFile $chunk, int $fileId, int $chunkIndex): array
    {
        try {
            // Verify file exists
            $file = File::find($fileId);
            if (!$file) {
                return [
                    'success' => false,
                    'chunk_id' => null,
                    'error' => 'File record not found',
                ];
            }

            // Validate chunk index
            if ($chunkIndex < 0 || $chunkIndex >= $file->total_chunks) {
                return [
                    'success' => false,
                    'chunk_id' => null,
                    'error' => "Invalid chunk index: {$chunkIndex}. Expected 0-" . ($file->total_chunks - 1),
                ];
            }

            // Check if chunk already exists
            $existingChunk = FileChunk::where('file_id', $fileId)
                ->where('chunk_index', $chunkIndex)
                ->first();

            if ($existingChunk) {
                return [
                    'success' => false,
                    'chunk_id' => null,
                    'error' => "Chunk {$chunkIndex} already uploaded",
                ];
            }

            // Generate temporary chunk path
            $chunkPath = $this->generateChunkPath($fileId, $chunkIndex);

            // Ensure chunk directory exists
            $chunkDir = storage_path('app/' . dirname($chunkPath));
            if (!is_dir($chunkDir)) {
                if (!mkdir($chunkDir, 0755, true)) {
                    Log::error('Failed to create chunk directory', [
                        'directory' => $chunkDir,
                        'file_id' => $fileId,
                        'chunk_index' => $chunkIndex,
                    ]);
                    return [
                        'success' => false,
                        'chunk_id' => null,
                        'error' => 'Failed to create chunk directory',
                    ];
                }
            }

            // Store chunk directly using move
            $fullChunkPath = storage_path('app/' . $chunkPath);
            
            // Get chunk size before moving (use getRealPath to get actual file path)
            $tempFilePath = $chunk->getRealPath();
            $chunkSize = $tempFilePath ? filesize($tempFilePath) : $chunk->getSize();
            
            try {
                $chunk->move(dirname($fullChunkPath), basename($fullChunkPath));
            } catch (\Exception $e) {
                Log::error('Failed to move chunk file', [
                    'error' => $e->getMessage(),
                    'destination' => $fullChunkPath,
                    'file_id' => $fileId,
                    'chunk_index' => $chunkIndex,
                ]);
                return [
                    'success' => false,
                    'chunk_id' => null,
                    'error' => 'Failed to move chunk file: ' . $e->getMessage(),
                ];
            }

            // Verify file was actually written to disk
            if (!file_exists($fullChunkPath)) {
                Log::error('Chunk file not found after move', [
                    'full_path' => $fullChunkPath,
                    'file_id' => $fileId,
                    'chunk_index' => $chunkIndex,
                ]);
                return [
                    'success' => false,
                    'chunk_id' => null,
                    'error' => 'Chunk file not found after storage',
                ];
            }

            $storedPath = $chunkPath;

            // Create chunk record in database
            $fileChunk = FileChunk::create([
                'file_id' => $fileId,
                'chunk_index' => $chunkIndex,
                'chunk_path' => $storedPath,
                'chunk_size' => $chunkSize,
                'uploaded_at' => now(),
            ]);

            // Update file's uploaded_chunks count
            $file->increment('uploaded_chunks');

            // Update file status to uploading if it's the first chunk
            if ($file->status === File::STATUS_PENDING) {
                $file->update(['status' => File::STATUS_UPLOADING]);
            }

            Log::info('Chunk stored successfully', [
                'file_id' => $fileId,
                'chunk_index' => $chunkIndex,
                'chunk_size' => $chunkSize,
                'uploaded_chunks' => $file->uploaded_chunks + 1,
                'total_chunks' => $file->total_chunks,
            ]);

            return [
                'success' => true,
                'chunk_id' => $fileChunk->id,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to store chunk', [
                'file_id' => $fileId,
                'chunk_index' => $chunkIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'chunk_id' => null,
                'error' => 'Chunk storage failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Assemble all chunks into the final file
     * 
     * @param int $fileId The file ID to assemble chunks for
     * @return array{success: bool, file_path: string|null, file_url: string|null, error: string|null}
     */
    public function assembleChunks(int $fileId): array
    {
        // Use savepoint for nested transaction support
        $useSavepoint = DB::transactionLevel() > 0;
        
        if ($useSavepoint) {
            // Already in a transaction, don't start a new one
            return $this->performAssembly($fileId);
        }

        DB::beginTransaction();

        try {
            $result = $this->performAssembly($fileId);
            
            if ($result['success']) {
                DB::commit();
            } else {
                DB::rollBack();
            }
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Perform the actual chunk assembly logic
     * 
     * @param int $fileId The file ID to assemble chunks for
     * @return array{success: bool, file_path: string|null, file_url: string|null, error: string|null}
     */
    private function performAssembly(int $fileId): array
    {
        try {
            // Get file record
            $file = File::find($fileId);
            if (!$file) {
                return [
                    'success' => false,
                    'file_path' => null,
                    'file_url' => null,
                    'error' => 'File record not found',
                ];
            }

            // Validate chunk sequence before assembly
            $validationResult = $this->validateChunkSequence($fileId);
            if (!$validationResult['success']) {
                return [
                    'success' => false,
                    'file_path' => null,
                    'file_url' => null,
                    'error' => $validationResult['error'],
                ];
            }

            // Get all chunks ordered by index
            $chunks = FileChunk::where('file_id', $fileId)
                ->orderBy('chunk_index')
                ->get();

            // Create temporary file for assembly
            $tempAssembledPath = storage_path('app/temp/assembled_' . $fileId . '_' . time());
            
            // Ensure temp directory exists
            $tempDir = dirname($tempAssembledPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $tempHandle = fopen($tempAssembledPath, 'wb');

            if (!$tempHandle) {
                throw new \Exception('Failed to create temporary file for assembly');
            }

            // Assemble chunks sequentially
            foreach ($chunks as $chunk) {
                $chunkFilePath = storage_path('app/' . $chunk->chunk_path);

                if (!file_exists($chunkFilePath)) {
                    fclose($tempHandle);
                    unlink($tempAssembledPath);
                    throw new \Exception("Chunk file not found: {$chunk->chunk_index}");
                }

                $chunkHandle = fopen($chunkFilePath, 'rb');
                if (!$chunkHandle) {
                    fclose($tempHandle);
                    unlink($tempAssembledPath);
                    throw new \Exception("Failed to read chunk: {$chunk->chunk_index}");
                }

                // Stream chunk content to assembled file
                while (!feof($chunkHandle)) {
                    $buffer = fread($chunkHandle, 8192);
                    fwrite($tempHandle, $buffer);
                }

                fclose($chunkHandle);
            }

            fclose($tempHandle);

            // Verify assembled file size matches expected size
            $assembledSize = filesize($tempAssembledPath);
            if ($assembledSize !== $file->file_size) {
                unlink($tempAssembledPath);
                throw new \Exception(
                    "Assembled file size mismatch. Expected: {$file->file_size}, Got: {$assembledSize}"
                );
            }

            // Create UploadedFile instance from assembled file
            $assembledFile = new \Illuminate\Http\UploadedFile(
                $tempAssembledPath,
                $file->original_name,
                $file->mime_type,
                null,
                true
            );

            // Get target path from metadata if available
            $targetPath = $file->metadata['target_path'] ?? null;

            // Store assembled file to permanent storage
            $storageResult = $this->storageHandler->storeFile(
                $assembledFile,
                $file->user_id,
                $file->file_name,
                $targetPath
            );

            // Clean up temporary assembled file
            if (file_exists($tempAssembledPath)) {
                unlink($tempAssembledPath);
            }

            if (!$storageResult['success']) {
                throw new \Exception($storageResult['error'] ?? 'Failed to store assembled file');
            }

            // Update file record with final path and URL
            $file->update([
                'file_path' => $storageResult['path'],
                'file_url' => $storageResult['url'],
                'status' => File::STATUS_COMPLETED,
            ]);

            // Clean up temporary chunks
            $this->cleanupChunks($fileId);

            Log::info('Chunks assembled successfully', [
                'file_id' => $fileId,
                'file_name' => $file->file_name,
                'file_size' => $file->file_size,
                'total_chunks' => $file->total_chunks,
                'file_url' => $storageResult['url'],
            ]);

            return [
                'success' => true,
                'file_path' => $storageResult['path'],
                'file_url' => $storageResult['url'],
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to assemble chunks', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update file status to failed
            if (isset($file)) {
                $file->update(['status' => File::STATUS_FAILED]);
            }

            return [
                'success' => false,
                'file_path' => null,
                'file_url' => null,
                'error' => 'Chunk assembly failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate that all chunks have been received in the correct sequence
     * 
     * @param int $fileId The file ID to validate chunks for
     * @return array{success: bool, missing_chunks: array<int>, error: string|null}
     */
    public function validateChunkSequence(int $fileId): array
    {
        try {
            // Get file record
            $file = File::find($fileId);
            if (!$file) {
                return [
                    'success' => false,
                    'missing_chunks' => [],
                    'error' => 'File record not found',
                ];
            }

            // Get all uploaded chunk indices
            $uploadedChunks = FileChunk::where('file_id', $fileId)
                ->orderBy('chunk_index')
                ->pluck('chunk_index')
                ->toArray();

            // Check if all chunks are present
            $expectedChunks = range(0, $file->total_chunks - 1);
            $missingChunks = array_diff($expectedChunks, $uploadedChunks);

            if (!empty($missingChunks)) {
                return [
                    'success' => false,
                    'missing_chunks' => array_values($missingChunks),
                    'error' => 'Missing chunks: ' . implode(', ', $missingChunks),
                ];
            }

            // Verify uploaded_chunks count matches
            if ((int)$file->uploaded_chunks !== (int)$file->total_chunks) {
                return [
                    'success' => false,
                    'missing_chunks' => [],
                    'error' => "Chunk count mismatch. Expected: {$file->total_chunks}, Got: {$file->uploaded_chunks}",
                ];
            }

            // Verify all chunk files exist on disk
            foreach ($uploadedChunks as $chunkIndex) {
                $chunk = FileChunk::where('file_id', $fileId)
                    ->where('chunk_index', $chunkIndex)
                    ->first();

                $chunkFilePath = storage_path('app/' . $chunk->chunk_path);
                if (!file_exists($chunkFilePath)) {
                    return [
                        'success' => false,
                        'missing_chunks' => [$chunkIndex],
                        'error' => "Chunk file missing on disk: {$chunkIndex}",
                    ];
                }
            }

            Log::info('Chunk sequence validated successfully', [
                'file_id' => $fileId,
                'total_chunks' => $file->total_chunks,
                'uploaded_chunks' => count($uploadedChunks),
            ]);

            return [
                'success' => true,
                'missing_chunks' => [],
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to validate chunk sequence', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'missing_chunks' => [],
                'error' => 'Chunk validation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Clean up temporary chunks after successful assembly
     * 
     * @param int $fileId The file ID to clean up chunks for
     * @return array{success: bool, deleted_count: int, error: string|null}
     */
    public function cleanupChunks(int $fileId): array
    {
        try {
            // Get all chunks for this file
            $chunks = FileChunk::where('file_id', $fileId)->get();

            $deletedCount = 0;
            $errors = [];

            foreach ($chunks as $chunk) {
                $chunkFilePath = storage_path('app/' . $chunk->chunk_path);

                // Delete chunk file from disk
                if (file_exists($chunkFilePath)) {
                    if (unlink($chunkFilePath)) {
                        $deletedCount++;
                    } else {
                        $errors[] = "Failed to delete chunk file: {$chunk->chunk_index}";
                    }
                }

                // Delete chunk record from database
                if ($chunk instanceof FileChunk) {
                    $chunk->delete();
                } else {
                    FileChunk::where('id', $chunk->id)->delete();
                }
            }

            // Clean up empty directories
            $this->cleanupEmptyDirectories($fileId);

            if (!empty($errors)) {
                Log::warning('Some chunks could not be deleted', [
                    'file_id' => $fileId,
                    'errors' => $errors,
                ]);
            }

            Log::info('Chunks cleaned up successfully', [
                'file_id' => $fileId,
                'deleted_count' => $deletedCount,
            ]);

            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'error' => !empty($errors) ? implode('; ', $errors) : null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to cleanup chunks', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'deleted_count' => 0,
                'error' => 'Chunk cleanup failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate temporary chunk path
     * 
     * @param int $fileId The file ID
     * @param int $chunkIndex The chunk index
     * @return string The chunk path
     */
    private function generateChunkPath(int $fileId, int $chunkIndex): string
    {
        return self::TEMP_CHUNKS_DIR . '/' . $fileId . '/chunk_' . $chunkIndex;
    }

    /**
     * Clean up empty directories after chunk deletion
     * 
     * @param int $fileId The file ID
     * @return void
     */
    private function cleanupEmptyDirectories(int $fileId): void
    {
        try {
            $chunkDir = storage_path('app/' . self::TEMP_CHUNKS_DIR . '/' . $fileId);

            if (is_dir($chunkDir)) {
                // Check if directory is empty
                $files = scandir($chunkDir);
                $files = array_diff($files, ['.', '..']);

                if (empty($files)) {
                    rmdir($chunkDir);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup empty directories', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the configured chunk size
     * 
     * @return int Chunk size in bytes
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Set the chunk size
     * 
     * @param int $size Chunk size in bytes
     * @return void
     */
    public function setChunkSize(int $size): void
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Chunk size must be greater than 0');
        }

        $this->chunkSize = $size;
    }
}

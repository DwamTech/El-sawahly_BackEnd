<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FileValidationService;
use App\Services\StorageHandlerService;
use App\Services\ChunkProcessorService;
use App\Models\File;
use App\Models\UploadSession;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FileUploadController extends Controller
{
    protected $validationService;
    protected $storageHandler;
    protected $chunkProcessor;

    public function __construct(
        FileValidationService $validationService,
        StorageHandlerService $storageHandler,
        ChunkProcessorService $chunkProcessor
    ) {
        $this->validationService = $validationService;
        $this->storageHandler = $storageHandler;
        $this->chunkProcessor = $chunkProcessor;
    }

    /**
     * Initiate a new file upload
     */
    public function initiateUpload(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'fileName' => 'required|string|max:255',
                'fileSize' => 'required|integer|min:1',
                'fileType' => 'required|string|in:image,video,audio,document,unclassified',
                'mimeType' => 'required|string|max:100',
                'totalChunks' => 'required|integer|min:1',
                'targetPath' => 'nullable|string', // Optional target directory path
                'sessionId' => 'nullable|string|uuid',
            ]);

            // Validate file metadata using the service
            // This replaces the inline checks for allowed mime types and dangerous extensions
            // to keep the logic centralized in the service while maintaining the controller flow.
            $validation = $this->validationService->validateMetadata(
                $validated['fileName'],
                $validated['fileSize'],
                $validated['mimeType']
            );

            if (!$validation['valid']) {
                // Map service errors to the response format expected by the frontend/test script
                $errorCode = 'VALIDATION_ERROR';
                $errorMessage = implode(' ', $validation['errors']);

                // Try to detect specific error types based on message content to match the reference logic
                if (str_contains($errorMessage, 'File type') && str_contains($errorMessage, 'not allowed')) {
                    $errorCode = 'INVALID_FILE_TYPE';
                } elseif (str_contains($errorMessage, 'File size') && str_contains($errorMessage, 'exceeds')) {
                    $errorCode = 'FILE_TOO_LARGE';
                } elseif (str_contains($errorMessage, 'suspicious double extension') || str_contains($errorMessage, 'extension') && str_contains($errorMessage, 'not allowed')) {
                    $errorCode = 'DANGEROUS_FILE_TYPE';
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ],
                ], 400);
            }

            // Use the sanitized filename from validation if available, or sanitize it here again to be safe
            $sanitizedFileName = $validation['sanitizedFilename'];
            
            if (empty($sanitizedFileName)) {
                // Fallback sanitization if service didn't return it (though it should)
                $sanitizedFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $validated['fileName']);
                $sanitizedFileName = preg_replace('/_+/', '_', $sanitizedFileName);
                $sanitizedFileName = trim($sanitizedFileName, '_');
            }

            if (empty($sanitizedFileName)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_FILENAME',
                        'message' => 'Filename contains invalid characters and cannot be sanitized.',
                    ],
                ], 400);
            }

            $userId = Auth::id();

            // Create or get active upload session
            $sessionId = $request->input('sessionId') ?? Str::uuid()->toString();
            $uploadSession = UploadSession::firstOrCreate(
                [
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                ],
                [
                    'status' => UploadSession::STATUS_ACTIVE,
                    'total_files' => 0,
                    'completed_files' => 0,
                    'failed_files' => 0,
                    'expires_at' => now()->addHours(24),
                ]
            );

            // Increment total files count
            $uploadSession->increment('total_files');

            // Create file record
            $metadata = [];
            if (!empty($validated['targetPath'])) {
                $metadata['target_path'] = $validated['targetPath'];
            }

            $file = File::create([
                'user_id' => $userId,
                'file_name' => $sanitizedFileName,
                'original_name' => $validated['fileName'],
                'file_type' => $validated['fileType'],
                'mime_type' => $validated['mimeType'],
                'file_size' => $validated['fileSize'],
                'file_path' => '', // Will be set after upload completion
                'file_url' => '',  // Will be set after upload completion
                'status' => File::STATUS_PENDING,
                'upload_session_id' => $uploadSession->session_id,
                'total_chunks' => $validated['totalChunks'],
                'uploaded_chunks' => 0,
                'metadata' => $metadata,
            ]);

            Log::info('Upload initiated', [
                'file_id' => $file->id,
                'file_name' => $file->file_name,
                'file_size' => $file->file_size,
                'total_chunks' => $file->total_chunks,
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'fileId' => $file->id,
                    'sessionId' => $sessionId,
                    'uploadUrl' => route('files.upload.chunk'), // Ensure this route exists
                    'expiresAt' => $uploadSession->expires_at->toIso8601String(),
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                ],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Upload initiation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPLOAD_INITIATION_FAILED',
                    'message' => 'Failed to initiate upload',
                    'details' => config('app.debug') ? ['error' => $e->getMessage()] : [],
                ],
            ], 500);
        }
    }

    /**
     * Upload a file chunk
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fileId' => 'required|exists:files,id',
                'chunkIndex' => 'required|integer|min:0',
                'chunk' => 'required|file|max:' . (config('filesystems.chunk_size', 2097152) / 1024 + 1024), // Chunk size + buffer
            ]);

            $fileId = $validated['fileId'];
            $chunkIndex = $validated['chunkIndex'];
            $chunk = $request->file('chunk');

            // Verify file ownership
            $file = File::where('id', $fileId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'File not found or access denied',
                    ],
                ], 404);
            }

            // Store chunk
            $storeResult = $this->chunkProcessor->storeChunk($chunk, $fileId, $chunkIndex);

            if (!$storeResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CHUNK_UPLOAD_FAILED',
                        'message' => $storeResult['error'],
                    ],
                ], 400);
            }

            // Check if all chunks uploaded
            $file->refresh();
            if ($file->uploaded_chunks >= $file->total_chunks) {
                // Assemble chunks
                $assemblyResult = $this->chunkProcessor->assembleChunks($fileId);

                if (!$assemblyResult['success']) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'ASSEMBLY_FAILED',
                            'message' => $assemblyResult['error'],
                        ],
                    ], 500);
                }

                // Update session stats
                UploadSession::where('session_id', $file->upload_session_id)
                    ->increment('completed_files');

                return response()->json([
                    'success' => true,
                    'data' => [
                        'fileId' => $file->id,
                        'status' => 'completed',
                        'filePath' => $assemblyResult['file_path'],
                        'fileUrl' => $assemblyResult['file_url'],
                        'completed' => true,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'fileId' => $file->id,
                    'status' => 'uploading',
                    'chunkIndex' => $chunkIndex,
                    'uploadedChunks' => $file->uploaded_chunks,
                    'totalChunks' => $file->total_chunks,
                    'completed' => false,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Chunk upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHUNK_UPLOAD_ERROR',
                    'message' => 'Unexpected error during chunk upload',
                    'details' => config('app.debug') ? ['error' => $e->getMessage()] : [],
                ],
            ], 500);
        }
    }

    /**
     * Finalize file upload
     * 
     * POST /api/files/upload/finalize
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function finalizeUpload(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'fileId' => 'required|integer|exists:files,id',
            ]);

            $fileId = $validated['fileId'];

            // Verify file belongs to authenticated user
            $file = File::where('id', $fileId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'File not found or access denied',
                    ],
                ], 404);
            }

            // Check if file is already completed
            if ($file->status === File::STATUS_COMPLETED) {
                 return response()->json([
                    'success' => true,
                    'data' => [
                        'fileId' => $file->id,
                        'url' => $file->file_url,
                        'fileName' => $file->file_name,
                        'fileSize' => $file->file_size,
                        'fileType' => $file->file_type,
                        'uploadedAt' => $file->updated_at->toIso8601String(),
                        'message' => 'File already completed',
                    ],
                ], 200);
            }

            // Validate all chunks are received
            $validationResult = $this->chunkProcessor->validateChunkSequence($fileId);
            if (!$validationResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INCOMPLETE_UPLOAD',
                        'message' => $validationResult['error'],
                        'details' => [
                            'missing_chunks' => $validationResult['missing_chunks'] ?? [],
                        ],
                    ],
                ], 400);
            }

            // Assemble chunks into final file
            $assemblyResult = $this->chunkProcessor->assembleChunks($fileId);

            if (!$assemblyResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ASSEMBLY_FAILED',
                        'message' => $assemblyResult['error'],
                    ],
                ], 500);
            }

            // Update upload session statistics
            $uploadSession = UploadSession::where('session_id', $file->upload_session_id)->first();
            if ($uploadSession) {
                $uploadSession->increment('completed_files');
            }

            // Refresh file to get updated data
            $file->refresh();

            Log::info('Upload finalized successfully', [
                'file_id' => $fileId,
                'file_name' => $file->file_name,
                'file_url' => $file->file_url,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'fileId' => $file->id,
                    'url' => $file->file_url,
                    'fileName' => $file->file_name,
                    'fileSize' => $file->file_size,
                    'fileType' => $file->file_type,
                    'uploadedAt' => $file->updated_at->toIso8601String(),
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                ],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Upload finalization failed', [
                'file_id' => $request->input('fileId'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FINALIZATION_FAILED',
                    'message' => 'Failed to finalize upload',
                    'details' => config('app.debug') ? ['error' => $e->getMessage()] : [],
                ],
            ], 500);
        }
    }

    /**
     * Cancel file upload
     * 
     * DELETE /api/files/upload/cancel/{fileId}
     * 
     * @param int $fileId
     * @return JsonResponse
     */
    public function cancelUpload($fileId): JsonResponse
    {
        try {
            // Verify file belongs to authenticated user
            $file = File::where('id', $fileId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'File not found or access denied',
                    ],
                ], 404);
            }

            // Check if file is already completed
            if ($file->status === File::STATUS_COMPLETED) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CANNOT_CANCEL_COMPLETED',
                        'message' => 'Cannot cancel a completed upload',
                    ],
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Clean up temporary chunks
                $cleanupResult = $this->chunkProcessor->cleanupChunks($fileId);

                // Update file status to cancelled
                $file->update(['status' => File::STATUS_FAILED]);

                // Update upload session statistics
                $uploadSession = UploadSession::where('session_id', $file->upload_session_id)->first();
                if ($uploadSession) {
                    $uploadSession->increment('failed_files');
                }

                DB::commit();

                Log::info('Upload cancelled successfully', [
                    'file_id' => $fileId,
                    'file_name' => $file->file_name,
                    'chunks_deleted' => $cleanupResult['deleted_count'],
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'fileId' => $fileId,
                        'message' => 'Upload cancelled successfully',
                        'chunksDeleted' => $cleanupResult['deleted_count'],
                    ],
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Upload cancellation failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANCELLATION_FAILED',
                    'message' => 'Failed to cancel upload',
                    'details' => config('app.debug') ? ['error' => $e->getMessage()] : [],
                ],
            ], 500);
        }
    }

}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileExplorerController extends Controller
{
    /**
     * Browse directory contents
     * 
     * GET /api/files/explorer/browse?path={path}
     */
    public function browse(Request $request): JsonResponse
    {
        try {
            $path = $request->query('path', '');
            
            // Sanitize and validate path
            $sanitizedPath = $this->sanitizePath($path);
            
            // Get storage disk
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('public');
            
            // Check if path exists
            if (!$disk->exists($sanitizedPath) && $sanitizedPath !== '') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PATH_NOT_FOUND',
                        'message' => 'المسار غير موجود',
                    ],
                ], 404);
            }
            
            // Get directories and files
            $directories = [];
            $files = [];
            
            // Get directories
            $dirPaths = $disk->directories($sanitizedPath);
            foreach ($dirPaths as $dirPath) {
                $directories[] = [
                    'name' => basename($dirPath),
                    'path' => $dirPath,
                    'type' => 'directory',
                    'size' => null,
                    'modified_at' => $disk->lastModified($dirPath),
                ];
            }

            // Get files
            $filePaths = $disk->files($sanitizedPath);
            foreach ($filePaths as $filePath) {
                $files[] = [
                    'name' => basename($filePath),
                    'path' => $filePath,
                    'type' => 'file',
                    'size' => $disk->size($filePath),
                    'mime_type' => $disk->mimeType($filePath),
                    'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                    'url' => $disk->url($filePath),
                    'modified_at' => $disk->lastModified($filePath),
                ];
            }
            
            // Sort directories and files by name
            usort($directories, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            
            return response()->json([
                'success' => true,
                'data' => [
                    'current_path' => $sanitizedPath,
                    'parent_path' => $this->getParentPath($sanitizedPath),
                    'directories' => $directories,
                    'files' => $files,
                    'total_items' => count($directories) + count($files),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to browse directory', [
                'path' => $request->query('path'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BROWSE_FAILED',
                    'message' => 'فشل في تصفح المجلد',
                ],
            ], 500);
        }
    }
    
    /**
     * Rename file or directory
     * 
     * POST /api/files/explorer/rename
     */
    public function rename(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'path' => 'required|string',
                'new_name' => 'required|string|max:255',
            ]);
            
            $path = $this->sanitizePath($validated['path']);
            $newName = $this->sanitizeFileName($validated['new_name']);
            
            $disk = Storage::disk('public');
            
            if (!$disk->exists($path)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'الملف غير موجود',
                    ],
                ], 404);
            }
            
            // Get parent directory
            $parentPath = dirname($path);
            $newPath = ($parentPath === '.' ? '' : $parentPath . '/') . $newName;
            
            // Check if new name already exists
            if ($disk->exists($newPath)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NAME_EXISTS',
                        'message' => 'الاسم موجود بالفعل',
                    ],
                ], 400);
            }
            
            // Rename
            $disk->move($path, $newPath);
            
            Log::info('File renamed', [
                'old_path' => $path,
                'new_path' => $newPath,
                'user_id' => Auth::id(),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'old_path' => $path,
                    'new_path' => $newPath,
                    'message' => 'تم إعادة التسمية بنجاح',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'بيانات غير صحيحة',
                    'details' => $e->errors(),
                ],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to rename file', [
                'path' => $request->input('path'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RENAME_FAILED',
                    'message' => 'فشل في إعادة التسمية',
                ],
            ], 500);
        }
    }
    
    /**
     * Delete file or directory
     * 
     * DELETE /api/files/explorer/delete
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'path' => 'required|string',
            ]);
            
            $path = $this->sanitizePath($validated['path']);
            $disk = Storage::disk('public');
            
            if (!$disk->exists($path)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'الملف غير موجود',
                    ],
                ], 404);
            }
            
            // Check if it's a directory
            if (is_dir($disk->path($path))) {
                $disk->deleteDirectory($path);
            } else {
                $disk->delete($path);
            }
            
            Log::info('File deleted', [
                'path' => $path,
                'user_id' => Auth::id(),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'تم الحذف بنجاح',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'بيانات غير صحيحة',
                    'details' => $e->errors(),
                ],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to delete file', [
                'path' => $request->input('path'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => 'فشل في الحذف',
                ],
            ], 500);
        }
    }
    
    /**
     * Create new folder
     * 
     * POST /api/files/explorer/create-folder
     */
    public function createFolder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'path' => 'required|string',
                'name' => 'required|string|max:255',
            ]);
            
            $parentPath = $this->sanitizePath($validated['path']);
            $folderName = $this->sanitizeFileName($validated['name']);
            
            $disk = Storage::disk('public');
            
            // Create full path
            $fullPath = ($parentPath === '' ? '' : $parentPath . '/') . $folderName;
            
            // Check if folder already exists
            if ($disk->exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FOLDER_EXISTS',
                        'message' => 'المجلد موجود بالفعل',
                    ],
                ], 400);
            }
            
            // Create folder
            $disk->makeDirectory($fullPath);
            
            Log::info('Folder created', [
                'path' => $fullPath,
                'user_id' => Auth::id(),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'path' => $fullPath,
                    'message' => 'تم إنشاء المجلد بنجاح',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'بيانات غير صحيحة',
                    'details' => $e->errors(),
                ],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to create folder', [
                'path' => $request->input('path'),
                'name' => $request->input('name'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATE_FOLDER_FAILED',
                    'message' => 'فشل في إنشاء المجلد',
                ],
            ], 500);
        }
    }
    
    /**
     * Download file
     * 
     * GET /api/files/explorer/download?path={path}
     */
    public function download(Request $request): mixed
    {
        try {
            $path = $this->sanitizePath($request->query('path', ''));
            $disk = Storage::disk('public');
            
            if (!$disk->exists($path)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'الملف غير موجود',
                    ],
                ], 404);
            }
            
            // Check if it's a file (not directory)
            if (is_dir($disk->path($path))) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_FILE',
                        'message' => 'لا يمكن تحميل مجلد',
                    ],
                ], 400);
            }
            
            Log::info('File downloaded', [
                'path' => $path,
                'user_id' => Auth::id(),
            ]);
            
            $fullPath = Storage::disk('public')->path($path);
            return response()->download($fullPath);
        } catch (\Exception $e) {
            Log::error('Failed to download file', [
                'path' => $request->query('path'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_FAILED',
                    'message' => 'فشل في تحميل الملف',
                ],
            ], 500);
        }
    }
    
    /**
     * Sanitize path to prevent directory traversal
     */
    private function sanitizePath(string $path): string
    {
        // Remove leading/trailing slashes
        $path = trim($path, '/\\');
        
        // Normalize path separators (convert backslashes to forward slashes)
        $path = str_replace('\\', '/', $path);
        
        // Remove any ../ or ./ sequences
        $path = preg_replace('#/\.+/#', '/', $path);
        $path = preg_replace('#^\.+/#', '', $path);
        
        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);
        
        return $path;
    }
    
    /**
     * Sanitize file/folder name
     */
    private function sanitizeFileName(string $name): string
    {
        // Remove path separators
        $name = str_replace(['/', '\\'], '', $name);
        
        // Remove dangerous characters but keep Arabic, English, numbers, and common symbols
        $name = preg_replace('/[^\p{Arabic}a-zA-Z0-9\s._-]/u', '', $name);
        
        // Trim whitespace
        $name = trim($name);
        
        return $name;
    }
    
    /**
     * Get parent path
     */
    private function getParentPath(string $path): ?string
    {
        if ($path === '' || $path === '/') {
            return null;
        }
        
        $parent = dirname($path);
        
        if ($parent === '.' || $parent === '/') {
            return '';
        }
        
        return $parent;
    }
}

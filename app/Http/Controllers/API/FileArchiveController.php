<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class FileArchiveController extends Controller
{
    /**
     * Display a paginated list of archived files for the authenticated user.
     * GET /api/files/archive
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = 50;
        
        // Use eager loading for relationships
        $files = File::where('user_id', Auth::id())
            ->with(['user:id,name,email']) // Eager load user relationship if needed
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'files' => $files->items(),
                'pagination' => [
                    'current_page' => $files->currentPage(),
                    'per_page' => $files->perPage(),
                    'total' => $files->total(),
                    'last_page' => $files->lastPage(),
                    'from' => $files->firstItem(),
                    'to' => $files->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Display a single archived file with user access verification.
     * GET /api/files/archive/{id}
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $file = File::find($id);
        
        if (!$file) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FILE_NOT_FOUND',
                    'message' => 'File not found',
                ],
            ], 404);
        }
        
        // Verify user has access to this file
        if ($file->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED_ACCESS',
                    'message' => 'You do not have permission to access this file',
                ],
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $file,
        ]);
    }

    /**
     * Search and filter archived files with sorting and pagination.
     * POST /api/files/archive/search
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $request->validate([
            'filename' => 'nullable|string',
            'file_type' => 'nullable|string|in:image,video,audio,document,unclassified',
            'status' => 'nullable|string|in:pending,uploading,completed,failed',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort_by' => 'nullable|string|in:name,date,size,type',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = File::where('user_id', Auth::id());

        // Apply filename filter (case-insensitive)
        if ($request->filled('filename')) {
            $query->where('file_name', 'like', '%' . $request->filename . '%');
        }

        // Apply file type filter
        if ($request->filled('file_type')) {
            $query->where('file_type', $request->file_type);
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Apply date range filter (inclusive)
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'date');
        $sortOrder = $request->input('sort_order', 'desc');

        switch ($sortBy) {
            case 'name':
                $query->orderBy('file_name', $sortOrder);
                break;
            case 'size':
                $query->orderBy('file_size', $sortOrder);
                break;
            case 'type':
                $query->orderBy('file_type', $sortOrder);
                break;
            case 'date':
            default:
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        // Apply pagination
        $perPage = $request->input('per_page', 50);
        $files = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'files' => $files->items(),
                'pagination' => [
                    'current_page' => $files->currentPage(),
                    'per_page' => $files->perPage(),
                    'total' => $files->total(),
                    'last_page' => $files->lastPage(),
                    'from' => $files->firstItem(),
                    'to' => $files->lastItem(),
                ],
            ],
        ]);
    }
}

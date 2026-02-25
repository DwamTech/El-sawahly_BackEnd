<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents (Public).
     */
    public function index(Request $request)
    {
        $query = Document::with(['user:id,name', 'section']);

        // Filter by section
        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        // Search by title or keywords
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%");
            });
        }

        // Filter by file type
        if ($request->has('file_type')) {
            $query->where('file_type', $request->file_type);
        }

        $documents = $query->latest()->paginate(20);

        return response()->json($documents);
    }

    /**
     * Store a newly created document (Admin).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:1048576',
            'description' => 'nullable|string',
            'source_type' => 'required|in:file,link',
            'file_path' => 'nullable|required_if:source_type,file|file|mimes:pdf,doc,docx,xlsx,xls,ppt,pptx,txt,zip,rar|max:1048576', // 100MB
            'source_link' => 'nullable|required_if:source_type,link|url',
            'cover_type' => 'required|in:auto,upload',
            'cover_path' => 'nullable|required_if:cover_type,upload|image|max:1048576',
            'keywords' => 'nullable', // Can be array or JSON string
            'section_id' => 'nullable|exists:sections,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = auth()->id();

        // Handle File Upload
        if ($request->hasFile('file_path')) {
            $file = $request->file('file_path');
            $data['file_path'] = $file->store('documents/files', 'public');
            $data['file_type'] = $file->getClientOriginalExtension();
            $data['file_size'] = $file->getSize();
        }

        // Handle Cover Upload
        if ($request->cover_type === 'upload' && $request->hasFile('cover_path')) {
            $data['cover_path'] = $request->file('cover_path')->store('documents/covers', 'public');
        } elseif ($request->cover_type === 'auto') {
            $data['cover_path'] = 'defaults/document_cover.png';
        }

        // Handle keywords: if it's a JSON string (from multipart), decode it
        if (isset($data['keywords']) && is_string($data['keywords'])) {
            $data['keywords'] = json_decode($data['keywords'], true) ?: [];
        }

        $document = Document::create($data);

        return response()->json([
            'message' => 'تم إضافة الملف بنجاح',
            'data' => $document,
        ], 201);
    }

    /**
     * Display the specified document (Public).
     */
    public function show($id)
    {
        $document = Document::with(['user:id,name', 'section'])->find($id);

        if (! $document) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        // Increment views
        $document->increment('views_count');

        return response()->json($document);
    }

    /**
     * Update the specified document (Admin).
     */
    public function update(Request $request, $id)
    {
        $document = Document::find($id);

        if (! $document) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:1048576',
            'description' => 'nullable|string',
            'source_type' => 'in:file,link',
            'file_path' => 'nullable|file|mimes:pdf,doc,docx,xlsx,xls,ppt,pptx,txt,zip,rar|max:1048576',
            'source_link' => 'nullable|url',
            'cover_type' => 'in:auto,upload',
            'cover_path' => 'nullable|image|max:1048576',
            'keywords' => 'nullable', // Can be array or JSON string
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Handle File Upload
        if ($request->hasFile('file_path')) {
            // Delete old file if exists
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            $file = $request->file('file_path');
            $data['file_path'] = $file->store('documents/files', 'public');
            $data['file_type'] = $file->getClientOriginalExtension();
            $data['file_size'] = $file->getSize();
        }

        // Handle Cover Upload
        if ($request->has('cover_type')) {
            if ($request->cover_type === 'upload' && $request->hasFile('cover_path')) {
                if ($document->cover_path && Storage::disk('public')->exists($document->cover_path)) {
                    Storage::disk('public')->delete($document->cover_path);
                }
                $data['cover_path'] = $request->file('cover_path')->store('documents/covers', 'public');
            } elseif ($request->cover_type === 'auto') {
                $data['cover_path'] = 'defaults/document_cover.png';
            }
        }

        // Handle keywords: if it's a JSON string, decode it
        if (isset($data['keywords']) && is_string($data['keywords'])) {
            $data['keywords'] = json_decode($data['keywords'], true) ?: [];
        }

        $document->update($data);

        return response()->json([
            'message' => 'تم تحديث الملف بنجاح',
            'data' => $document,
        ]);
    }

    /**
     * Remove the specified document (Admin).
     */
    public function destroy($id)
    {
        $document = Document::find($id);

        if (! $document) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        // Delete files from storage
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        if ($document->cover_path && Storage::disk('public')->exists($document->cover_path)) {
            Storage::disk('public')->delete($document->cover_path);
        }

        $document->delete();

        return response()->json(['message' => 'تم حذف الملف بنجاح']);
    }

    /**
     * Increment download count (Public).
     */
    public function download($id)
    {
        $document = Document::find($id);

        if (! $document) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        $document->increment('downloads_count');

        return response()->json([
            'message' => 'تم تسجيل التحميل',
            'download_url' => $document->source_type === 'file' ? Storage::url($document->file_path) : $document->source_link,
        ]);
    }
}

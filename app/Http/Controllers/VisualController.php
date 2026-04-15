<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisualRequest;
use App\Http\Requests\UpdateVisualRequest;
use App\Models\Section;
use App\Models\Visual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VisualController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Visual::with(['section', 'user']);

        if ($request->filled('section_id')) {
            $section = Section::resolveReference($request->input('section_id'));
            if (! $section) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => max(1, min((int) $request->input('per_page', 15), 500)),
                    'total' => 0,
                ]);
            }

            $query->where('section_id', $section->id);
        }

        if ($request->filled('author')) {
            $query->where('user_id', $request->author);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 500));
        $visuals = $query->latest()->paginate($perPage);

        return response()->json($visuals);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVisualRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Handle File Upload
        if ($request->hasFile('file') && $data['type'] === 'upload') {
            $data['file_path'] = $request->file('file')->store('visuals/videos', 'public');
        }

        // Handle Thumbnail Upload
        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('visuals/thumbnails', 'public');
        }

        $visual = Visual::create($data);

        return response()->json([
            'message' => 'Visual created successfully',
            'visual' => $visual,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $visual = Visual::with(['section', 'user'])->findOrFail($id);
        $visual->increment('views_count');

        $next = Visual::where('section_id', $visual->section_id)
            ->where('id', '!=', $visual->id)
            ->where('id', '<', $visual->id)
            ->orderByDesc('id')
            ->select('id', 'title', 'thumbnail', 'created_at')
            ->first();

        return response()->json([
            'visual' => $visual,
            'next'   => $next,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVisualRequest $request, Visual $visual)
    {
        $data = $request->validated();

        // Handle File Upload
        if ($request->hasFile('file') && isset($data['type']) && $data['type'] === 'upload') {
            // Delete old file if exists
            if ($visual->file_path) {
                Storage::disk('public')->delete($visual->getRawOriginal('file_path'));
            }
            $data['file_path'] = $request->file('file')->store('visuals/videos', 'public');
        }

        // Handle Thumbnail Upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if exists
            if ($visual->thumbnail) {
                Storage::disk('public')->delete($visual->getRawOriginal('thumbnail'));
            }
            $data['thumbnail'] = $request->file('thumbnail')->store('visuals/thumbnails', 'public');
        }

        $visual->update($data);

        return response()->json([
            'message' => 'Visual updated successfully',
            'visual' => $visual,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Visual $visual)
    {
        if ($visual->file_path) {
            $original = $visual->getRawOriginal('file_path');
            if ($original && ! str_starts_with((string) $original, 'http://') && ! str_starts_with((string) $original, 'https://')) {
                Storage::disk('public')->delete($original);
            }
        }

        if ($visual->thumbnail) {
            $original = $visual->getRawOriginal('thumbnail');
            if ($original && ! str_starts_with((string) $original, 'http://') && ! str_starts_with((string) $original, 'https://')) {
                Storage::disk('public')->delete($original);
            }
        }

        $visual->delete();

        return response()->json([
            'message' => 'Visual deleted successfully',
        ]);
    }
}

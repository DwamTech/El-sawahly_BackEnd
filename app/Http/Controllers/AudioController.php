<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAudioRequest;
use App\Http\Requests\UpdateAudioRequest;
use App\Models\Audio;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AudioController extends Controller
{
    public function index(Request $request)
    {
        $query = Audio::with(['section', 'user']);

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
        $audios = $query->latest()->paginate($perPage);

        return response()->json($audios);
    }

    public function store(StoreAudioRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('file') && $data['type'] === 'upload') {
            $data['file_path'] = $request->file('file')->store('audios/files', 'public');
        }

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('audios/thumbnails', 'public');
        }

        $audio = Audio::create($data);

        return response()->json([
            'message' => 'Audio created successfully',
            'audio' => $audio,
        ], 201);
    }

    public function show($id)
    {
        $audio = Audio::with(['section', 'user'])->findOrFail($id);
        $audio->increment('views_count');

        $next = Audio::where('section_id', $audio->section_id)
            ->where('id', '!=', $audio->id)
            ->where('id', '<', $audio->id)
            ->orderByDesc('id')
            ->select('id', 'title', 'thumbnail', 'created_at')
            ->first();

        return response()->json([
            'audio' => $audio,
            'next'  => $next,
        ]);
    }

    public function update(UpdateAudioRequest $request, Audio $audio)
    {
        $data = $request->validated();

        if ($request->hasFile('file') && isset($data['type']) && $data['type'] === 'upload') {
            if ($audio->file_path) {
                Storage::disk('public')->delete($audio->getRawOriginal('file_path'));
            }

            $data['file_path'] = $request->file('file')->store('audios/files', 'public');
        }

        if ($request->hasFile('thumbnail')) {
            if ($audio->thumbnail) {
                $original = $audio->getRawOriginal('thumbnail');
                if (! str_starts_with((string) $original, 'http://') && ! str_starts_with((string) $original, 'https://')) {
                    Storage::disk('public')->delete($original);
                }
            }

            $data['thumbnail'] = $request->file('thumbnail')->store('audios/thumbnails', 'public');
        }

        $audio->update($data);

        return response()->json([
            'message' => 'Audio updated successfully',
            'audio' => $audio,
        ]);
    }

    public function destroy(Audio $audio)
    {
        if ($audio->file_path) {
            $original = $audio->getRawOriginal('file_path');
            if ($original && ! str_starts_with((string) $original, 'http://') && ! str_starts_with((string) $original, 'https://')) {
                Storage::disk('public')->delete($original);
            }
        }

        if ($audio->thumbnail) {
            $original = $audio->getRawOriginal('thumbnail');
            if ($original && ! str_starts_with((string) $original, 'http://') && ! str_starts_with((string) $original, 'https://')) {
                Storage::disk('public')->delete($original);
            }
        }

        $audio->delete();

        return response()->json([
            'message' => 'Audio deleted successfully',
        ]);
    }
}

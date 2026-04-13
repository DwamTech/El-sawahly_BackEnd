<?php

namespace App\Http\Controllers;

use App\Models\Audio;
use App\Models\Book;
use App\Models\Article;
use App\Models\Section;
use App\Models\Visual;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SectionController extends Controller
{
    // ---------------------------------------------------------------
    // PUBLIC
    // ---------------------------------------------------------------

    /**
     * GET /sections
     * List all active sections (no content).
     */
    public function index()
    {
        $sections = Section::where('is_active', true)->get();

        return response()->json($sections);
    }

    /**
     * GET /sections/{id}
     * Single section with ALL its content (type-aware).
     */
    public function show($id)
    {
        $section = Section::where('is_active', true)->findOrFail($id);

        return response()->json([
            'section' => $section,
            'content' => $this->resolveContent($section),
        ]);
    }

    /**
     * GET /homepage
     * All active sections, each with a limited preview of its content.
     * Default limit = 5, except "فتاوي" = 9.
     */
    public function homepage()
    {
        $sections = Section::where('is_active', true)->get();

        $result = $sections->map(function (Section $section) {
            $limit = $section->name === 'فتاوي' ? 9 : 5;

            return [
                'section' => $section,
                'content' => $this->resolveContent($section, $limit),
            ];
        });

        return response()->json($result);
    }

    // ---------------------------------------------------------------
    // ADMIN CRUD
    // ---------------------------------------------------------------

    /**
     * POST /sections
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:sections,name',
            'name_sw'     => 'nullable|string|max:255',
            'type'        => 'required|in:مقال,كتب,فيديو,صوت',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $data['slug']    = Str::slug($data['name'].'-'.($data['name_sw'] ?? uniqid()));
        $data['user_id'] = $request->user()->id;

        $section = Section::create($data);

        return response()->json([
            'message' => 'Section created successfully',
            'section' => $section,
        ], 201);
    }

    /**
     * PUT /sections/{section}
     */
    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:sections,name,'.$section->id,
            'name_sw'     => 'nullable|string|max:255',
            'type'        => 'sometimes|in:مقال,كتب,فيديو,صوت',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name'].'-'.($data['name_sw'] ?? $section->name_sw ?? uniqid()));
        }

        $section->update($data);

        return response()->json([
            'message' => 'Section updated successfully',
            'section' => $section->fresh(),
        ]);
    }

    /**
     * DELETE /sections/{section}
     */
    public function destroy(Section $section)
    {
        $section->delete();

        return response()->json(['message' => 'Section deleted successfully']);
    }

    // ---------------------------------------------------------------
    // PRIVATE HELPERS
    // ---------------------------------------------------------------

    /**
     * Return content items from the correct module based on section type.
     */
    private function resolveContent(Section $section, int $limit = PHP_INT_MAX): array
    {
        return match ($section->type) {
            Section::TYPE_ARTICLE => $this->fetchArticles($section->id, $limit),
            Section::TYPE_BOOK    => $this->fetchBooks($section->id, $limit),
            Section::TYPE_VIDEO   => $this->fetchVideos($section->id, $limit),
            Section::TYPE_AUDIO   => $this->fetchAudios($section->id, $limit),
            default               => [],
        };
    }

    // ---------------------------------------------------------------
    // PUBLIC CONTENT ENDPOINTS  GET /sections/{id}/articles|books|videos|audios
    // ---------------------------------------------------------------

    /** GET /sections/{id}/articles */
    public function getArticles(Request $request, int $id)
    {
        $section = Section::where('is_active', true)->findOrFail($id);

        $items = Article::where('section_id', $section->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /** GET /sections/{id}/books */
    public function getBooks(Request $request, int $id)
    {
        $section = Section::where('is_active', true)->findOrFail($id);

        $items = Book::where('section_id', $section->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /** GET /sections/{id}/videos */
    public function getVideos(Request $request, int $id)
    {
        $section = Section::where('is_active', true)->findOrFail($id);

        $items = Visual::where('section_id', $section->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    /** GET /sections/{id}/audios */
    public function getAudios(Request $request, int $id)
    {
        $section = Section::where('is_active', true)->findOrFail($id);

        $items = Audio::where('section_id', $section->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    // ---------------------------------------------------------------
    // PRIVATE FETCH HELPERS (used internally by resolveContent)
    // ---------------------------------------------------------------

    private function fetchArticles(int $sectionId, int $limit): array
    {
        return Article::where('section_id', $sectionId)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function fetchBooks(int $sectionId, int $limit): array
    {
        return Book::where('section_id', $sectionId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function fetchVideos(int $sectionId, int $limit): array
    {
        return Visual::where('section_id', $sectionId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function fetchAudios(int $sectionId, int $limit): array
    {
        return Audio::where('section_id', $sectionId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}

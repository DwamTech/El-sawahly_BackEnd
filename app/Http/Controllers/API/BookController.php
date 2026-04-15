<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookController extends Controller
{
    // Public: List Books
    public function index(Request $request)
    {
        $query = Book::with('section');

        if ($request->has('section_id')) {
            $section = Section::resolveReference($request->input('section_id'));
            if (! $section) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => max(1, min((int) $request->input('per_page', 20), 500)),
                    'total' => 0,
                ]);
            }

            $query->where('section_id', $section->id);
        }

        if ($request->has('series_id')) {
            $query->where('book_series_id', $request->series_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 500));
        $books = $query->latest()->paginate($perPage);

        return response()->json($books);
    }

    // Public: Show Book
    public function show($id)
    {
        $book = Book::with(['series', 'section'])->find($id);

        if (! $book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $book->increment('views_count');

        $next = Book::where('section_id', $book->section_id)
            ->where('id', '!=', $book->id)
            ->where('id', '<', $book->id)
            ->orderByDesc('id')
            ->select('id', 'title', 'author_name', 'cover_path', 'cover_type', 'created_at')
            ->first();

        $response = [
            'book' => $book,
            'next' => $next,
        ];

        if ($book->type === 'part' && $book->book_series_id) {
            $siblings = Book::where('book_series_id', $book->book_series_id)
                ->where('id', '!=', $book->id)
                ->select('id', 'title', 'cover_path', 'cover_type')
                ->get();
            $response['related_parts'] = $siblings;
        }

        return response()->json($response);
    }

    // Public: Rate Book
    public function rate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|numeric|min:1|max:5', // float accepted
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $book = Book::find($id);
        if (! $book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $book->increment('rating_count');
        $book->increment('rating_sum', $request->rating);

        return response()->json([
            'message' => 'تم التقييم بنجاح',
            'average_rating' => $book->average_rating,
        ]);
    }

    // Admin: Store Book
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:1048576',
            'description' => 'required|string',
            'source_type' => 'required|in:file,link,embed',
            // if file -> file required. if link/embed -> source_link required
            'file_path' => 'nullable|required_if:source_type,file|file|mimes:pdf,doc,docx,epub|max:1048576', // 50MB
            'source_link' => 'nullable|required_if:source_type,link,embed|string',
            'cover_type' => 'required|in:auto,upload',
            'cover_path' => 'nullable|required_if:cover_type,upload|image|max:1048576',
            'keywords' => 'nullable|array',
            'author_name' => 'required|string|max:1048576',
            'type' => 'required|in:single,part',
            'book_series_id' => 'nullable|required_if:type,part|exists:book_series,id',
            'section_id' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $sectionId = $this->resolveSectionId($request->input('section_id'));
        if ($request->filled('section_id') && $sectionId === null) {
            return response()->json(['errors' => ['section_id' => ['القسم المحدد غير صالح']]], 422);
        }
        $data['section_id'] = $sectionId;

        // Handle File
        if ($request->hasFile('file_path')) {
            $data['file_path'] = $request->file('file_path')->store('books/files', 'public');
        }

        // Handle Cover
        if ($request->cover_type === 'upload' && $request->hasFile('cover_path')) {
            $data['cover_path'] = $request->file('cover_path')->store('books/covers', 'public');
        } elseif ($request->cover_type === 'auto') {
            // Placeholder logic for now, or generated on frontend?
            // We just store a default path or null
            $data['cover_path'] = 'defaults/book_cover.png';
        }

        // Keywords is array, but casted to array in model.
        // Laravel handles JSON casting automatically if input is array.

        $book = Book::create($data);

        return response()->json(['message' => 'تم إضافة الكتاب بنجاح', 'data' => $book], 201);
    }

    // Admin: Update Book
    public function update(Request $request, $id)
    {
        $book = Book::find($id);
        if (! $book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:1048576',
            'description' => 'string',
            'source_type' => 'in:file,link,embed',
            'file_path' => 'nullable|file|mimes:pdf,doc,docx,epub|max:1048576',
            'source_link' => 'nullable|string',
            'cover_type' => 'in:auto,upload',
            'cover_path' => 'nullable|image|max:1048576',
            'keywords' => 'nullable|array',
            'author_name' => 'string|max:1048576',
            'type' => 'in:single,part',
            'book_series_id' => 'nullable|exists:book_series,id',
            'section_id' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $sectionId = $this->resolveSectionId($request->input('section_id'));
        if ($request->filled('section_id') && $sectionId === null) {
            return response()->json(['errors' => ['section_id' => ['القسم المحدد غير صالح']]], 422);
        }
        if ($request->has('section_id')) {
            $data['section_id'] = $sectionId;
        }

        // Handle File Update
        if ($request->hasFile('file_path')) {
            // Delete old file if exists (optional, good practice)
            // Storage::disk('public')->delete($book->file_path);
            $data['file_path'] = $request->file('file_path')->store('books/files', 'public');
        }

        // Handle Cover Update
        if ($request->hasFile('cover_path')) {
            $data['cover_path'] = $request->file('cover_path')->store('books/covers', 'public');
        }

        $book->update($data);

        return response()->json(['message' => 'تم تحديث بيانات الكتاب بنجاح', 'data' => $book]);
    }

    // Admin: Delete Book
    public function destroy($id)
    {
        $book = Book::find($id);
        if (! $book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $book->delete();

        return response()->json(['message' => 'تم حذف الكتاب بنجاح']);
    }

    // Admin: Get Distinct Authors
    public function getAuthors(Request $request)
    {
        $authors = Book::select('author_name')
            ->distinct()
            ->whereNotNull('author_name')
            ->orderBy('author_name')
            ->pluck('author_name');

        return response()->json($authors);
    }

    protected function resolveSectionId(mixed $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        $section = Section::resolveReference($reference);

        return $section?->id;
    }
}

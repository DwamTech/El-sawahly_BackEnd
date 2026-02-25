<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookController extends Controller
{
    // Public: List Books
    public function index(Request $request)
    {
        $query = Book::with('section');

        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        if ($request->has('series_id')) {
            $query->where('book_series_id', $request->series_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $books = $query->latest()->paginate(20);

        return response()->json($books);
    }

    // Public: Show Book
    public function show($id)
    {
        $book = Book::with(['series', 'section'])->find($id);

        if (! $book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // Increment Views
        $book->increment('views_count');

        $response = ['book' => $book];

        // If part of series, bring siblings
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
            'section_id' => 'nullable|exists:sections,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

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
            'section_id' => 'nullable|exists:sections,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ArticleController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Article::with(['section']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('author')) {
            $query->where('author_name', 'like', '%'.$request->author.'%');
        }

        if ($request->filled('date')) {
            $query->whereDate('gregorian_date', $request->date);
        }

        // Sort by newest
        $query->latest();

        $articles = $query->paginate(15);

        return response()->json($articles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArticleRequest $request, $section_id = null)
    {
        $data = $request->validated();

        // If IDs are passed via route, ensure they are used (though merge in request handles validation)
        if ($section_id) {
            $data['section_id'] = $section_id;
        }

        // issue_id is now optional and not passed via route in this context
        // if ($issue_id) $data['issue_id'] = $issue_id;

        // Assign current user
        $data['user_id'] = $request->user()->id;

        // Handle Image Upload
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $this->imageService->upload(
                $request->file('featured_image'),
                'articles' // Storage path: storage/app/public/articles
            );
        }

        // Check for scheduled publishing
        // Logic:
        // 1. If published_at is SET and FUTURE -> Scheduled (Draft)
        // 2. If published_at is EMPTY, check gregorian_date.
        //    If gregorian_date is FUTURE -> Copy to published_at -> Scheduled (Draft)
        // 3. If publishing immediately (status=published) and no future date -> Ensure published_at = now()

        if (isset($data['published_at'])) {
            if (\Carbon\Carbon::parse($data['published_at'])->isFuture() && $data['status'] === 'published') {
                $data['status'] = 'draft';
            }
        } elseif (isset($data['gregorian_date'])) {
            // Check if gregorian_date is a valid future date
            try {
                $gDate = \Carbon\Carbon::parse($data['gregorian_date']);
                if ($gDate->isFuture() && $data['status'] === 'published') {
                    $data['published_at'] = $gDate;
                    $data['status'] = 'draft';
                }
            } catch (\Exception $e) {
                // Ignore invalid date strings
            }
        }

        // If still published but no published_at, set to now
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $article = Article::create($data);

        return response()->json([
            'message' => 'Article created successfully',
            'article' => $article,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $article = Article::with(['section', 'issue'])->findOrFail($id);

        // Get visited articles from cookie
        $visitedArticles = json_decode(Cookie::get('visited_articles', '[]'), true);

        if (! in_array($article->id, $visitedArticles)) {
            // Increment views
            $article->increment('views_count');

            // Add article ID to visited list
            $visitedArticles[] = $article->id;

            // Update cookie (valid for 30 days)
            Cookie::queue('visited_articles', json_encode($visitedArticles), 60 * 24 * 30);
        }

        return response()->json([
            'article' => $article,
            // 'visited_articles' => $visitedArticles // Removed debug info for cleaner response
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArticleRequest $request, Article $article)
    {
        $data = $request->validated();
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Handle Image Upload
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($article->featured_image) {
                $this->imageService->delete($article->getRawOriginal('featured_image'));
            }

            $data['featured_image'] = $this->imageService->upload(
                $request->file('featured_image'),
                'articles' // Storage path: storage/app/public/articles
            );
        }

        // Check for scheduled publishing
        if (isset($data['published_at'])) {
            if (\Carbon\Carbon::parse($data['published_at'])->isFuture()) {
                if (isset($data['status']) && $data['status'] === 'published') {
                    $data['status'] = 'draft';
                }
            }
        } elseif (isset($data['gregorian_date'])) {
            try {
                $gDate = \Carbon\Carbon::parse($data['gregorian_date']);
                // Only if trying to publish
                if ($gDate->isFuture() && isset($data['status']) && $data['status'] === 'published') {
                    $data['published_at'] = $gDate;
                    $data['status'] = 'draft';
                }
            } catch (\Exception $e) {
            }
        }

        // Maintain published_at consistency
        if (isset($data['status']) && $data['status'] === 'published') {
            if (empty($data['published_at']) && ! $article->published_at) {
                $data['published_at'] = now();
            }
        }

        $article->update($data);

        return response()->json([
            'message' => 'Article updated successfully',
            'article' => $article,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Article $article)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $article->delete(); // Soft delete because of SoftDeletes trait

        return response()->json([
            'message' => 'Article deleted successfully',
        ]);
    }

    /**
     * Toggle the status of the specified article (draft <-> published).
     */
    public function toggleStatus(Request $request, Article $article)
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $newStatus = $article->status === 'published' ? 'draft' : 'published';

        $data = ['status' => $newStatus];

        // If publishing, forcing published_at to now() as requested
        if ($newStatus === 'published') {
            $data['published_at'] = now();
            $data['gregorian_date'] = now()->toDateString(); // Also update the display date
        }

        $article->update($data);

        return response()->json([
            'message' => 'Article status updated successfully',
            'status' => $newStatus,
            'article' => $article,
        ]);
    }

    /**
     * Get a list of distinct internal authors.
     */
    public function getAuthors(Request $request)
    {
        $authors = Article::select('author_name')
            ->distinct()
            ->whereNotNull('author_name')
            ->orderBy('author_name')
            ->pluck('author_name');

        return response()->json($authors);
    }
}

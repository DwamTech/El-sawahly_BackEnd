<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Models\Issue;
use App\Services\FileUploadService;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    protected $imageService;

    protected $fileUploadService;

    public function __construct(ImageUploadService $imageService, FileUploadService $fileUploadService)
    {
        $this->imageService = $imageService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $issues = Issue::orderBy('issue_number', 'desc')->paginate(10);

        return response()->json($issues);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIssueRequest $request)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();

        // Assign current user
        $data['user_id'] = $user->id;
        // $data['issue_number']=

        // Handle Cover Image Upload
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->imageService->upload(
                $request->file('cover_image'),
                'issues' // Storage path: storage/app/public/issues
            );
        }

        // Handle Cover Image Alt Upload (Treating as second image per user request)
        if ($request->hasFile('cover_image_alt')) {
            $data['cover_image_alt'] = $this->imageService->upload(
                $request->file('cover_image_alt'),
                'issues'
            );
        }

        // Handle PDF Upload
        if ($request->hasFile('pdf_file')) {
            $data['pdf_file'] = $this->fileUploadService->uploadPdf(
                $request->file('pdf_file'),
                'issues_pdf' // Storage path: storage/app/public/issues_pdf
            );
        }

        $issue = Issue::create($data);

        return response()->json([
            'message' => 'Issue created successfully',
            'issue' => $issue,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $issue = Issue::findOrFail($id);

        // Increment views
        $issue->increment('views_count');

        return response()->json($issue);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIssueRequest $request, Issue $issue)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();

        // Handle Cover Image Upload
        if ($request->hasFile('cover_image')) {
            // Delete old image
            if ($issue->cover_image) {
                $this->imageService->delete($issue->getRawOriginal('cover_image'));
            }

            $data['cover_image'] = $this->imageService->upload(
                $request->file('cover_image'),
                'issues' // Storage path: storage/app/public/issues
            );
        }

        // Handle Cover Image Alt Upload
        if ($request->hasFile('cover_image_alt')) {
            // Delete old image
            if ($issue->cover_image_alt) {
                $this->imageService->delete($issue->getRawOriginal('cover_image_alt'));
            }

            $data['cover_image_alt'] = $this->imageService->upload(
                $request->file('cover_image_alt'),
                'issues'
            );
        }

        // Handle PDF Upload
        if ($request->hasFile('pdf_file')) {
            // Delete old PDF
            if ($issue->pdf_file) {
                $this->fileUploadService->delete($issue->getRawOriginal('pdf_file'));
            }

            $data['pdf_file'] = $this->fileUploadService->uploadPdf(
                $request->file('pdf_file'),
                'issues_pdf'
            );
        }

        $issue->update($data);

        return response()->json([
            'message' => 'Issue updated successfully',
            'issue' => $issue,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Issue $issue)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete cover image
        if ($issue->cover_image) {
            $this->imageService->delete($issue->getRawOriginal('cover_image'));
        }

        // Delete cover image alt
        if ($issue->cover_image_alt) {
            $this->imageService->delete($issue->getRawOriginal('cover_image_alt'));
        }

        // Delete PDF file
        if ($issue->pdf_file) {
            $this->fileUploadService->delete($issue->getRawOriginal('pdf_file'));
        }

        $issue->delete();

        return response()->json([
            'message' => 'Issue deleted successfully',
        ]);
    }
}

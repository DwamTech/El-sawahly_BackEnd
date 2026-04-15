<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\VisualController;
use App\Http\Middleware\EnsureVisitorCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Auth (Public) ────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/admin', [AuthController::class, 'registerAdmin']);
Route::post('/login', [AuthController::class, 'loginOrRegister']);

// ─── Sections (Public) ────────────────────────────────────────────────────────
Route::get('/sections', [SectionController::class, 'index']);
Route::get('/sections/{id}', [SectionController::class, 'show']);
Route::get('/sections/{id}/articles', [SectionController::class, 'getArticles']);
Route::get('/sections/{id}/books',    [SectionController::class, 'getBooks']);
Route::get('/sections/{id}/videos',   [SectionController::class, 'getVideos']);
Route::get('/sections/{id}/audios',   [SectionController::class, 'getAudios']);
Route::get('/homepage', [SectionController::class, 'homepage']);

// ─── Issues (Public) ──────────────────────────────────────────────────────────
Route::get('/issues', [IssueController::class, 'index']);
Route::get('/issues/{id}', [IssueController::class, 'show']);

// ─── Articles (Public read) ───────────────────────────────────────────────────
Route::middleware('module.status:articles')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show'])
        ->middleware(EnsureVisitorCookie::class);
});

// ─── Visuals (Public read) ────────────────────────────────────────────────────
Route::middleware('module.status:visuals')->group(function () {
    Route::get('/visuals', [VisualController::class, 'index']);
    Route::get('/visuals/{visual}', [VisualController::class, 'show']);
});

// ─── Audios (Public read) ─────────────────────────────────────────────────────
Route::middleware('module.status:audios')->group(function () {
    Route::get('/audios', [AudioController::class, 'index']);
    Route::get('/audios/{audio}', [AudioController::class, 'show']);
});

// ─── Galleries (Public read) ──────────────────────────────────────────────────
Route::get('/galleries', [GalleryController::class, 'index']);
Route::get('/galleries/{gallery}', [GalleryController::class, 'show']);

// ─── Library (Public read) ────────────────────────────────────────────────────
Route::prefix('library')->group(function () {
    Route::get('books', [\App\Http\Controllers\API\BookController::class, 'index']);
    Route::get('books/{id}', [\App\Http\Controllers\API\BookController::class, 'show']);
    Route::post('books/{id}/rate', [\App\Http\Controllers\API\BookController::class, 'rate']);
});

// ─── Documents (Public) ───────────────────────────────────────────────────────
Route::prefix('documents')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\DocumentController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\API\DocumentController::class, 'show']);
    Route::post('/{id}/download', [\App\Http\Controllers\API\DocumentController::class, 'download']);
});

// ─── Support (Public) ─────────────────────────────────────────────────────────
Route::prefix('support/individual')->group(function () {
    Route::post('store', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'store']);
    Route::post('status', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'checkStatus']);
});
Route::prefix('support/institutional')->group(function () {
    Route::post('store', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'store']);
    Route::post('status', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'checkStatus']);
});
Route::post('support/workflow/upload', [\App\Http\Controllers\API\WorkflowController::class, 'uploadDocuments']);
Route::get('support/settings', [\App\Http\Controllers\API\SupportSettingController::class, 'index']);
Route::get('site/status', [\App\Http\Controllers\API\SupportSettingController::class, 'getSiteStatus']);

// ─── System Content (Public) ──────────────────────────────────────────────────
Route::get('system-content/{key}', [\App\Http\Controllers\API\SystemContentController::class, 'show']);

// ─── Site Contact (Public) ────────────────────────────────────────────────────
Route::prefix('site-contact')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\SiteContactController::class, 'index']);
    Route::get('/social', [\App\Http\Controllers\API\SiteContactController::class, 'social']);
    Route::get('/phones', [\App\Http\Controllers\API\SiteContactController::class, 'phones']);
    Route::get('/business', [\App\Http\Controllers\API\SiteContactController::class, 'business']);
});

// ─── Platform Rating & Feedback (Public) ──────────────────────────────────────
Route::get('/platform-rating', [\App\Http\Controllers\API\PlatformRatingController::class, 'index']);
Route::post('/platform-rating', [\App\Http\Controllers\API\PlatformRatingController::class, 'store']);
Route::post('/feedback', [\App\Http\Controllers\API\FeedbackController::class, 'store']);

// ══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATED ROUTES
// ══════════════════════════════════════════════════════════════════════════════
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/validate-token', [AuthController::class, 'validateToken']);
    Route::get('/user', fn(Request $request) => $request->user());

    // Profile
    Route::get('/profile', [\App\Http\Controllers\API\UserManagementController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\API\UserManagementController::class, 'updateProfile']);

    // Issues (auth)
    Route::post('/issues', [IssueController::class, 'store']);
    Route::put('/issues/{issue}', [IssueController::class, 'update']);
    Route::delete('/issues/{issue}', [IssueController::class, 'destroy']);

    // File Management
    Route::prefix('files')->group(function () {
        Route::post('/upload/initiate', [\App\Http\Controllers\API\FileUploadController::class, 'initiateUpload']);
        Route::post('/upload/chunk', [\App\Http\Controllers\API\FileUploadController::class, 'uploadChunk'])->name('files.upload.chunk');
        Route::post('/upload/finalize', [\App\Http\Controllers\API\FileUploadController::class, 'finalizeUpload']);
        Route::delete('/upload/cancel/{fileId}', [\App\Http\Controllers\API\FileUploadController::class, 'cancelUpload']);

        Route::prefix('explorer')->group(function () {
            Route::get('/browse', [\App\Http\Controllers\API\FileExplorerController::class, 'browse']);
            Route::post('/rename', [\App\Http\Controllers\API\FileExplorerController::class, 'rename']);
            Route::delete('/delete', [\App\Http\Controllers\API\FileExplorerController::class, 'delete']);
            Route::post('/create-folder', [\App\Http\Controllers\API\FileExplorerController::class, 'createFolder']);
            Route::get('/download', [\App\Http\Controllers\API\FileExplorerController::class, 'download']);
        });

        Route::prefix('archive')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\FileArchiveController::class, 'index']);
            Route::get('/search', [\App\Http\Controllers\API\FileArchiveController::class, 'search']);
            Route::post('/search', [\App\Http\Controllers\API\FileArchiveController::class, 'search']);
            Route::get('/{id}', [\App\Http\Controllers\API\FileArchiveController::class, 'show']);
        });
    });

    // ── ADMIN ROUTES ──────────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {

        Route::middleware(['content.admin'])->group(function () {
            // Articles (Content management)
            Route::get('/articles', [ArticleController::class, 'adminIndex']);
            Route::get('/articles/{article}', [ArticleController::class, 'adminShow']);
            Route::post('/sections/{section}/articles', [ArticleController::class, 'store']);
            Route::post('/articles', [ArticleController::class, 'store']);
            Route::match(['put', 'post'], '/articles/{article}', [ArticleController::class, 'update']);
            Route::post('/articles/{article}/toggle-status', [ArticleController::class, 'toggleStatus']);
            Route::delete('/articles/{article}', [ArticleController::class, 'destroy']);

            // Visuals (Content management)
            Route::post('/visuals', [VisualController::class, 'store']);
            Route::match(['put', 'post'], '/visuals/{visual}', [VisualController::class, 'update']);
            Route::delete('/visuals/{visual}', [VisualController::class, 'destroy']);

            // Audios (Content management)
            Route::post('/audios', [AudioController::class, 'store']);
            Route::match(['put', 'post'], '/audios/{audio}', [AudioController::class, 'update']);
            Route::delete('/audios/{audio}', [AudioController::class, 'destroy']);

            // Galleries (Content management)
            Route::post('/galleries', [GalleryController::class, 'store']);
            Route::match(['put', 'post'], '/galleries/{gallery}', [GalleryController::class, 'update']);
            Route::delete('/galleries/{gallery}', [GalleryController::class, 'destroy']);

            // Links (Content management)
            Route::post('/links', [LinkController::class, 'store']);
            Route::match(['put', 'post'], '/links/{link}', [LinkController::class, 'update']);
            Route::delete('/links/{link}', [LinkController::class, 'destroy']);

            // Sections (Content management)
            Route::post('/sections', [SectionController::class, 'store']);
            Route::put('/sections/{section}', [SectionController::class, 'update']);
            Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

            // Library (Content management)
            Route::apiResource('library/series', \App\Http\Controllers\API\BookSeriesController::class);
            Route::get('library/books/authors', [\App\Http\Controllers\API\BookController::class, 'getAuthors']);
            Route::get('library/books', [\App\Http\Controllers\API\BookController::class, 'index']);
            Route::post('library/books', [\App\Http\Controllers\API\BookController::class, 'store']);
            Route::get('library/books/{id}', [\App\Http\Controllers\API\BookController::class, 'show']);
            Route::put('library/books/{id}', [\App\Http\Controllers\API\BookController::class, 'update']);
            Route::delete('library/books/{id}', [\App\Http\Controllers\API\BookController::class, 'destroy']);

            // Documents (Content management)
            Route::post('documents', [\App\Http\Controllers\API\DocumentController::class, 'store']);
            Route::put('documents/{id}', [\App\Http\Controllers\API\DocumentController::class, 'update']);
            Route::delete('documents/{id}', [\App\Http\Controllers\API\DocumentController::class, 'destroy']);

            // Authors
            Route::get('/articles/authors', [ArticleController::class, 'getAuthors']);
        });

        Route::middleware(['admin'])->group(function () {
            // Backups
            Route::get('/backups', [BackupController::class, 'index']);
            Route::get('/backups/history', [BackupController::class, 'history']);
            Route::post('/backups/upload', [BackupController::class, 'upload']);
            Route::get('/backups/download', [BackupController::class, 'download'])->name('backup.download');
            Route::post('/backups/create', [BackupController::class, 'create']);
            Route::post('/backups/restore', [BackupController::class, 'restore']);
            Route::delete('/backups', [BackupController::class, 'destroy']);

            // Support Settings
            Route::post('/support/settings/update', [\App\Http\Controllers\API\SupportSettingController::class, 'update']);
            Route::match(['get', 'post'], '/support/settings/update-all', [\App\Http\Controllers\API\SupportSettingController::class, 'updateAll']);
            Route::post('/site/status', [\App\Http\Controllers\API\SupportSettingController::class, 'updateSiteStatus']);

            // Support Requests
            Route::get('/support/individual/requests', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'index']);
            Route::get('/support/individual/requests/{id}', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'show']);
            Route::post('/support/individual/requests/{id}/update', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'update']);
            Route::delete('/support/individual/requests/{id}', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'destroy']);
            Route::get('/support/institutional/requests', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'index']);
            Route::get('/support/institutional/requests/{id}', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'show']);
            Route::post('/support/institutional/requests/{id}/update', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'update']);
            Route::delete('/support/institutional/requests/{id}', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'destroy']);
            Route::get('/support/pending', [\App\Http\Controllers\API\SupportRequestController::class, 'pending']);

            // Feedback
            Route::get('/feedback', [\App\Http\Controllers\API\FeedbackController::class, 'index']);
            Route::delete('/feedback/{id}', [\App\Http\Controllers\API\FeedbackController::class, 'destroy']);

            // Dashboard
            Route::prefix('dashboard')->group(function () {
                Route::get('summary', [\App\Http\Controllers\API\DashboardController::class, 'summary']);
                Route::get('analytics', [\App\Http\Controllers\API\DashboardController::class, 'analytics']);
                Route::get('recent-requests', [\App\Http\Controllers\API\DashboardController::class, 'recentRequests']);
                Route::get('notifications/count', [\App\Http\Controllers\API\DashboardController::class, 'unreadNotificationsCount']);
                Route::get('pending-requests-values', [\App\Http\Controllers\API\DashboardController::class, 'pendingRequestsValues']);
                Route::get('support-stats', [\App\Http\Controllers\API\DashboardController::class, 'supportStats']);
            });

            // Users
            Route::get('/users', [\App\Http\Controllers\API\UserManagementController::class, 'index']);
            Route::get('/users/{id}', [\App\Http\Controllers\API\UserManagementController::class, 'show']);
            Route::post('/users', [\App\Http\Controllers\API\UserManagementController::class, 'store']);
            Route::put('/users/{id}', [\App\Http\Controllers\API\UserManagementController::class, 'update']);
            Route::post('/users/{id}/change-password', [\App\Http\Controllers\API\UserManagementController::class, 'changePassword']);
            Route::delete('/users/{id}', [\App\Http\Controllers\API\UserManagementController::class, 'destroy']);

            // Site Contact
            Route::prefix('site-contact')->group(function () {
                Route::get('/', [\App\Http\Controllers\API\SiteContactController::class, 'show']);
                Route::put('/', [\App\Http\Controllers\API\SiteContactController::class, 'update']);
                Route::put('/social', [\App\Http\Controllers\API\SiteContactController::class, 'updateSocial']);
                Route::put('/phones', [\App\Http\Controllers\API\SiteContactController::class, 'updatePhones']);
                Route::put('/business', [\App\Http\Controllers\API\SiteContactController::class, 'updateBusiness']);
            });

            // System Content
            Route::post('/system-content/{key}', [\App\Http\Controllers\API\SystemContentController::class, 'update']);

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/', [\App\Http\Controllers\API\AdminNotificationController::class, 'index']);
                Route::get('/count', [\App\Http\Controllers\API\AdminNotificationController::class, 'count']);
                Route::get('/latest', [\App\Http\Controllers\API\AdminNotificationController::class, 'latest']);
                Route::get('/meta', [\App\Http\Controllers\API\AdminNotificationController::class, 'meta']);
                Route::get('/{id}', [\App\Http\Controllers\API\AdminNotificationController::class, 'show']);
                Route::post('/{id}/read', [\App\Http\Controllers\API\AdminNotificationController::class, 'markAsRead']);
                Route::post('/{id}/unread', [\App\Http\Controllers\API\AdminNotificationController::class, 'markAsUnread']);
                Route::post('/read-all', [\App\Http\Controllers\API\AdminNotificationController::class, 'markAllAsRead']);
                Route::delete('/{id}', [\App\Http\Controllers\API\AdminNotificationController::class, 'destroy']);
                Route::delete('/clear-read', [\App\Http\Controllers\API\AdminNotificationController::class, 'clearRead']);
                Route::delete('/clear-all', [\App\Http\Controllers\API\AdminNotificationController::class, 'clearAll']);
            });
        });
    });
});

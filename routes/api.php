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

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/admin', [AuthController::class, 'registerAdmin']);
Route::post('/login', [AuthController::class, 'loginOrRegister']);

// Public section routes
Route::get('/sections', [SectionController::class, 'index']);
Route::get('/sections/{id}', [SectionController::class, 'show']);

// Public issue routes
Route::get('/issues', [IssueController::class, 'index']);
Route::get('/issues/{id}', [IssueController::class, 'show']);

// Public article routes
Route::middleware('module.status:articles')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show'])
        ->middleware(EnsureVisitorCookie::class);
});

// Public visuals routes
Route::middleware('module.status:visuals')->group(function () {
    Route::get('/visuals', [VisualController::class, 'index']);
    Route::get('/visuals/{visual}', [VisualController::class, 'show']);
});

// Public audios routes
Route::middleware('module.status:audios')->group(function () {
    Route::get('/audios', [AudioController::class, 'index']);
    Route::get('/audios/{audio}', [AudioController::class, 'show']);
});

// Public galleries routes
Route::get('/galleries', [GalleryController::class, 'index']);
Route::get('/galleries/{gallery}', [GalleryController::class, 'show']);

// Public Individual Support Routes
Route::prefix('support/individual')->group(function () {
    Route::post('store', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'store']);
    Route::post('status', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'checkStatus']);
});

// Public Institutional Support Routes
Route::prefix('support/institutional')->group(function () {
    Route::post('store', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'store']);
    Route::post('status', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'checkStatus']);
});

// Public Workflow (Client Response)
Route::post('support/workflow/upload', [\App\Http\Controllers\API\WorkflowController::class, 'uploadDocuments']);

// Support Settings (Public)
Route::get('support/settings', [\App\Http\Controllers\API\SupportSettingController::class, 'index']);

// Public System Content
Route::get('system-content/{key}', [\App\Http\Controllers\API\SystemContentController::class, 'show']);

// Public Site Contact (Guest endpoints)
Route::prefix('site-contact')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\SiteContactController::class, 'index']);
    Route::get('/social', [\App\Http\Controllers\API\SiteContactController::class, 'social']);
    Route::get('/phones', [\App\Http\Controllers\API\SiteContactController::class, 'phones']);
    Route::get('/business', [\App\Http\Controllers\API\SiteContactController::class, 'business']);
});

Route::group(['middleware' => ['auth:sanctum']], function () {
    // ... existing auth routes
    // System Content Management (Admin)
    Route::post('/admin/system-content/{key}', [\App\Http\Controllers\API\SystemContentController::class, 'update']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/validate-token', [AuthController::class, 'validateToken']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Profile Management (All authenticated users)
    Route::get('/profile', [\App\Http\Controllers\API\UserManagementController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\API\UserManagementController::class, 'updateProfile']);

    // Protected issue routes
    Route::post('/issues', [IssueController::class, 'store']);
    Route::put('/issues/{issue}', [IssueController::class, 'update']);
    Route::delete('/issues/{issue}', [IssueController::class, 'destroy']);

    // Protected article routes (Create, Update, Delete)
    // Create article with section in URL
    Route::post('/sections/{section}/articles', [ArticleController::class, 'store']);
    // Or just generic store route
    Route::post('/articles', [ArticleController::class, 'store']);

    // Legacy store route (optional, can keep or remove based on preference, removing to force new structure)
    // Route::post('/articles', [ArticleController::class, 'store']);

    Route::match(['put', 'post'], '/articles/{article}', [ArticleController::class, 'update']);
    Route::post('/articles/{article}/toggle-status', [ArticleController::class, 'toggleStatus']);
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy']);

    // Visuals Routes
    Route::post('/visuals', [VisualController::class, 'store']);
    Route::match(['put', 'post'], '/visuals/{visual}', [VisualController::class, 'update']);
    Route::delete('/visuals/{visual}', [VisualController::class, 'destroy']);
    // Route::apiResource('visuals', VisualController::class)->except(['show', 'index']);

    // Audios Routes
    Route::post('/audios', [AudioController::class, 'store']);
    Route::match(['put', 'post'], '/audios/{audio}', [AudioController::class, 'update']);
    Route::delete('/audios/{audio}', [AudioController::class, 'destroy']);

    // Galleries Routes
    Route::post('/galleries', [GalleryController::class, 'store']);
    Route::match(['put', 'post'], '/galleries/{gallery}', [GalleryController::class, 'update']);
    Route::delete('/galleries/{gallery}', [GalleryController::class, 'destroy']);

    // Links Routes
    Route::post('/links', [LinkController::class, 'store']);
    Route::match(['put', 'post'], '/links/{link}', [LinkController::class, 'update']);
    Route::delete('/links/{link}', [LinkController::class, 'destroy']);

    // Backup Routes (Admin only)
    Route::middleware(['admin'])->group(function () {
        Route::get('/backups', [BackupController::class, 'index']);
        Route::get('/backups/history', [BackupController::class, 'history']);
        Route::post('/backups/upload', [BackupController::class, 'upload']);
        Route::get('/backups/download', [BackupController::class, 'download'])->name('backup.download');
        Route::post('/backups/create', [BackupController::class, 'create']);
        Route::post('/backups/restore', [BackupController::class, 'restore']);
        Route::delete('/backups', [BackupController::class, 'destroy']);
        // Route::post('/register', [AuthController::class, 'register']);

        // Route::post('/set-role/{user}', [AuthController::class, 'setRole']);

        // Support Settings (Admin Update)
        Route::post('/admin/support/settings/update', [\App\Http\Controllers\API\SupportSettingController::class, 'update']);
        Route::match(['get', 'post'], '/admin/support/settings/update-all', [\App\Http\Controllers\API\SupportSettingController::class, 'updateAll']);

        // Individual Support Admin Requests
        Route::get('/admin/support/individual/requests', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'index']);
        Route::get('/admin/support/individual/requests/{id}', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'show']);
        Route::post('/admin/support/individual/requests/{id}/update', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'update']);
        Route::delete('/admin/support/individual/requests/{id}', [\App\Http\Controllers\API\IndividualSupportRequestController::class, 'destroy']);

        // Institutional Support Admin Requests
        Route::get('/admin/support/institutional/requests', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'index']);
        Route::get('/admin/support/institutional/requests/{id}', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'show']);
        Route::post('/admin/support/institutional/requests/{id}/update', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'update']);
        Route::delete('/admin/support/institutional/requests/{id}', [\App\Http\Controllers\API\InstitutionalSupportRequestController::class, 'destroy']);

        // Unified Pending Requests
        Route::get('/admin/support/pending', [\App\Http\Controllers\API\SupportRequestController::class, 'pending']);

        // Feedback Delete
        Route::delete('admin/feedback/{id}', [\App\Http\Controllers\API\FeedbackController::class, 'destroy']);

        // Library Management (Admin)
        Route::apiResource('admin/library/series', \App\Http\Controllers\API\BookSeriesController::class);
        Route::get('admin/library/books/authors', [\App\Http\Controllers\API\BookController::class, 'getAuthors']);
        Route::apiResource('admin/library/books', \App\Http\Controllers\API\BookController::class);

        // Document Management (Admin)
        Route::apiResource('admin/documents', \App\Http\Controllers\API\DocumentController::class)->except(['index', 'show']);

        // Feedback Management (Admin Index)
        Route::get('admin/feedback', [\App\Http\Controllers\API\FeedbackController::class, 'index']);

        // Dashboard & Analytics
        Route::prefix('admin/dashboard')->group(function () {
            Route::get('summary', [\App\Http\Controllers\API\DashboardController::class, 'summary']);
            Route::get('analytics', [\App\Http\Controllers\API\DashboardController::class, 'analytics']);
            Route::get('recent-requests', [\App\Http\Controllers\API\DashboardController::class, 'recentRequests']);
            Route::get('notifications/count', [\App\Http\Controllers\API\DashboardController::class, 'unreadNotificationsCount']);
            Route::get('pending-requests-values', [\App\Http\Controllers\API\DashboardController::class, 'pendingRequestsValues']);
            Route::get('support-stats', [\App\Http\Controllers\API\DashboardController::class, 'supportStats']);
        });

        // User Management (Admin only)
        Route::get('admin/users', [\App\Http\Controllers\API\UserManagementController::class, 'index']);
        Route::get('admin/users/{id}', [\App\Http\Controllers\API\UserManagementController::class, 'show']);
        Route::post('admin/users', [\App\Http\Controllers\API\UserManagementController::class, 'store']);
        Route::put('admin/users/{id}', [\App\Http\Controllers\API\UserManagementController::class, 'update']);
        Route::post('admin/users/{id}/change-password', [\App\Http\Controllers\API\UserManagementController::class, 'changePassword']);
        Route::delete('admin/users/{id}', [\App\Http\Controllers\API\UserManagementController::class, 'destroy']);

        // Authors List
        Route::get('admin/articles/authors', [ArticleController::class, 'getAuthors']);

        // Admin Section Management
        Route::post('/sections', [SectionController::class, 'store']);
        Route::put('/sections/{section}', [SectionController::class, 'update']);
        Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

        // Site Contact Management (Admin)
        Route::prefix('admin/site-contact')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\SiteContactController::class, 'show']);
            Route::put('/', [\App\Http\Controllers\API\SiteContactController::class, 'update']);
            Route::put('/social', [\App\Http\Controllers\API\SiteContactController::class, 'updateSocial']);
            Route::put('/phones', [\App\Http\Controllers\API\SiteContactController::class, 'updatePhones']);
            Route::put('/business', [\App\Http\Controllers\API\SiteContactController::class, 'updateBusiness']);
        });

        // Admin Notifications
        Route::prefix('admin/notifications')->group(function () {
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

// Admin Feedback (Temporarily Public for Testing)
// Route::get('admin/feedback', [\App\Http\Controllers\API\FeedbackController::class, 'index']); // Removed

// Public Library Routes
Route::prefix('library')->group(function () {
    Route::get('books', [\App\Http\Controllers\API\BookController::class, 'index']);
    Route::get('books/{id}', [\App\Http\Controllers\API\BookController::class, 'show']);
    Route::post('books/{id}/rate', [\App\Http\Controllers\API\BookController::class, 'rate']);
});

// Public Documents Routes
Route::prefix('documents')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\DocumentController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\API\DocumentController::class, 'show']);
    Route::post('/{id}/download', [\App\Http\Controllers\API\DocumentController::class, 'download']);
});

// Platform Satisfaction Rating (Public)
Route::get('/platform-rating', [\App\Http\Controllers\API\PlatformRatingController::class, 'index']);
Route::post('/platform-rating', [\App\Http\Controllers\API\PlatformRatingController::class, 'store']);

// Feedback (Public)
Route::post('/feedback', [\App\Http\Controllers\API\FeedbackController::class, 'store']);

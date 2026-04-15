<?php

namespace App\Http\Controllers;

use App\Exceptions\BackupOperationException;
use App\Services\Backup\BackupManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupManagerService $backupManager,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->backupManager->listBackups(),
            'meta' => $this->backupManager->runtimeMeta(),
        ]);
    }

    public function history(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->backupManager->history(),
        ]);
    }

    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            $file = $this->backupManager->downloadPath((string) $request->query('file_name', ''));

            return response()->download($file['full_path'], $file['file_name']);
        } catch (BackupOperationException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->status());
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $result = $this->backupManager->createBackup($request->user());

            return response()->json($result);
        } catch (BackupOperationException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->status());
        } catch (Throwable $exception) {
            return $this->errorResponse('Failed to create backup: '.$exception->getMessage(), 500);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:zip',
        ]);

        try {
            $result = $this->backupManager->uploadBackup($request->file('file'), $request->user());

            return response()->json($result);
        } catch (BackupOperationException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->status());
        } catch (Throwable $exception) {
            return $this->errorResponse('Failed to upload backup: '.$exception->getMessage(), 500);
        }
    }

    public function restore(Request $request): JsonResponse
    {
        try {
            $result = $this->backupManager->restoreBackup((string) $request->input('file_name', ''), $request->user());

            return response()->json($result);
        } catch (BackupOperationException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->status());
        } catch (Throwable $exception) {
            return $this->errorResponse('Failed to restore backup: '.$exception->getMessage(), 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            $result = $this->backupManager->deleteBackup((string) $request->query('file_name', ''), $request->user());

            return response()->json($result);
        } catch (BackupOperationException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->status());
        } catch (Throwable $exception) {
            return $this->errorResponse('Failed to delete backup: '.$exception->getMessage(), 500);
        }
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}

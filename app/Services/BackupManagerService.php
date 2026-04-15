<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use App\Models\BackupHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupManagerService
{
    private const MANIFEST_FILE = 'backup-manifest.json';

    public function listBackups(): array
    {
        $disk = $this->backupDisk();
        $directory = $this->backupDirectory();

        if (! $disk->exists($directory)) {
            return [];
        }

        return collect($disk->files($directory))
            ->filter(fn (string $path) => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip')
            ->map(function (string $path) use ($disk) {
                $lastModified = $disk->lastModified($path);

                return [
                    'file_name' => basename($path),
                    'file_size' => $this->humanFileSize($disk->size($path)),
                    'created_at' => date('Y-m-d H:i:s', $lastModified),
                    'last_modified' => date('Y-m-d H:i:s', $lastModified),
                    'file_path' => $path,
                    'download_link' => route('backup.download', ['file_name' => basename($path)]),
                ];
            })
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    public function createBackup(?int $userId = null, bool $preRestore = false): array
    {
        $history = BackupHistory::create([
            'type' => 'create',
            'status' => 'started',
            'file_name' => null,
            'message' => $preRestore ? 'Pre-restore backup started.' : 'Backup creation started.',
            'user_id' => $userId,
        ]);

        $tempDirectory = $this->makeTempDirectory('backup-create-');

        try {
            $sqlDirectory = $tempDirectory.DIRECTORY_SEPARATOR.'db-dumps';
            $publicSnapshotDirectory = $tempDirectory.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';
            File::ensureDirectoryExists($sqlDirectory);
            File::ensureDirectoryExists($publicSnapshotDirectory);

            $databaseName = (string) config('database.connections.mysql.database', 'database');
            $sqlPath = $sqlDirectory.DIRECTORY_SEPARATOR.$databaseName.'.sql';
            $this->dumpDatabase($sqlPath);

            $sourcePublicDirectory = storage_path('app/public');
            if (is_dir($sourcePublicDirectory)) {
                File::copyDirectory($sourcePublicDirectory, $publicSnapshotDirectory);
            }

            file_put_contents(
                $tempDirectory.DIRECTORY_SEPARATOR.self::MANIFEST_FILE,
                json_encode([
                    'version' => 1,
                    'created_at' => now()->toIso8601String(),
                    'app_name' => config('app.name'),
                    'backup_directory' => $this->backupDirectory(),
                    'database' => $databaseName,
                    'os' => $this->backupOs(),
                    'includes' => [
                        'database_dump' => 'db-dumps/'.basename($sqlPath),
                        'storage_public' => 'storage/app/public',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $fileName = $this->generateBackupFileName($preRestore);
            $relativePath = $this->backupDirectory().'/'.$fileName;

            $this->ensureBackupDirectoryExists();
            $absolutePath = $this->backupDisk()->path($relativePath);
            $this->createZipArchive($tempDirectory, $absolutePath);

            $fileSize = filesize($absolutePath) ?: null;

            $history->update([
                'status' => 'success',
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'message' => $preRestore ? 'Pre-restore backup created successfully.' : 'Backup created successfully.',
            ]);

            return [
                'success' => true,
                'message' => $preRestore ? 'تم إنشاء نسخة وقائية قبل الاستعادة بنجاح.' : 'تم إنشاء النسخة الاحتياطية بنجاح.',
                'file_name' => $fileName,
                'file_path' => $relativePath,
                'file_size' => $this->humanFileSize($fileSize ?: 0),
            ];
        } catch (\Throwable $exception) {
            $history->update([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ]);

            Log::error('Backup creation failed: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            throw new BackupOperationException('فشل إنشاء النسخة الاحتياطية: '.$exception->getMessage(), 500, $exception);
        } finally {
            $this->deleteDirectory($tempDirectory);
        }
    }

    public function downloadBackup(string $fileName): array
    {
        $relativePath = $this->resolveBackupRelativePath($fileName);

        return [
            'file_name' => basename($relativePath),
            'absolute_path' => $this->backupDisk()->path($relativePath),
        ];
    }

    public function uploadBackup(UploadedFile $file, ?int $userId = null): array
    {
        $originalName = (string) $file->getClientOriginalName();

        if (! Str::endsWith(strtolower($originalName), '.zip')) {
            throw new BackupOperationException('ملف النسخة الاحتياطية يجب أن يكون بصيغة zip.', 422);
        }

        $this->assertValidBackupFileName($originalName);
        $this->assertArchiveStructure($file->getRealPath() ?: '');

        $disk = $this->backupDisk();
        $relativePath = $this->backupDirectory().'/'.$originalName;

        if ($disk->exists($relativePath)) {
            throw new BackupOperationException('يوجد ملف نسخة احتياطية بنفس الاسم بالفعل.', 409);
        }

        $this->ensureBackupDirectoryExists();
        $disk->putFileAs($this->backupDirectory(), $file, $originalName);

        BackupHistory::create([
            'type' => 'upload',
            'status' => 'success',
            'file_name' => $originalName,
            'file_size' => $file->getSize(),
            'message' => 'External backup uploaded successfully.',
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'message' => 'تم رفع النسخة الاحتياطية بنجاح.',
            'path' => $relativePath,
        ];
    }

    public function deleteBackup(string $fileName, ?int $userId = null): array
    {
        $relativePath = $this->resolveBackupRelativePath($fileName);
        $disk = $this->backupDisk();
        $fileSize = $disk->size($relativePath);

        $disk->delete($relativePath);

        BackupHistory::create([
            'type' => 'delete',
            'status' => 'success',
            'file_name' => basename($relativePath),
            'file_size' => $fileSize,
            'message' => 'Backup file deleted successfully.',
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'message' => 'تم حذف النسخة الاحتياطية بنجاح.',
        ];
    }

    public function restoreBackup(string $fileName, ?int $userId = null): array
    {
        $relativePath = $this->resolveBackupRelativePath($fileName);
        $history = BackupHistory::create([
            'type' => 'restore',
            'status' => 'started',
            'file_name' => basename($relativePath),
            'message' => 'Restore process started.',
            'user_id' => $userId,
        ]);

        $tempDirectory = $this->makeTempDirectory('backup-restore-');
        $archiveExtractDirectory = $tempDirectory.DIRECTORY_SEPARATOR.'archive';
        File::ensureDirectoryExists($archiveExtractDirectory);

        $enteredMaintenance = false;
        $rollbackPublicDirectory = null;
        $swappedPublicDirectory = false;

        try {
            $this->assertArchiveStructure($this->backupDisk()->path($relativePath));
            $this->extractArchive($this->backupDisk()->path($relativePath), $archiveExtractDirectory);

            $sqlPath = $this->findSqlDump($archiveExtractDirectory);
            if (! $sqlPath) {
                throw new BackupOperationException('ملف SQL غير موجود داخل النسخة الاحتياطية.', 422);
            }

            $restoredPublicDirectory = $archiveExtractDirectory.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';
            if (! is_dir($restoredPublicDirectory)) {
                throw new BackupOperationException('مجلد storage/app/public غير موجود داخل النسخة الاحتياطية.', 422);
            }

            $this->createBackup($userId, true);

            if (! app()->isDownForMaintenance()) {
                Artisan::call('down');
                $enteredMaintenance = true;
            }

            $livePublicDirectory = storage_path('app/public');
            $rollbackPublicDirectory = $tempDirectory.DIRECTORY_SEPARATOR.'current-public';

            if (is_dir($livePublicDirectory) && ! @rename($livePublicDirectory, $rollbackPublicDirectory)) {
                throw new BackupOperationException('تعذر نقل ملفات storage الحالية مؤقتًا.', 500);
            }

            File::ensureDirectoryExists(dirname($livePublicDirectory));
            if (! @rename($restoredPublicDirectory, $livePublicDirectory)) {
                if (is_dir($rollbackPublicDirectory)) {
                    @rename($rollbackPublicDirectory, $livePublicDirectory);
                }

                throw new BackupOperationException('تعذر استعادة ملفات storage من النسخة الاحتياطية.', 500);
            }

            $swappedPublicDirectory = true;
            $this->importDatabase($sqlPath);
            Artisan::call('optimize:clear');

            if ($rollbackPublicDirectory && is_dir($rollbackPublicDirectory)) {
                $this->deleteDirectory($rollbackPublicDirectory);
            }

            if ($enteredMaintenance) {
                Artisan::call('up');
                $enteredMaintenance = false;
            }

            $history->update([
                'status' => 'success',
                'message' => 'Backup restored successfully.',
            ]);

            return [
                'success' => true,
                'message' => 'تمت استعادة النسخة الاحتياطية بنجاح.',
            ];
        } catch (\Throwable $exception) {
            if ($swappedPublicDirectory && $rollbackPublicDirectory && is_dir($rollbackPublicDirectory)) {
                $livePublicDirectory = storage_path('app/public');
                if (is_dir($livePublicDirectory)) {
                    $this->deleteDirectory($livePublicDirectory);
                }
                @rename($rollbackPublicDirectory, $livePublicDirectory);
            }

            if ($enteredMaintenance) {
                Artisan::call('up');
            }

            $history->update([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ]);

            Log::error('Backup restore failed: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception instanceof BackupOperationException
                ? $exception
                : new BackupOperationException('فشلت استعادة النسخة الاحتياطية: '.$exception->getMessage(), 500, $exception);
        } finally {
            $this->deleteDirectory($tempDirectory);
        }
    }

    public function history()
    {
        return BackupHistory::with('user:id,name,email')->latest()->take(50)->get();
    }

    private function backupDisk()
    {
        return Storage::disk('local');
    }

    private function backupDirectory(): string
    {
        return (string) config('backup.smart.directory', config('backup.backup.name', 'laravel-backup'));
    }

    private function backupOs(): string
    {
        $configured = strtolower(trim((string) config('backup.smart.os', env('OS', ''))));

        if (str_contains($configured, 'win')) {
            return 'win';
        }

        if (str_contains($configured, 'linux')) {
            return 'linux';
        }

        return PHP_OS_FAMILY === 'Windows' ? 'win' : 'linux';
    }

    private function generateBackupFileName(bool $preRestore = false): string
    {
        $prefix = Str::slug((string) config('app.name', 'backup'));
        $suffix = $preRestore ? 'pre-restore' : 'full';

        return sprintf('%s-%s-%s.zip', $prefix, $suffix, now()->format('Y-m-d_H-i-s'));
    }

    private function ensureBackupDirectoryExists(): void
    {
        $this->backupDisk()->makeDirectory($this->backupDirectory());
    }

    private function assertValidBackupFileName(string $fileName): void
    {
        if ($fileName === '' || $fileName !== basename($fileName) || str_contains($fileName, '..') || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            throw new BackupOperationException('اسم ملف النسخة الاحتياطية غير صالح.', 422);
        }
    }

    private function resolveBackupRelativePath(string $fileName): string
    {
        $this->assertValidBackupFileName($fileName);

        $disk = $this->backupDisk();
        $relativePath = $this->backupDirectory().'/'.$fileName;

        if ($disk->exists($relativePath)) {
            return $relativePath;
        }

        if ($disk->exists($fileName)) {
            return $fileName;
        }

        throw new BackupOperationException('ملف النسخة الاحتياطية غير موجود.', 404);
    }

    private function makeTempDirectory(string $prefix): string
    {
        $directory = storage_path('app/backup-temp/'.$prefix.Str::uuid());
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function dumpDatabase(string $sqlPath): void
    {
        $databaseConfig = (array) config('database.connections.mysql', []);
        $database = (string) ($databaseConfig['database'] ?? '');

        if ($database === '') {
            throw new BackupOperationException('بيانات الاتصال بقاعدة البيانات غير مكتملة.', 500);
        }

        $binary = $this->resolveBinary('mysqldump');
        $defaultsFile = $this->writeMysqlDefaultsFile($databaseConfig);

        try {
            $command = sprintf(
                '%s --defaults-extra-file=%s --single-transaction --skip-lock-tables --routines --triggers --events %s',
                escapeshellarg($binary),
                escapeshellarg($defaultsFile),
                escapeshellarg($database)
            );

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', $sqlPath, 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes);
            if (! is_resource($process)) {
                throw new BackupOperationException('تعذر تشغيل mysqldump.', 500);
            }

            fclose($pipes[0]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new BackupOperationException('فشل تصدير قاعدة البيانات: '.trim($stderr), 500);
            }
        } finally {
            @unlink($defaultsFile);
        }
    }

    private function importDatabase(string $sqlPath): void
    {
        $databaseConfig = (array) config('database.connections.mysql', []);
        $database = (string) ($databaseConfig['database'] ?? '');

        if ($database === '') {
            throw new BackupOperationException('بيانات الاتصال بقاعدة البيانات غير مكتملة.', 500);
        }

        $binary = $this->resolveBinary('mysql');
        $defaultsFile = $this->writeMysqlDefaultsFile($databaseConfig);

        try {
            $command = sprintf(
                '%s --defaults-extra-file=%s --binary-mode=1 %s',
                escapeshellarg($binary),
                escapeshellarg($defaultsFile),
                escapeshellarg($database)
            );

            $descriptors = [
                0 => ['file', $sqlPath, 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes);
            if (! is_resource($process)) {
                throw new BackupOperationException('تعذر تشغيل mysql للاستعادة.', 500);
            }

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new BackupOperationException('فشل استيراد قاعدة البيانات: '.trim($stderr ?: $stdout), 500);
            }
        } finally {
            @unlink($defaultsFile);
        }
    }

    private function writeMysqlDefaultsFile(array $databaseConfig): string
    {
        $path = storage_path('app/backup-temp/mysql-'.Str::uuid().'.cnf');
        File::ensureDirectoryExists(dirname($path));

        $contents = implode(PHP_EOL, [
            '[client]',
            'user='.(string) ($databaseConfig['username'] ?? ''),
            'password='.(string) ($databaseConfig['password'] ?? ''),
            'host='.(string) ($databaseConfig['host'] ?? '127.0.0.1'),
            'port='.(string) ($databaseConfig['port'] ?? '3306'),
        ]).PHP_EOL;

        file_put_contents($path, $contents);
        @chmod($path, 0600);

        return $path;
    }

    private function resolveBinary(string $binary): string
    {
        $isWindows = $this->backupOs() === 'win';
        $binaryName = $isWindows ? $binary.'.exe' : $binary;
        $dbDumpConfig = (array) config('database.connections.mysql.dump', []);
        $configuredPath = (string) ($dbDumpConfig['dump_binary_path'] ?? '');

        $candidates = [];
        if ($configuredPath !== '') {
            $configuredPath = rtrim($configuredPath, '\\/');
            $candidates[] = $configuredPath.DIRECTORY_SEPARATOR.$binaryName;
        }

        $candidates[] = $binaryName;

        foreach ($this->commonBinaryPatterns($binaryName) as $pattern) {
            $matches = glob($pattern) ?: [];
            foreach ($matches as $match) {
                $candidates[] = $match;
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($this->binaryIsAvailable($candidate)) {
                return $candidate;
            }
        }

        throw new BackupOperationException(
            sprintf('تعذر العثور على %s. تأكد من ضبط OS=win|linux وأن أداة MySQL متاحة على الجهاز.', $binaryName),
            500
        );
    }

    private function commonBinaryPatterns(string $binaryName): array
    {
        if ($this->backupOs() === 'win') {
            return [
                'C:\\laragon\\bin\\mysql\\*\\bin\\'.$binaryName,
                'C:\\xampp\\mysql\\bin\\'.$binaryName,
                'C:\\Program Files\\MySQL\\*\\bin\\'.$binaryName,
                'C:\\Program Files (x86)\\MySQL\\*\\bin\\'.$binaryName,
            ];
        }

        return [
            '/usr/bin/'.$binaryName,
            '/usr/local/bin/'.$binaryName,
            '/bin/'.$binaryName,
        ];
    }

    private function binaryIsAvailable(string $candidate): bool
    {
        if (str_contains($candidate, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $candidate) === 1) {
            return is_file($candidate);
        }

        $lookupCommand = $this->backupOs() === 'win'
            ? 'where '.escapeshellarg($candidate)
            : 'which '.escapeshellarg($candidate);

        $output = [];
        $exitCode = 1;
        @exec($lookupCommand, $output, $exitCode);

        return $exitCode === 0;
    }

    private function createZipArchive(string $sourceDirectory, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupOperationException('تعذر إنشاء ملف النسخة الاحتياطية المضغوط.', 500);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $absolutePath = $item->getPathname();
            $relativePath = str_replace('\\', '/', ltrim(Str::after($absolutePath, $sourceDirectory), '\\/'));

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
                continue;
            }

            $zip->addFile($absolutePath, $relativePath);
        }

        $zip->close();
    }

    private function extractArchive(string $archivePath, string $destination): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new BackupOperationException('تعذر فتح ملف النسخة الاحتياطية.', 422);
        }

        $zip->extractTo($destination);
        $zip->close();
    }

    private function assertArchiveStructure(string $archivePath): void
    {
        if (! is_file($archivePath)) {
            throw new BackupOperationException('ملف النسخة الاحتياطية غير موجود.', 404);
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new BackupOperationException('ملف النسخة الاحتياطية غير صالح أو تالف.', 422);
        }

        $hasSql = false;
        $hasStorage = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (Str::endsWith(strtolower($name), '.sql')) {
                $hasSql = true;
            }
            if (str_starts_with($name, 'storage/app/public')) {
                $hasStorage = true;
            }
        }

        $zip->close();

        if (! $hasSql || ! $hasStorage) {
            throw new BackupOperationException('ملف النسخة الاحتياطية يجب أن يحتوي على SQL dump ومجلد storage/app/public.', 422);
        }
    }

    private function findSqlDump(string $directory): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === 'sql') {
                return $item->getPathname();
            }
        }

        return null;
    }

    private function humanFileSize(int $bytes, int $decimals = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return sprintf("%.{$decimals}f %s", $bytes / (1024 ** $factor), $units[$factor]);
    }

    private function deleteDirectory(?string $directory): void
    {
        if (! $directory || ! file_exists($directory)) {
            return;
        }

        File::deleteDirectory($directory);
    }
}

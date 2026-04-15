<?php

namespace App\Services\Backup;

use App\Exceptions\BackupOperationException;
use App\Models\BackupHistory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupManagerService
{
    public function __construct(
        private readonly FilesystemManager $storage,
        private readonly Filesystem $files,
    ) {}

    public function listBackups(): array
    {
        $disk = $this->backupDisk();
        $this->ensureBackupDirectory();

        $backups = collect($disk->files($this->backupDirectory()))
            ->filter(fn (string $path) => Str::endsWith(strtolower($path), '.zip'))
            ->map(function (string $path) use ($disk): array {
                return [
                    'file_name' => basename($path),
                    'file_path' => $path,
                    'file_size' => $this->formatBytes((int) $disk->size($path)),
                    'last_modified' => date('Y-m-d H:i:s', $disk->lastModified($path)),
                ];
            })
            ->sortByDesc(fn (array $item) => $item['last_modified'])
            ->values()
            ->all();

        return $backups;
    }

    public function history(int $limit = 50): Collection
    {
        return BackupHistory::query()
            ->with('user:id,name,email,email_verified_at,created_at,updated_at')
            ->latest()
            ->take($limit)
            ->get();
    }

    public function runtimeMeta(): array
    {
        return [
            'environment' => app()->environment(),
            'os' => $this->resolveConfiguredOs(),
            'backup_directory' => $this->backupDirectory(),
            'storage_source' => storage_path('app/public'),
        ];
    }

    public function createBackup(?Authenticatable $user = null, string $reason = 'manual'): array
    {
        $history = $this->writeHistory([
            'type' => 'create',
            'status' => 'started',
            'message' => $this->buildCreateStartedMessage($reason),
            'user_id' => $user?->getAuthIdentifier(),
        ]);

        $workingDirectory = $this->makeTempDirectory('backup-build-');

        try {
            $this->ensureZipArchiveIsAvailable();
            $this->ensureBackupDirectory();

            $sqlDumpPath = $workingDirectory.DIRECTORY_SEPARATOR.'db-dumps'.DIRECTORY_SEPARATOR.$this->databaseName().'.sql';
            $storageTarget = $workingDirectory.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

            $this->files->ensureDirectoryExists(dirname($sqlDumpPath), 0700, true);
            $this->dumpDatabase($sqlDumpPath);
            $this->mirrorStorage($storageTarget);
            $this->writeManifest($workingDirectory, $reason);

            $fileName = $this->generateBackupFileName($reason);
            $relativePath = $this->backupDirectory().'/'.$fileName;
            $absolutePath = $this->backupDisk()->path($relativePath);

            $this->files->ensureDirectoryExists(dirname($absolutePath), 0700, true);
            $this->zipDirectory($workingDirectory, $absolutePath);

            $this->markHistory($history, [
                'status' => 'success',
                'file_name' => $fileName,
                'file_size' => filesize($absolutePath) ?: null,
                'message' => 'Backup created successfully.',
            ]);

            return [
                'success' => true,
                'message' => 'Backup created successfully.',
                'file_name' => $fileName,
            ];
        } catch (Throwable $exception) {
            $this->markHistory($history, [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ]);

            throw $exception instanceof BackupOperationException
                ? $exception
                : new BackupOperationException('Failed to create backup: '.$exception->getMessage(), 500, $exception);
        } finally {
            $this->deleteDirectory($workingDirectory);
        }
    }

    public function uploadBackup(UploadedFile $file, ?Authenticatable $user = null): array
    {
        $originalName = trim((string) $file->getClientOriginalName());

        if ($originalName === '' || ! Str::endsWith(strtolower($originalName), '.zip')) {
            throw new BackupOperationException('File must be a zip archive.', 422);
        }

        $this->ensureZipArchiveIsAvailable();
        $this->ensureBackupDirectory();

        $relativePath = $this->backupDirectory().'/'.$originalName;

        if ($this->backupDisk()->exists($relativePath)) {
            throw new BackupOperationException('A backup file with this name already exists.', 409);
        }

        $this->assertArchiveStructure($file->getRealPath());

        try {
            $this->backupDisk()->putFileAs($this->backupDirectory(), $file, $originalName);

            $this->writeHistory([
                'type' => 'upload',
                'status' => 'success',
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'message' => 'External backup uploaded successfully.',
                'user_id' => $user?->getAuthIdentifier(),
            ]);

            return [
                'success' => true,
                'message' => 'Backup uploaded successfully.',
                'path' => $relativePath,
            ];
        } catch (Throwable $exception) {
            throw new BackupOperationException('Failed to upload backup: '.$exception->getMessage(), 500, $exception);
        }
    }

    public function deleteBackup(string $fileName, ?Authenticatable $user = null): array
    {
        $relativePath = $this->resolveBackupRelativePath($fileName);
        $disk = $this->backupDisk();

        try {
            $fileSize = (int) $disk->size($relativePath);
            $disk->delete($relativePath);

            $this->writeHistory([
                'type' => 'delete',
                'status' => 'success',
                'file_name' => basename($relativePath),
                'file_size' => $fileSize,
                'message' => 'Backup file deleted successfully.',
                'user_id' => $user?->getAuthIdentifier(),
            ]);

            return [
                'success' => true,
                'message' => 'Backup file deleted successfully.',
            ];
        } catch (Throwable $exception) {
            $this->writeHistory([
                'type' => 'delete',
                'status' => 'failed',
                'file_name' => basename($relativePath),
                'message' => $exception->getMessage(),
                'user_id' => $user?->getAuthIdentifier(),
            ]);

            throw new BackupOperationException('Failed to delete backup: '.$exception->getMessage(), 500, $exception);
        }
    }

    public function restoreBackup(string $fileName, ?Authenticatable $user = null): array
    {
        $history = $this->writeHistory([
            'type' => 'restore',
            'status' => 'started',
            'file_name' => $fileName,
            'message' => 'Restore process started.',
            'user_id' => $user?->getAuthIdentifier(),
        ]);

        $relativePath = $this->resolveBackupRelativePath($fileName);
        $archivePath = $this->backupDisk()->path($relativePath);
        $restoreDirectory = $this->makeTempDirectory('backup-restore-');
        $rollbackStoragePath = $this->temporaryDirectory().DIRECTORY_SEPARATOR.'public-rollback-'.Str::uuid();

        $enteredMaintenance = false;
        $filesSwapped = false;

        try {
            $this->ensureZipArchiveIsAvailable();
            $preRestoreBackup = $this->createBackup($user, 'pre-restore');

            if (! app()->isDownForMaintenance()) {
                Artisan::call('down');
                $enteredMaintenance = true;
            }

            $this->extractArchive($archivePath, $restoreDirectory);

            $sqlFile = $this->findSqlDump($restoreDirectory);
            $restoredStoragePath = $restoreDirectory.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

            if (! $sqlFile) {
                throw new BackupOperationException('SQL dump file not found in the backup archive.', 422);
            }

            if (! $this->files->isDirectory($restoredStoragePath)) {
                throw new BackupOperationException('storage/app/public not found in the backup archive.', 422);
            }

            $liveStoragePath = storage_path('app/public');
            if ($this->files->isDirectory($liveStoragePath)) {
                $this->files->ensureDirectoryExists(dirname($rollbackStoragePath), 0700, true);
                if (! @rename($liveStoragePath, $rollbackStoragePath)) {
                    throw new BackupOperationException('Failed to backup current storage/app/public.', 500);
                }
            }

            if (! @rename($restoredStoragePath, $liveStoragePath)) {
                if ($this->files->isDirectory($rollbackStoragePath)) {
                    @rename($rollbackStoragePath, $liveStoragePath);
                }

                throw new BackupOperationException('Failed to restore storage/app/public.', 500);
            }

            $filesSwapped = true;
            $this->importDatabase($sqlFile);
            Artisan::call('optimize:clear');

            $this->deleteDirectory($rollbackStoragePath);
            $this->markHistory($history, [
                'status' => 'success',
                'file_name' => basename($relativePath),
                'message' => 'Backup restored successfully. Pre-restore backup: '.$preRestoreBackup['file_name'],
            ]);

            return [
                'success' => true,
                'message' => 'Backup restored successfully.',
            ];
        } catch (Throwable $exception) {
            if ($filesSwapped && $this->files->isDirectory($rollbackStoragePath)) {
                $liveStoragePath = storage_path('app/public');
                $this->deleteDirectory($liveStoragePath);
                @rename($rollbackStoragePath, $liveStoragePath);
            }

            $this->markHistory($history, [
                'status' => 'failed',
                'file_name' => basename($relativePath),
                'message' => $exception->getMessage(),
            ]);

            throw $exception instanceof BackupOperationException
                ? $exception
                : new BackupOperationException('Failed to restore backup: '.$exception->getMessage(), 500, $exception);
        } finally {
            if ($enteredMaintenance) {
                Artisan::call('up');
            }

            $this->deleteDirectory($restoreDirectory);
            $this->deleteDirectory($rollbackStoragePath);
        }
    }

    public function downloadPath(string $fileName): array
    {
        $relativePath = $this->resolveBackupRelativePath($fileName);

        return [
            'file_name' => basename($relativePath),
            'full_path' => $this->backupDisk()->path($relativePath),
        ];
    }

    private function backupDisk(): FilesystemAdapter
    {
        return $this->storage->disk(config('backup.smart.disk', 'local'));
    }

    private function backupDirectory(): string
    {
        return trim((string) config('backup.smart.directory', config('backup.backup.name', 'laravel-backup')), '/');
    }

    private function temporaryDirectory(): string
    {
        return (string) config('backup.smart.temporary_directory', storage_path('app/backup-temp'));
    }

    private function ensureBackupDirectory(): void
    {
        $this->backupDisk()->makeDirectory($this->backupDirectory());
        $this->files->ensureDirectoryExists($this->temporaryDirectory(), 0700, true);
    }

    private function resolveBackupRelativePath(string $fileName): string
    {
        $cleanFileName = trim($fileName);

        if ($cleanFileName === '') {
            throw new BackupOperationException('File name is required', 400);
        }

        if ($cleanFileName !== basename($cleanFileName) || str_contains($cleanFileName, '..') || str_contains($cleanFileName, '/') || str_contains($cleanFileName, '\\')) {
            throw new BackupOperationException('Invalid file name', 422);
        }

        $relativePath = $this->backupDirectory().'/'.$cleanFileName;

        if (! $this->backupDisk()->exists($relativePath)) {
            throw new BackupOperationException('Backup file not found', 404);
        }

        return $relativePath;
    }

    private function ensureZipArchiveIsAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new BackupOperationException('ZipArchive extension is missing', 500);
        }
    }

    private function generateBackupFileName(string $reason): string
    {
        $prefix = trim((string) config('backup.backup.destination.filename_prefix', ''));
        $slugReason = Str::slug($reason ?: 'manual');

        return trim($prefix.$slugReason, '-_')
            .($prefix !== '' || $slugReason !== '' ? '-' : '')
            .now()->format('Y-m-d-H-i-s')
            .'-'
            .Str::lower(Str::random(8))
            .'.zip';
    }

    private function buildCreateStartedMessage(string $reason): string
    {
        return $reason === 'pre-restore'
            ? 'Pre-restore backup started.'
            : 'Backup creation started.';
    }

    private function makeTempDirectory(string $prefix): string
    {
        $this->files->ensureDirectoryExists($this->temporaryDirectory(), 0700, true);

        $path = $this->temporaryDirectory().DIRECTORY_SEPARATOR.$prefix.Str::uuid();
        $this->files->ensureDirectoryExists($path, 0700, true);

        return $path;
    }

    private function writeManifest(string $workingDirectory, string $reason): void
    {
        $manifest = [
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'os' => $this->resolveConfiguredOs(),
            'database' => [
                'connection' => $this->databaseConnectionName(),
                'name' => $this->databaseName(),
            ],
            'storage_source' => storage_path('app/public'),
            'created_at' => now()->toIso8601String(),
            'reason' => $reason,
        ];

        $this->files->put(
            $workingDirectory.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function mirrorStorage(string $targetPath): void
    {
        $source = storage_path('app/public');

        $this->files->ensureDirectoryExists($targetPath, 0700, true);

        if (! $this->files->isDirectory($source)) {
            return;
        }

        $this->files->copyDirectory($source, $targetPath);
    }

    private function zipDirectory(string $sourceDirectory, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupOperationException('Failed to create backup archive.', 500);
        }

        $sourceDirectory = rtrim($sourceDirectory, DIRECTORY_SEPARATOR);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $absolutePath = $item->getPathname();
            $relativePath = ltrim(str_replace($sourceDirectory, '', $absolutePath), DIRECTORY_SEPARATOR);

            if ($relativePath === '') {
                continue;
            }

            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);

                continue;
            }

            $zip->addFile($absolutePath, $relativePath);
        }

        $zip->close();
    }

    private function extractArchive(string $archivePath, string $targetDirectory): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new BackupOperationException('Failed to open backup archive.', 500);
        }

        if (! $zip->extractTo($targetDirectory)) {
            $zip->close();
            throw new BackupOperationException('Failed to extract backup archive.', 500);
        }

        $zip->close();
    }

    private function assertArchiveStructure(string $archivePath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new BackupOperationException('Failed to open backup archive.', 422);
        }

        $hasSqlDump = false;
        $hasStorage = false;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = (string) $zip->getNameIndex($index);
            $normalized = trim(str_replace('\\', '/', $entryName), '/');

            if ($normalized === '') {
                continue;
            }

            if (Str::startsWith($normalized, 'db-dumps/') && Str::endsWith(strtolower($normalized), '.sql')) {
                $hasSqlDump = true;
            }

            if ($normalized === 'storage/app/public' || Str::startsWith($normalized, 'storage/app/public/')) {
                $hasStorage = true;
            }
        }

        $zip->close();

        if (! $hasSqlDump || ! $hasStorage) {
            throw new BackupOperationException('Backup archive must contain db-dumps/*.sql and storage/app/public.', 422);
        }
    }

    private function findSqlDump(string $directory): ?string
    {
        $preferredDirectory = $directory.DIRECTORY_SEPARATOR.'db-dumps';

        if ($this->files->isDirectory($preferredDirectory)) {
            $files = collect($this->files->files($preferredDirectory))
                ->filter(fn (string $path) => Str::endsWith(strtolower($path), '.sql'))
                ->values();

            if ($files->isNotEmpty()) {
                return $files->first();
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === 'sql') {
                return $item->getPathname();
            }
        }

        return null;
    }

    private function dumpDatabase(string $targetPath): void
    {
        $command = array_merge(
            [
                $this->resolveDatabaseBinary('mysqldump'),
                '--defaults-extra-file='.$this->writeMysqlDefaultsFile(),
                '--single-transaction',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                '--result-file='.$targetPath,
                $this->databaseName(),
            ],
            $this->parseExtraMysqlOptions()
        );

        $this->runProcess($command, null);
    }

    private function importDatabase(string $sqlPath): void
    {
        $command = [
            $this->resolveDatabaseBinary('mysql'),
            '--defaults-extra-file='.$this->writeMysqlDefaultsFile(),
            '--binary-mode=1',
            $this->databaseName(),
        ];

        $this->runProcess($command, $sqlPath);
    }

    private function parseExtraMysqlOptions(): array
    {
        $option = (string) data_get(config('database.connections.'.$this->databaseConnectionName()), 'dump.add_extra_option', '');

        if ($option === '') {
            return [];
        }

        return preg_split('/\s+/', trim($option)) ?: [];
    }

    private function writeMysqlDefaultsFile(): string
    {
        $config = $this->databaseConnectionConfig();
        $path = $this->temporaryDirectory().DIRECTORY_SEPARATOR.'mysql-'.Str::uuid().'.cnf';

        $contents = [
            '[client]',
            'user='.$config['username'],
            'password='.$config['password'],
            'host='.$config['host'],
            'port='.$config['port'],
        ];

        if (! empty($config['unix_socket'])) {
            $contents[] = 'socket='.$config['unix_socket'];
        }

        $this->files->ensureDirectoryExists(dirname($path), 0700, true);
        $this->files->put($path, implode(PHP_EOL, $contents).PHP_EOL);
        @chmod($path, 0600);

        return $path;
    }

    private function runProcess(array $command, ?string $stdinPath): void
    {
        $defaultsFile = collect($command)
            ->first(fn (string $segment) => Str::startsWith($segment, '--defaults-extra-file='));

        $descriptorSpec = [
            0 => $stdinPath ? ['file', $stdinPath, 'r'] : ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $commandString = implode(' ', array_map('escapeshellarg', $command));
        $process = proc_open($commandString, $descriptorSpec, $pipes);

        if (! is_resource($process)) {
            if (is_string($defaultsFile)) {
                @unlink(Str::after($defaultsFile, '--defaults-extra-file='));
            }

            throw new BackupOperationException('Failed to start backup process.', 500);
        }

        if (! $stdinPath && isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }

        $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }

        $exitCode = proc_close($process);

        if (is_string($defaultsFile)) {
            @unlink(Str::after($defaultsFile, '--defaults-extra-file='));
        }

        if ($exitCode !== 0) {
            $message = trim($stderr ?: $stdout);
            throw new BackupOperationException(
                $message !== '' ? $message : 'Backup process exited with a non-zero status.',
                500
            );
        }
    }

    private function resolveDatabaseBinary(string $binary): string
    {
        $executableName = $this->resolveConfiguredOs() === 'win' ? $binary.'.exe' : $binary;
        $configuredPath = trim((string) data_get($this->databaseConnectionConfig(), 'dump.dump_binary_path', ''));

        if ($configuredPath !== '') {
            $candidate = rtrim($configuredPath, '\\/').DIRECTORY_SEPARATOR.$executableName;
            if ($this->files->exists($candidate)) {
                return $candidate;
            }
        }

        if ($pathBinary = $this->findBinaryOnPath($executableName)) {
            return $pathBinary;
        }

        $fallbacks = $this->resolveConfiguredOs() === 'win'
            ? $this->windowsBinaryCandidates($executableName)
            : $this->linuxBinaryCandidates($executableName);

        foreach ($fallbacks as $candidate) {
            if ($this->files->exists($candidate)) {
                return $candidate;
            }
        }

        throw new BackupOperationException(
            sprintf(
                'Unable to locate %s for OS "%s". Set MYSQL_DUMP_PATH or ensure the binary is available in PATH.',
                $binary,
                $this->resolveConfiguredOs()
            ),
            500
        );
    }

    private function findBinaryOnPath(string $binary): ?string
    {
        $command = $this->resolveConfiguredOs() === 'win'
            ? sprintf('where %s', escapeshellarg($binary))
            : sprintf('command -v %s', escapeshellarg($binary));

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        return trim((string) $output[0]);
    }

    private function windowsBinaryCandidates(string $binary): array
    {
        $patterns = [
            'C:\\laragon\\bin\\mysql\\*\\bin\\'.$binary,
            'C:\\xampp\\mysql\\bin\\'.$binary,
            'C:\\Program Files\\MySQL\\*\\bin\\'.$binary,
            'C:\\Program Files (x86)\\MySQL\\*\\bin\\'.$binary,
            'C:\\wamp64\\bin\\mysql\\*\\bin\\'.$binary,
        ];

        $matches = [];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                $matches[] = $path;
            }
        }

        rsort($matches);

        return array_values(array_unique($matches));
    }

    private function linuxBinaryCandidates(string $binary): array
    {
        return [
            '/usr/bin/'.$binary,
            '/usr/local/bin/'.$binary,
            '/opt/homebrew/bin/'.$binary,
        ];
    }

    private function resolveConfiguredOs(): string
    {
        $value = strtolower(trim((string) config('backup.smart.os', 'linux')));

        if (! in_array($value, ['win', 'linux'], true)) {
            throw new BackupOperationException('Invalid backup OS configuration. Allowed values are: win, linux.', 500);
        }

        return $value;
    }

    private function databaseConnectionName(): string
    {
        return (string) config('database.default', 'mysql');
    }

    private function databaseConnectionConfig(): array
    {
        $connection = (array) config('database.connections.'.$this->databaseConnectionName(), []);
        $driver = (string) ($connection['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new BackupOperationException('Smart backup currently supports mysql and mariadb only.', 500);
        }

        return [
            'host' => (string) ($connection['host'] ?? '127.0.0.1'),
            'port' => (string) ($connection['port'] ?? '3306'),
            'database' => (string) ($connection['database'] ?? ''),
            'username' => (string) ($connection['username'] ?? ''),
            'password' => (string) ($connection['password'] ?? ''),
            'unix_socket' => (string) ($connection['unix_socket'] ?? ''),
            'dump' => is_array($connection['dump'] ?? null) ? $connection['dump'] : [],
        ];
    }

    private function databaseName(): string
    {
        $database = $this->databaseConnectionConfig()['database'];

        if ($database === '') {
            throw new BackupOperationException('Database name is missing from configuration.', 500);
        }

        return $database;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, 2).' '.$units[$unitIndex];
    }

    private function writeHistory(array $attributes): BackupHistory
    {
        return BackupHistory::query()->create($attributes);
    }

    private function markHistory(BackupHistory $history, array $attributes): void
    {
        $history->forceFill($attributes)->save();
    }

    private function deleteDirectory(?string $path): void
    {
        if (! is_string($path) || trim($path) === '' || ! $this->files->exists($path)) {
            return;
        }

        if ($this->files->isDirectory($path)) {
            $this->files->deleteDirectory($path);

            return;
        }

        $this->files->delete($path);
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManagerService;
use Illuminate\Console\Command;
use Throwable;

class RunSmartBackupCommand extends Command
{
    protected $signature = 'backup:smart-run {--reason=scheduled : Reason label stored in the backup manifest}';

    protected $description = 'Create a full smart backup that includes SQL dump and storage files.';

    public function handle(BackupManagerService $backupManager): int
    {
        try {
            $result = $backupManager->createBackup(null, (string) $this->option('reason'));

            $this->info($result['message']);
            if (! empty($result['file_name'])) {
                $this->line('Backup file: '.$result['file_name']);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

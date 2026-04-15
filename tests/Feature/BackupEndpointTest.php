<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureVisitorCookie;
use App\Http\Middleware\TrackVisits;
use App\Models\BackupHistory;
use App\Models\User;
use App\Services\Backup\BackupManagerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\RefreshDatabaseWithForce;
use Tests\TestCase;
use ZipArchive;

class BackupEndpointTest extends TestCase
{
    use RefreshDatabaseWithForce;

    protected User $adminUser;

    protected string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            TrackVisits::class,
            EnsureVisitorCookie::class,
        ]);

        Storage::fake('local');

        config()->set('backup.smart.disk', 'local');
        config()->set('backup.smart.directory', 'test-backups');
        config()->set('backup.smart.os', 'linux');
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.driver', 'mysql');
        config()->set('database.connections.mysql.database', 'ashia_test');
        config()->set('database.connections.mysql.username', 'root');
        config()->set('database.connections.mysql.password', 'secret');
        config()->set('database.connections.mysql.host', '127.0.0.1');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.dump.dump_binary_path', '');

        $this->adminUser = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->backupDirectory = config('backup.smart.directory');
        Storage::disk('local')->makeDirectory($this->backupDirectory);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function authenticateAsAdmin(): void
    {
        Sanctum::actingAs($this->adminUser);
    }

    private function authenticateAs(User $user): void
    {
        Sanctum::actingAs($user);
    }

    private function createStoredBackup(string $fileName, bool $withValidStructure = true): string
    {
        $temporaryPath = storage_path('app/'.$fileName);
        $zip = new ZipArchive;
        $zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($withValidStructure) {
            $zip->addFromString('db-dumps/ashia_test.sql', '-- fake dump');
            $zip->addEmptyDir('storage/app/public');
            $zip->addFromString('storage/app/public/example.txt', 'content');
            $zip->addFromString('manifest.json', json_encode(['ok' => true]));
        } else {
            $zip->addFromString('notes.txt', 'invalid backup');
        }

        $zip->close();

        $relativePath = $this->backupDirectory.'/'.$fileName;
        Storage::disk('local')->put($relativePath, file_get_contents($temporaryPath));
        @unlink($temporaryPath);

        return $relativePath;
    }

    private function makeUploadedBackup(string $fileName, bool $withValidStructure = true): UploadedFile
    {
        $temporaryPath = storage_path('app/upload-'.$fileName);
        $zip = new ZipArchive;
        $zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($withValidStructure) {
            $zip->addFromString('db-dumps/ashia_test.sql', '-- fake dump');
            $zip->addEmptyDir('storage/app/public');
            $zip->addFromString('storage/app/public/example.txt', 'content');
        } else {
            $zip->addFromString('db-dumps/readme.txt', 'missing sql and storage');
        }

        $zip->close();

        return new UploadedFile($temporaryPath, $fileName, 'application/zip', null, true);
    }

    private function mockBackupManager(callable $callback): void
    {
        $mock = Mockery::mock(BackupManagerService::class);
        $callback($mock);
        $this->app->instance(BackupManagerService::class, $mock);
    }

    #[Test]
    public function unauthenticated_users_cannot_access_backup_endpoints(): void
    {
        $this->getJson('/api/admin/backups')->assertUnauthorized();
        $this->getJson('/api/admin/backups/history')->assertUnauthorized();
        $this->postJson('/api/admin/backups/create')->assertUnauthorized();
        $this->getJson('/api/admin/backups/download?file_name=test.zip')->assertUnauthorized();
        $this->postJson('/api/admin/backups/upload')->assertUnauthorized();
        $this->postJson('/api/admin/backups/restore')->assertUnauthorized();
        $this->deleteJson('/api/admin/backups?file_name=test.zip')->assertUnauthorized();
    }

    #[Test]
    public function non_admin_users_cannot_access_backup_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
        ]);

        $this->authenticateAs($user);

        $this->getJson('/api/admin/backups')->assertForbidden();
        $this->postJson('/api/admin/backups/create')->assertForbidden();
        $this->deleteJson('/api/admin/backups?file_name=test.zip')->assertForbidden();
    }

    #[Test]
    public function list_backups_returns_runtime_meta_and_items(): void
    {
        $this->authenticateAsAdmin();
        $fileName = 'backup-a.zip';
        $this->createStoredBackup($fileName);

        $response = $this->getJson('/api/admin/backups');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.os', 'linux')
            ->assertJsonPath('meta.backup_directory', $this->backupDirectory)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['file_name', 'file_path', 'file_size', 'last_modified'],
                ],
                'meta' => ['environment', 'os', 'backup_directory', 'storage_source'],
            ]);

        $this->assertSame($fileName, $response->json('data.0.file_name'));
    }

    #[Test]
    public function download_backup_requires_existing_file_name(): void
    {
        $this->authenticateAsAdmin();

        $this->getJson('/api/admin/backups/download')
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'File name is required',
            ]);

        $this->getJson('/api/admin/backups/download?file_name=missing.zip')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Backup file not found',
            ]);
    }

    #[Test]
    public function download_backup_returns_binary_file_for_existing_archive(): void
    {
        $this->authenticateAsAdmin();
        $fileName = 'download-me.zip';
        $this->createStoredBackup($fileName);

        $response = $this->get('/api/admin/backups/download?file_name='.$fileName);

        $response->assertOk();
        $this->assertTrue($response->headers->has('content-disposition'));
        $this->assertStringContainsString($fileName, (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function upload_backup_requires_zip_file_and_valid_backup_structure(): void
    {
        $this->authenticateAsAdmin();

        $this->postJson('/api/admin/backups/upload', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $invalidFile = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');
        $this->postJson('/api/admin/backups/upload', ['file' => $invalidFile])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $invalidBackup = $this->makeUploadedBackup('invalid-structure.zip', false);
        $this->post('/api/admin/backups/upload', ['file' => $invalidBackup])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Backup archive must contain db-dumps/*.sql and storage/app/public.',
            ]);
    }

    #[Test]
    public function upload_backup_stores_file_and_writes_history(): void
    {
        $this->authenticateAsAdmin();
        $upload = $this->makeUploadedBackup('uploaded-backup.zip');

        $response = $this->post('/api/admin/backups/upload', ['file' => $upload]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Backup uploaded successfully.',
                'path' => $this->backupDirectory.'/uploaded-backup.zip',
            ]);

        Storage::disk('local')->assertExists($this->backupDirectory.'/uploaded-backup.zip');
        $this->assertDatabaseHas('backup_histories', [
            'type' => 'upload',
            'status' => 'success',
            'file_name' => 'uploaded-backup.zip',
            'user_id' => $this->adminUser->id,
        ]);
    }

    #[Test]
    public function upload_backup_rejects_duplicate_names(): void
    {
        $this->authenticateAsAdmin();
        $this->createStoredBackup('duplicate.zip');

        $upload = $this->makeUploadedBackup('duplicate.zip');

        $this->post('/api/admin/backups/upload', ['file' => $upload])
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'A backup file with this name already exists.',
            ]);
    }

    #[Test]
    public function delete_backup_validates_and_removes_archive(): void
    {
        $this->authenticateAsAdmin();

        $this->deleteJson('/api/admin/backups')
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'File name is required',
            ]);

        $this->deleteJson('/api/admin/backups?file_name=../../secret.zip')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid file name',
            ]);

        $fileName = 'delete-me.zip';
        $this->createStoredBackup($fileName);

        $this->deleteJson('/api/admin/backups?file_name='.$fileName)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Backup file deleted successfully.',
            ]);

        Storage::disk('local')->assertMissing($this->backupDirectory.'/'.$fileName);
        $this->assertDatabaseHas('backup_histories', [
            'type' => 'delete',
            'status' => 'success',
            'file_name' => $fileName,
        ]);
    }

    #[Test]
    public function history_endpoint_returns_latest_records_with_user_relation(): void
    {
        $this->authenticateAsAdmin();

        BackupHistory::query()->create([
            'type' => 'upload',
            'status' => 'success',
            'file_name' => 'one.zip',
            'message' => 'Uploaded successfully.',
            'user_id' => $this->adminUser->id,
        ]);

        BackupHistory::query()->create([
            'type' => 'delete',
            'status' => 'failed',
            'file_name' => 'two.zip',
            'message' => 'Delete failed.',
            'user_id' => $this->adminUser->id,
        ]);

        $response = $this->getJson('/api/admin/backups/history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'status',
                        'file_name',
                        'message',
                        'user' => ['id', 'name', 'email'],
                    ],
                ],
            ]);
    }

    #[Test]
    public function create_backup_endpoint_uses_service_and_returns_success_payload(): void
    {
        $this->authenticateAsAdmin();

        $this->mockBackupManager(function ($mock): void {
            $mock->shouldReceive('createBackup')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'Backup created successfully.',
                    'file_name' => 'manual-backup.zip',
                ]);
        });

        $this->postJson('/api/admin/backups/create')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Backup created successfully.',
                'file_name' => 'manual-backup.zip',
            ]);
    }

    #[Test]
    public function restore_backup_endpoint_uses_service_and_returns_success_payload(): void
    {
        $this->authenticateAsAdmin();

        $this->mockBackupManager(function ($mock): void {
            $mock->shouldReceive('restoreBackup')
                ->once()
                ->with('restore-me.zip', Mockery::type(User::class))
                ->andReturn([
                    'success' => true,
                    'message' => 'Backup restored successfully.',
                ]);
        });

        $this->postJson('/api/admin/backups/restore', [
            'file_name' => 'restore-me.zip',
        ])->assertOk()->assertJson([
            'success' => true,
            'message' => 'Backup restored successfully.',
        ]);
    }

    #[Test]
    public function restore_backup_validates_file_name_when_service_runs(): void
    {
        $this->authenticateAsAdmin();

        $this->postJson('/api/admin/backups/restore', [])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'File name is required',
            ]);

        $this->postJson('/api/admin/backups/restore', [
            'file_name' => '../unsafe.zip',
        ])->assertStatus(422)->assertJson([
            'success' => false,
            'message' => 'Invalid file name',
        ]);
    }
}

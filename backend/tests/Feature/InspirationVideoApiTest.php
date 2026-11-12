<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InspirationVideoApiTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/admin/inspiration-video';

    private const VIDEO = 'homepage/story/Inspiration.mp4';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function adminUser(
        array $permissions = ['cms.manage', 'homepage.manage']
    ): User {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );

        $ids = [];

        foreach ($permissions as $slug) {
            $ids[] = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug]
            )->id;
        }

        $role->permissions()->sync($ids);

        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function videoUpload(): UploadedFile
    {
        // Minimal MP4 container signature for MIME validation.
        $content = pack('N', 24)
            . 'ftypisom'
            . pack('N', 0)
            . 'isomiso2';

        return UploadedFile::fake()->createWithContent(
            'replacement.mp4',
            $content
        );
    }

    public function test_guest_cannot_manage_video(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized();

        $this->postJson(self::ENDPOINT)
            ->assertUnauthorized();
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user)
            ->getJson(self::ENDPOINT)
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(self::ENDPOINT)
            ->assertForbidden();
    }

    public function test_admin_can_read_current_video(): void
    {
        Storage::disk('public')->put(
            self::VIDEO,
            'original-video'
        );

        $this->actingAs($this->adminUser())
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath(
                'data.path',
                '/storage/' . self::VIDEO
            )
            ->assertJsonPath(
                'data.size',
                strlen('original-video')
            );
    }

    public function test_missing_video_returns_not_found(): void
    {
        $this->actingAs($this->adminUser())
            ->getJson(self::ENDPOINT)
            ->assertNotFound();
    }

    public function test_valid_upload_replaces_video_and_creates_backup(): void
    {
        $disk = Storage::disk('public');

        $disk->put(self::VIDEO, 'original-video');

        $original = $disk->get(self::VIDEO);

        $upload = $this->videoUpload();

        $this->actingAs($this->adminUser())
            ->post(self::ENDPOINT, [
                'video' => $upload,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.path',
                '/storage/' . self::VIDEO
            );

        $this->assertNotSame(
            $original,
            $disk->get(self::VIDEO)
        );

        $backups = $disk->files(
            'homepage/story/backups'
        );

        $this->assertCount(1, $backups);

        $this->assertSame(
            $original,
            $disk->get($backups[0])
        );
    }

    public function test_invalid_file_does_not_change_video(): void
    {
        $disk = Storage::disk('public');

        $disk->put(self::VIDEO, 'original-video');

        $this->actingAs($this->adminUser())
            ->post(self::ENDPOINT, [
                'video' => UploadedFile::fake()
                    ->createWithContent(
                        'invalid.txt',
                        'not a video'
                    ),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $this->assertSame(
            'original-video',
            $disk->get(self::VIDEO)
        );

        $this->assertCount(
            0,
            $disk->files('homepage/story/backups')
        );
    }

    public function test_oversized_file_does_not_change_video(): void
    {
        $disk = Storage::disk('public');

        $disk->put(self::VIDEO, 'original-video');

        $this->actingAs($this->adminUser())
            ->post(self::ENDPOINT, [
                'video' => UploadedFile::fake()
                    ->create('large.mp4', 51201, 'video/mp4'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $this->assertSame(
            'original-video',
            $disk->get(self::VIDEO)
        );
    }
}
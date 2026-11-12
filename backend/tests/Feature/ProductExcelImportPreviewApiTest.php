<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductExcelImportPreviewApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/products/excel/preview';

    private function staff(
        string $roleSlug,
        array $permissions = []
    ): User {
        $role = Role::create([
            'name' => Str::headline($roleSlug),
            'slug' => $roleSlug,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline(
                        str_replace('.', ' ', $slug)
                    ),
                ]
            );

            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    private function permissions(): array
    {
        return ['products.update', 'inventory.manage'];
    }

    private function validFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tkb-excel-');

        if ($path === false) {
            throw new \RuntimeException('Temporary file creation failed.');
        }

        try {
            app(ProductExcelExportService::class)->export($path);

            return new UploadedFile(
                $path,
                'TKB-Products.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->postJson(self::URL)->assertUnauthorized();
    }

    public function test_accountant_is_forbidden(): void
    {
        $user = $this->staff('accountant', $this->permissions());

        $this->actingAs($user, 'sanctum')
            ->postJson(self::URL)
            ->assertForbidden();
    }

    public function test_admin_without_products_update_is_forbidden(): void
    {
        $user = $this->staff('admin', ['inventory.manage']);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::URL)
            ->assertForbidden();
    }

    public function test_admin_without_inventory_manage_is_forbidden(): void
    {
        $user = $this->staff('admin', ['products.update']);

        $this->actingAs($user, 'sanctum')
            ->postJson(self::URL)
            ->assertForbidden();
    }

    public function test_missing_file_is_rejected(): void
    {
        $user = $this->staff('admin', $this->permissions());

        $this->actingAs($user, 'sanctum')
            ->postJson(self::URL)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_invalid_file_is_rejected(): void
    {
        $user = $this->staff('admin', $this->permissions());

        $file = UploadedFile::fake()->createWithContent(
            'invalid.xlsx',
            'not a real xlsx file'
        );

        $this->actingAs($user, 'sanctum')
            ->post(self::URL, ['file' => $file], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable();
    }

    public function test_valid_excel_returns_preview_without_database_changes(): void
    {
        $user = $this->staff('admin', $this->permissions());
        $file = $this->validFile();
        $path = $file->getPathname();

        $before = [
            'products' => Product::count(),
            'variants' => ProductVariant::count(),
            'inventories' => Inventory::count(),
            'movements' => InventoryMovement::count(),
        ];

        try {
            $response = $this->actingAs($user, 'sanctum')
                ->post(self::URL, ['file' => $file], [
                    'Accept' => 'application/json',
                ]);

            $response->assertOk()
                ->assertJsonPath('valid', true)
                ->assertJsonPath('can_apply', false)
                ->assertJsonStructure([
                    'summary',
                    'changes',
                    'errors',
                ]);

            $after = [
                'products' => Product::count(),
                'variants' => ProductVariant::count(),
                'inventories' => Inventory::count(),
                'movements' => InventoryMovement::count(),
            ];

            $this->assertSame($before, $after);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductSearchReindexCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reindex_command_rebuilds_existing_product_search_indexes(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Stand Mixer',
                'fa' => 'همزن برقی',
            ],
            'brand' => 'Kitchen Brand',
            'slug' => 'stand-mixer',
        ]);

        DB::table('products')
            ->where(
                'id',
                $product->id
            )
            ->update([
                'search_text' => null,
                'search_compact' => null,
            ]);

        $exitCode =
            Artisan::call(
                'products:reindex-search'
            );

        $this->assertSame(
            0,
            $exitCode
        );

        $row =
            DB::table('products')
                ->where(
                    'id',
                    $product->id
                )
                ->first();

        $this->assertNotNull(
            $row->search_text
        );

        $this->assertNotNull(
            $row->search_compact
        );

        $this->assertStringContainsString(
            'stand mixer',
            $row->search_text
        );

        $this->assertStringContainsString(
            'همزن برقی',
            $row->search_text
        );

        $this->assertStringContainsString(
            'همزنبرقی',
            $row->search_compact
        );
    }
}
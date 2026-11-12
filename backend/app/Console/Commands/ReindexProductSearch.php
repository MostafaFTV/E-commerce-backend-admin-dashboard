<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ProductSearchIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReindexProductSearch extends Command
{
    protected $signature =
        'products:reindex-search';

    protected $description =
        'Rebuild normalized search indexes for all products';

    public function handle(): int
    {
        $processed = 0;

        Product::withTrashed()
            ->select([
                'id',
                'name',
                'brand',
                'slug',
            ])
            ->orderBy('id')
            ->chunkById(
                200,
                function ($products) use (&$processed): void {
                    foreach ($products as $product) {
                        $index =
                            ProductSearchIndexer::build(
                                $product
                            );

                        DB::table('products')
                            ->where(
                                'id',
                                $product->id
                            )
                            ->update([
                                'search_text' =>
                                    $index['search_text'],

                                'search_compact' =>
                                    $index['search_compact'],
                            ]);

                        $processed++;
                    }
                }
            );

        $this->info(
            "Reindexed {$processed} products."
        );

        return self::SUCCESS;
    }
}
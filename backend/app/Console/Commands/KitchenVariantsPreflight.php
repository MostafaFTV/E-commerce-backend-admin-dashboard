<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class KitchenVariantsPreflight extends Command
{
    protected $signature = 'kitchen:variants-preflight';

    protected $description =
        'Dry-run validation for Kitchen product variant image mappings';

    private string $sourceRoot;

    /**
     * @var array<string, string>
     */
    private array $directoryMap = [];

    public function handle(): int
    {
        $this->sourceRoot = env(
            'KITCHEN_PRODUCTS_SOURCE',
            'C:\Users\Diorse\Desktop\Kitchen products'
        );

        if (!File::isDirectory($this->sourceRoot)) {
            throw new RuntimeException(
                "Kitchen products source directory not found: {$this->sourceRoot}"
            );
        }

        $this->directoryMap = $this->buildImageDirectoryMap();

        $products = $this->definitions();

        $totalVariants = 0;
        $totalMatchedImages = 0;

        foreach ($products as $productData) {
            $sourceDirectory = $this->resolveSourceDirectory(
                $productData['source_directory']
            );

            $files = collect(
                File::files($sourceDirectory)
            )
                ->filter(
                    fn ($file) =>
                        $this->isSupportedImage(
                            $file->getExtension()
                        )
                );

            if ($files->isEmpty()) {
                throw new RuntimeException(
                    "No images found in: {$sourceDirectory}"
                );
            }

            foreach ($productData['variants'] as $variantData) {
                $matches = $files
                    ->filter(
                        fn ($file) =>
                            $this->filenameMatchesTokens(
                                $file->getFilename(),
                                $variantData['tokens']
                            )
                    )
                    ->values();

                if ($matches->isEmpty()) {
                    $this->error(
                        'FAILED: '
                        . $productData['slug']
                        . ' / '
                        . $variantData['color']
                        . ' / '
                        . $variantData['sku']
                        . ' => 0 images'
                    );

                    return self::FAILURE;
                }

                $totalVariants++;
                $totalMatchedImages += $matches->count();

                $this->line(
                    'PASS: '
                    . $productData['slug']
                    . ' / '
                    . $variantData['color']
                    . ' / '
                    . $variantData['sku']
                    . ' => '
                    . $matches->count()
                    . ' image(s)'
                );
            }
        }

        $this->newLine();

        $this->info(
            "Preflight passed: {$totalVariants} variants, "
            . "{$totalMatchedImages} matched source images."
        );

        return self::SUCCESS;
    }

    private function definitions(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | SMEG
            |--------------------------------------------------------------------------
            */

            [
                'slug' => 'smeg-bowl-stand-mixer',
                'source_directory' =>
                    'SMEG/Smeg bowlstand mixer',

                'variants' => [
                    [
                        'color' => 'Cream',
                        'sku' => 'SMF05CREU',
                        'tokens' => [
                            'SMF05CREU',
                        ],
                    ],
                    [
                        'color' => 'Pastel Blue',
                        'sku' => 'SMF05PBEU',
                        'tokens' => [
                            'SMF05PBEU',
                        ],
                    ],
                    [
                        'color' => 'White',
                        'sku' => 'SMF05WHEU',
                        'tokens' => [
                            'SMF05WHEU',
                        ],
                    ],
                ],
            ],

            [
                'slug' => 'smeg-espresso-machine',
                'source_directory' =>
                    'SMEG/Smeg espresso machine',

                'variants' => [
                    [
                        'color' => 'Cream',
                        'sku' => 'ECF02CREU',
                        'tokens' => [
                            'ECF02CREU',
                        ],
                    ],
                    [
                        'color' => 'Pastel Blue',
                        'sku' => 'ECF02PBEU',
                        'tokens' => [
                            'ECF02PBEU',
                        ],
                    ],
                    [
                        'color' => 'Pastel Green',
                        'sku' => 'ECF02PGEU',
                        'tokens' => [
                            'ECF02PGEU',
                        ],
                    ],
                ],
            ],

            [
                'slug' => 'smeg-kettle',
                'source_directory' =>
                    'SMEG/Smeg kettle',

                'variants' => [
                    [
                        'color' => 'Black Matte',
                        'sku' => 'KLF03BLMEU',
                        'tokens' => [
                            'KLF03BLMEU',
                        ],
                    ],
                    [
                        'color' => 'Navy Blue',
                        'sku' => 'KLF03NBEU',
                        'tokens' => [
                            'KLF03NBEU',
                        ],
                    ],
                    [
                        'color' => 'Pastel Blue',
                        'sku' => 'KLF03PBEU',
                        'tokens' => [
                            'KLF03PBEU',
                        ],
                    ],
                    [
                        'color' => 'Steel',
                        'sku' => 'KLF03SSEU',
                        'tokens' => [
                            'KLF03SSEU',
                        ],
                    ],
                ],
            ],

            [
                'slug' => 'smeg-soda-maker',
                'source_directory' =>
                    'SMEG/Smeg soda maker',

                'variants' => [
                    [
                        'color' => 'Black Matte',
                        'sku' => 'SKC01BLM',
                        'tokens' => [
                            'SKC01BLM',
                        ],
                    ],
                    [
                        'color' => 'Emerald Green Matte',
                        'sku' => 'SKC01EGM',
                        'tokens' => [
                            'SKC01EGM',
                        ],
                    ],
                    [
                        'color' => 'White Matte',
                        'sku' => 'SKC01WHM',
                        'tokens' => [
                            'SKC01WHM',
                        ],
                    ],
                ],
            ],

            [
                'slug' => 'smeg-toaster',
                'source_directory' =>
                    'SMEG/Smeg toaster',

                'variants' => [
                    [
                        'color' => 'Black',
                        'sku' => 'TSF01BLUS',
                        'tokens' => [
                            'TSF01BLUS',
                        ],
                    ],
                    [
                        'color' => 'Pastel Blue',
                        'sku' => 'TSF01PBUS',
                        'tokens' => [
                            'TSF01PBUS',
                        ],
                    ],
                    [
                        'color' => 'Steel',
                        'sku' => 'TSF01SSUS',
                        'tokens' => [
                            'TSF01SSUS',
                        ],
                    ],
                    [
                        'color' => 'White Matte',
                        'sku' => 'TSF01WHMUS',
                        'tokens' => [
                            'TSF01WHMUS',
                        ],
                    ],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | KitchenAid
            |--------------------------------------------------------------------------
            */

            [
                'slug' => 'kitchenaid-artisan-kettle',
                'source_directory' =>
                    'Kitchenaid/Kitchenaid artisan kettle',

                'variants' => [
                    [
                        'color' => 'Pebbled Palm',
                        'sku' => '5KEK1522EPP',
                        'tokens' => [
                            '5KEK1522EPP',
                            '5KEK1522BPP',
                            'Pebbled_palm',
                            'pebble_palm',
                        ],
                    ],
                    [
                        'color' => 'Candy Apple',
                        'sku' => '5KEK1522ECA',
                        'tokens' => [
                            '5KEK1522ECA',
                            '5KEK1522BCA',
                            'candy_apple',
                            '_CA.',
                            '_CA_',
                        ],
                    ],
                    [
                        'color' => 'Medallion Silver',
                        'sku' => '5KEK1522EMS',
                        'tokens' => [
                            '5KEK1522EMS',
                            '5KEK1522BMS',
                            'medallion_silver',
                            '_MS.',
                            '_MS_',
                            '-MS.',
                            '-MS_',
                        ],
                    ],
                ],
            ],

            [
                'slug' => 'kitchenaid-artisan-toaster',
                'source_directory' =>
                    'Kitchenaid/Kitchenaid artisan toaster',

                'variants' => [
                    [
                        'color' => 'Ink Blue',
                        'sku' => '5KMT2204EIB',
                        'tokens' => [
                            '5KMT2204EIB',
                            '5KMT2204IB',
                            'INK_BLUE',
                            'ink_blue',
                            'ink_bleu',
                        ],
                    ],
                    [
                        'color' => 'Medallion Silver',
                        'sku' => '5KMT2204EMS',
                        'tokens' => [
                            '5KMT2204EMS',
                            '5KMT2204MS',
                            'medallion_silver',
                        ],
                    ],
                    [
                        'color' => 'Candy Apple',
                        'sku' => '5KMT2204ECA',
                        'tokens' => [
                            '5KMT2204ECA',
                            '5KMT2204CA',
                            'candy_apple',
                        ],
                    ],
                ],
            ],

            [
                'slug' => 'kitchenaid-bowl-stand-mixer',
                'source_directory' =>
                    'Kitchenaid/Kitchenaid bowlstand mixer',

                'variants' => [
                    [
                        'color' => 'Contour Silver',
                        'sku' => '5KSM125ECU',
                        'tokens' => [
                            '5KSM125ECU',
                            '5KSM125BCU',
                            'contour-silver',
                            'Contour_silver',
                            'contour_silver',
                        ],
                    ],
                    [
                        'color' => 'Black Matte',
                        'sku' => '5KSM125EBM',
                        'tokens' => [
                            '5KSM125EBM',
                            '5KSM125-black-matte',
                            'black-matte',
                            'black_matte',
                        ],
                    ],
                    [
                        'color' => 'Milkshake',
                        'sku' => '5KSM125EMH',
                        'tokens' => [
                            '5KSM125EMH',
                            '5KSM125BMH',
                            '5KSM125_MH',
                            'Milkshake',
                        ],
                    ],
                ],
            ],

            [
                'slug' => 'kitchenaid-food-chopper',
                'source_directory' =>
                    'Kitchenaid/Kitchenaid foodchopper',

                'variants' => [
                    [
                        'color' => 'Cream',
                        'sku' => '5KFC3516EAC',
                        'tokens' => [
                            '5KFC3516EAC',
                            'Almendra',
                        ],
                    ],
                    [
                        'color' => 'Empire Red',
                        'sku' => '5KFC3516EER',
                        'tokens' => [
                            '5KFC3516EER',
                            '5KFC3516-ER',
                        ],
                    ],
                    [
                        'color' => 'Onyx Black',
                        'sku' => '5KFC3516EOB',
                        'tokens' => [
                            '5KFC3516EOB',
                            '5KFC3516-OB',
                        ],
                    ],
                    [
                        'color' => 'Silver Grey',
                        'sku' => '5KFC3516ECU',
                        'tokens' => [
                            '5KFC3516ECU',
                            '5KFC3516-CU',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildImageDirectoryMap(): array
    {
        $map = [];

        foreach (File::allFiles($this->sourceRoot) as $file) {
            if (!$this->isSupportedImage(
                $file->getExtension()
            )) {
                continue;
            }

            $directory = $file->getPath();

            $relative = ltrim(
                str_replace(
                    $this->sourceRoot,
                    '',
                    $directory
                ),
                '\\/'
            );

            $key = $this->normalizeRelativePath(
                $relative
            );

            if (
                isset($map[$key])
                && $map[$key] !== $directory
            ) {
                throw new RuntimeException(
                    "Normalized directory collision: {$key}"
                );
            }

            $map[$key] = $directory;
        }

        return $map;
    }

    private function resolveSourceDirectory(
        string $relativeDirectory
    ): string {
        $key = $this->normalizeRelativePath(
            $relativeDirectory
        );

        if (!isset($this->directoryMap[$key])) {
            throw new RuntimeException(
                'Source product directory not found: '
                . $relativeDirectory
            );
        }

        return $this->directoryMap[$key];
    }

    private function normalizeRelativePath(
        string $path
    ): string {
        $parts = preg_split(
            '#[\\\\/]+#',
            trim($path)
        );

        $normalized = [];

        foreach ($parts ?: [] as $part) {
            $value = strtolower(
                trim($part)
            );

            $value = preg_replace(
                '/[^a-z0-9]+/',
                ' ',
                $value
            );

            $value = preg_replace(
                '/\s+/',
                ' ',
                $value ?? ''
            );

            $value = trim(
                $value ?? ''
            );

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return implode(
            '/',
            $normalized
        );
    }

    private function filenameMatchesTokens(
        string $filename,
        array $tokens
    ): bool {
        $filename = strtolower(
            $filename
        );

        foreach ($tokens as $token) {
            if (
                str_contains(
                    $filename,
                    strtolower($token)
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function isSupportedImage(
        string $extension
    ): bool {
        return in_array(
            strtolower($extension),
            [
                'jpg',
                'jpeg',
                'png',
                'webp',
                'avif',
            ],
            true
        );
    }
}
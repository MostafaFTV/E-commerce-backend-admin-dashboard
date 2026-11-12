<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Temporary localized columns
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'attributes',
            function (Blueprint $table): void {
                $table->json('name_i18n')
                    ->nullable()
                    ->after('name');
            }
        );

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->json('value_i18n')
                    ->nullable()
                    ->after('value');
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Preserve existing mixed-language data
        |--------------------------------------------------------------------------
        |
        | Current database contains both English and Persian legacy records.
        | Persian-script text is moved to "fa".
        | Other text is moved to "en".
        |
        */

        DB::table('attributes')
            ->select([
                'id',
                'name',
            ])
            ->orderBy('id')
            ->each(
                function ($attribute): void {
                    $name = trim(
                        (string) $attribute->name
                    );

                    $locale =
                        preg_match(
                            '/[\x{0600}-\x{06FF}]/u',
                            $name
                        ) === 1
                            ? 'fa'
                            : 'en';

                    DB::table('attributes')
                        ->where(
                            'id',
                            $attribute->id
                        )
                        ->update([
                            'name_i18n' =>
                                json_encode(
                                    [
                                        $locale =>
                                            $name,
                                    ],
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                    | JSON_THROW_ON_ERROR
                                ),
                        ]);
                }
            );

        DB::table('attribute_values')
            ->select([
                'id',
                'value',
            ])
            ->orderBy('id')
            ->each(
                function ($attributeValue): void {
                    $value = trim(
                        (string) $attributeValue->value
                    );

                    $locale =
                        preg_match(
                            '/[\x{0600}-\x{06FF}]/u',
                            $value
                        ) === 1
                            ? 'fa'
                            : 'en';

                    DB::table('attribute_values')
                        ->where(
                            'id',
                            $attributeValue->id
                        )
                        ->update([
                            'value_i18n' =>
                                json_encode(
                                    [
                                        $locale =>
                                            $value,
                                    ],
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                    | JSON_THROW_ON_ERROR
                                ),
                        ]);
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Foreign-key supporting index
        |--------------------------------------------------------------------------
        |
        | Previously MySQL was using the composite unique index
        | (attribute_id, value) to support the attribute_id FK.
        |
        | Before removing that unique index we must provide a dedicated
        | attribute_id index.
        |
        */

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->index(
                    'attribute_id',
                    'attribute_values_attribute_id_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Remove old uniqueness before removing VARCHAR value
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'attribute_values_attr_value_unique'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Replace legacy columns
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'attributes',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'name'
                );
            }
        );

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'value'
                );
            }
        );

        Schema::table(
            'attributes',
            function (Blueprint $table): void {
                $table->renameColumn(
                    'name_i18n',
                    'name'
                );
            }
        );

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->renameColumn(
                    'value_i18n',
                    'value'
                );
            }
        );

        if (
            DB::getDriverName() ===
            'mysql'
        ) {
            DB::statement(
                'ALTER TABLE attributes
                 MODIFY `name` JSON NOT NULL'
            );

            DB::statement(
                'ALTER TABLE attribute_values
                 MODIFY `value` JSON NOT NULL'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Locale-aware generated columns
        |--------------------------------------------------------------------------
        |
        | JSON itself cannot use the old (attribute_id, value) unique index.
        |
        | These generated columns preserve uniqueness independently for
        | English and Persian while allowing either translation to be absent.
        |
        */

        if (
            DB::getDriverName() ===
            'mysql'
        ) {
            DB::statement(<<<'SQL'
ALTER TABLE attribute_values
ADD COLUMN value_en VARCHAR(150)
    GENERATED ALWAYS AS (
        NULLIF(
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    `value`,
                    '$.en'
                )
            ),
            ''
        )
    ) STORED,
ADD COLUMN value_fa VARCHAR(150)
    GENERATED ALWAYS AS (
        NULLIF(
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    `value`,
                    '$.fa'
                )
            ),
            ''
        )
    ) STORED
SQL);
        } else {
            /*
             * SQLite test database.
             *
             * SQLite supports generated VIRTUAL
             * columns, but MySQL's JSON_UNQUOTE
             * syntax and STORED ALTER syntax are
             * not portable.
             */
            DB::statement(
                "ALTER TABLE attribute_values
                 ADD COLUMN value_en TEXT
                 GENERATED ALWAYS AS (
                     NULLIF(
                         json_extract(
                             value,
                             '$.en'
                         ),
                         ''
                     )
                 ) VIRTUAL"
            );

            DB::statement(
                "ALTER TABLE attribute_values
                 ADD COLUMN value_fa TEXT
                 GENERATED ALWAYS AS (
                     NULLIF(
                         json_extract(
                             value,
                             '$.fa'
                         ),
                         ''
                     )
                 ) VIRTUAL"
            );
        }

        DB::statement(
            'CREATE UNIQUE INDEX
             attribute_values_attr_en_unique
             ON attribute_values (
                 attribute_id,
                 value_en
             )'
        );

        DB::statement(
            'CREATE UNIQUE INDEX
             attribute_values_attr_fa_unique
             ON attribute_values (
                 attribute_id,
                 value_fa
             )'
        );
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Remove localized uniqueness
        |--------------------------------------------------------------------------
        */

        DB::statement(
            'DROP INDEX
             attribute_values_attr_en_unique
             ON attribute_values'
        );

        DB::statement(
            'DROP INDEX
             attribute_values_attr_fa_unique
             ON attribute_values'
        );

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'value_en',
                    'value_fa',
                ]);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Create legacy fallback columns
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'attributes',
            function (Blueprint $table): void {
                $table->string(
                    'name_legacy',
                    100
                )->nullable();
            }
        );

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->string(
                    'value_legacy',
                    150
                )->nullable();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Pick English first, Persian second
        |--------------------------------------------------------------------------
        */

        DB::statement(<<<'SQL'
UPDATE attributes
SET name_legacy =
    COALESCE(
        NULLIF(
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    `name`,
                    '$.en'
                )
            ),
            ''
        ),
        NULLIF(
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    `name`,
                    '$.fa'
                )
            ),
            ''
        )
    )
SQL);

        DB::statement(<<<'SQL'
UPDATE attribute_values
SET value_legacy =
    COALESCE(
        NULLIF(
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    `value`,
                    '$.en'
                )
            ),
            ''
        ),
        NULLIF(
            JSON_UNQUOTE(
                JSON_EXTRACT(
                    `value`,
                    '$.fa'
                )
            ),
            ''
        )
    )
SQL);

        /*
        |--------------------------------------------------------------------------
        | Restore VARCHAR columns
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'attributes',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'name'
                );

                $table->renameColumn(
                    'name_legacy',
                    'name'
                );
            }
        );

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'value'
                );

                $table->renameColumn(
                    'value_legacy',
                    'value'
                );
            }
        );

        if (
            DB::getDriverName() ===
            'mysql'
        ) {
            DB::statement(
                'ALTER TABLE attributes
                 MODIFY `name`
                 VARCHAR(100)
                 NOT NULL'
            );

            DB::statement(
                'ALTER TABLE attribute_values
                 MODIFY `value`
                 VARCHAR(150)
                 NOT NULL'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Restore old unique key
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'attribute_id',
                        'value',
                    ],
                    'attribute_values_attr_value_unique'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Old unique now supports the FK again
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'attribute_values',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'attribute_values_attribute_id_index'
                );
            }
        );
    }
};
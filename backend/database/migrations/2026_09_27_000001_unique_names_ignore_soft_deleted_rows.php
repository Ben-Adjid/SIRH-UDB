<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * name/code must stay unique among active rows only, so that a deleted UFR,
 * subject, classroom or teaching type does not block reusing its name.
 * Each unique index now targets a generated column that is NULL once the row
 * is soft-deleted (NULLs never collide in a unique index).
 */
return new class extends Migration {
    private const COLUMNS = [
        'ufrs' => ['name' => 255, 'code' => 255],
        'subjects' => ['name' => 255, 'code' => 255],
        'classrooms' => ['name' => 255, 'code' => 255],
        'teaching_types' => ['name' => 255, 'code' => 255],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $length) {
                $existing = $this->uniqueIndexOn($table, [$column]);
                Schema::table($table, function (Blueprint $t) use ($table, $column, $length, $existing) {
                    if ($existing) {
                        $t->dropUnique($existing);
                    }
                    $t->string("{$column}_active", $length)->nullable()
                        ->virtualAs("CASE WHEN deleted_at IS NULL THEN {$column} END");
                    $t->unique("{$column}_active", "{$table}_{$column}_active_unique");
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $length) {
                Schema::table($table, function (Blueprint $t) use ($table, $column) {
                    $t->dropUnique("{$table}_{$column}_active_unique");
                    $t->dropColumn("{$column}_active");
                    $t->unique($column);
                });
            }
        }
    }

    private function uniqueIndexOn(string $table, array $columns): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && !$index['primary'] && $index['columns'] === $columns) {
                return $index['name'];
            }
        }
        return null;
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropUniqueIndexIfPresent('users_email_unique');
        $this->dropUniqueIndexIfPresent('users_phone_unique');
    }

    public function down(): void
    {
        if (! $this->hasIndex('users_email_unique') && $this->canRestoreUnique('email')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique('email'));
        }

        if (! $this->hasIndex('users_phone_unique') && $this->canRestoreUnique('phone')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique('phone'));
        }
    }

    private function dropUniqueIndexIfPresent(string $indexName): void
    {
        if (! $this->hasIndex($indexName)) {
            return;
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropUnique($indexName));
    }

    private function hasIndex(string $indexName): bool
    {
        $schema = Schema::getFacadeRoot();
        if (method_exists($schema, 'hasIndex')) {
            return Schema::hasIndex('users', $indexName);
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => ! empty($connection->select(
                'SHOW INDEX FROM `' . str_replace('`', '``', $connection->getTablePrefix() . 'users') . '` WHERE Key_name = ?',
                [$indexName]
            )),
            'pgsql' => DB::table('pg_indexes')
                ->where('tablename', 'users')
                ->where('indexname', $indexName)
                ->exists(),
            'sqlite' => collect($connection->select("PRAGMA index_list('users')"))
                ->contains(fn ($index) => ($index->name ?? null) === $indexName),
            default => true,
        };
    }

    private function canRestoreUnique(string $column): bool
    {
        return ! DB::table('users')
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
};

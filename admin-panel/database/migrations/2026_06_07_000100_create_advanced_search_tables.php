<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_indexes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('keywords')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('search_score', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id']);
            $table->index(['entity_type', 'is_active']);
            $table->index(['restaurant_id', 'is_active']);
            $table->index(['latitude', 'longitude']);
            $table->fullText(['title', 'description', 'keywords']);
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('keyword')->index();
            $table->string('clicked_result')->nullable();
            $table->string('result_type', 40)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->string('device_type', 40)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['result_type', 'result_id']);
        });

        Schema::create('trending_searches', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->unsignedInteger('total_searches')->default(0);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->string('replacement');
            $table->timestamps();
        });

        DB::table('search_synonyms')->insert([
            ['keyword' => 'biriyani', 'replacement' => 'biryani', 'created_at' => now(), 'updated_at' => now()],
            ['keyword' => 'piza', 'replacement' => 'pizza', 'created_at' => now(), 'updated_at' => now()],
            ['keyword' => 'burgar', 'replacement' => 'burger', 'created_at' => now(), 'updated_at' => now()],
            ['keyword' => 'cold drink', 'replacement' => 'soft drink', 'created_at' => now(), 'updated_at' => now()],
            ['keyword' => 'cocacola', 'replacement' => 'coca cola', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('trending_searches');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('search_indexes');
    }
};

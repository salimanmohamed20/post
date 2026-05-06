<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('legacy_source_id')->nullable()->unique()->after('slug');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('legacy_source_id')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['legacy_source_id']);
            $table->dropColumn('legacy_source_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['legacy_source_id']);
            $table->dropColumn('legacy_source_id');
        });
    }
};

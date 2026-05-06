<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedBigInteger('wp_post_author')->nullable()->after('legacy_source_id');
            $table->dateTime('wp_post_date_gmt')->nullable()->after('published_at');
            $table->string('wp_post_status', 20)->nullable()->after('is_published');
            $table->string('wp_comment_status', 20)->nullable()->after('wp_post_status');
            $table->string('wp_ping_status', 20)->nullable()->after('wp_comment_status');
            $table->string('wp_post_password', 255)->nullable()->after('wp_ping_status');
            $table->text('wp_to_ping')->nullable()->after('wp_post_password');
            $table->text('wp_pinged')->nullable()->after('wp_to_ping');
            $table->dateTime('wp_post_modified')->nullable()->after('wp_pinged');
            $table->dateTime('wp_post_modified_gmt')->nullable()->after('wp_post_modified');
            $table->longText('wp_post_content_filtered')->nullable()->after('wp_post_modified_gmt');
            $table->unsignedBigInteger('wp_post_parent')->nullable()->after('wp_post_content_filtered');
            $table->string('wp_guid')->nullable()->after('wp_post_parent');
            $table->integer('wp_menu_order')->nullable()->after('wp_guid');
            $table->string('wp_post_type', 20)->nullable()->after('wp_menu_order');
            $table->string('wp_post_mime_type', 100)->nullable()->after('wp_post_type');
            $table->unsignedBigInteger('wp_comment_count')->nullable()->after('wp_post_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'wp_post_author',
                'wp_post_date_gmt',
                'wp_post_status',
                'wp_comment_status',
                'wp_ping_status',
                'wp_post_password',
                'wp_to_ping',
                'wp_pinged',
                'wp_post_modified',
                'wp_post_modified_gmt',
                'wp_post_content_filtered',
                'wp_post_parent',
                'wp_guid',
                'wp_menu_order',
                'wp_post_type',
                'wp_post_mime_type',
                'wp_comment_count',
            ]);
        });
    }
};


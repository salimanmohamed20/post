<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'title',
    'slug',
    'legacy_source_id',
    'body',
    'excerpt',
    'category_id',
    'published_at',
    'is_published',
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
])]
class Article extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'wp_post_date_gmt' => 'datetime',
            'wp_post_modified' => 'datetime',
            'wp_post_modified_gmt' => 'datetime',
            'wp_post_author' => 'integer',
            'wp_post_parent' => 'integer',
            'wp_menu_order' => 'integer',
            'wp_comment_count' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk(config('filesystems.default', 'public'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 320, 220)
            ->nonQueued();

        $this->addMediaConversion('card')
            ->fit(Fit::Crop, 720, 420)
            ->nonQueued();
    }
}

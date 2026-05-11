<?php

namespace App\Jobs;

use App\Imports\LegacySourceReader;
use App\Models\Article;
use App\Models\Category;
use App\Models\ImportLog;
use App\Services\CacheInvalidationService;
use App\Services\ImageService;
use App\Services\InlineArticleImageService;
use App\Services\SlugService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ImportArticlesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?string $sourcePath = null,
    ) {}

    public function handle(
        LegacySourceReader $reader,
        SlugService $slugs,
        ImageService $images,
        InlineArticleImageService $inlineImages,
        CacheInvalidationService $cache,
    ): void {
        $imported = 0;
        $failed = [];
        $seen = [];

        $rows = $this->previewRows($reader);


        foreach ($rows as $row) {
            $legacyId = $this->legacyId($row);
            $slug = (string) ($row['slug'] ?? '');

            if (isset($seen[$slug])) {
                $failed[] = $this->failure($legacyId, $slug, 'duplicate_slug_in_import_file');
                continue;
            }

            $seen[$slug] = true;
            $category = $this->resolveCategory($row);

            if (! $category) {
                $failed[] = $this->failure($legacyId, $slug, 'missing_category_mapping');
                continue;
            }

            $existing = Article::query()
                ->when(
                    filled($legacyId),
                    fn ($query) => $query->where('legacy_source_id', (string) $legacyId)->orWhere('slug', $slug),
                    fn ($query) => $query->where('slug', $slug),
                )
                ->first();
            $reason = $slugs->validateImportedSlug($slug, Article::class, $existing?->id);

            if ($reason !== null) {
                $failed[] = $this->failure($legacyId, $slug, $reason);
                continue;
            }

            $article = Article::query()->updateOrCreate(
                filled($legacyId)
                    ? ['legacy_source_id' => (string) $legacyId]
                    : ['slug' => $slug],
                [
                    'slug' => $slug,
                    'title' => (string) ($row['title'] ?? $slug),
                    'legacy_source_id' => filled($legacyId) ? (string) $legacyId : null,
                    'body' => $this->normalizeBodyImages((string) ($row['body'] ?? '')),
                    'excerpt' => $row['excerpt'] ?? null,
                    'category_id' => $category->id,
                    'published_at' => isset($row['published_at']) ? Carbon::parse($row['published_at']) : null,
                    'is_published' => (bool) ($row['is_published'] ?? $row['published'] ?? false),
                    'wp_post_author' => $this->nullableInt($row['wp_post_author'] ?? null),
                    'wp_post_date_gmt' => $this->nullableDateTime($row['wp_post_date_gmt'] ?? null),
                    'wp_post_status' => $this->nullableString($row['wp_post_status'] ?? null),
                    'wp_comment_status' => $this->nullableString($row['wp_comment_status'] ?? null),
                    'wp_ping_status' => $this->nullableString($row['wp_ping_status'] ?? null),
                    'wp_post_password' => $this->nullableString($row['wp_post_password'] ?? null),
                    'wp_to_ping' => $this->nullableString($row['wp_to_ping'] ?? null),
                    'wp_pinged' => $this->nullableString($row['wp_pinged'] ?? null),
                    'wp_post_modified' => $this->nullableDateTime($row['wp_post_modified'] ?? null),
                    'wp_post_modified_gmt' => $this->nullableDateTime($row['wp_post_modified_gmt'] ?? null),
                    'wp_post_content_filtered' => $this->nullableString($row['wp_post_content_filtered'] ?? null),
                    'wp_post_parent' => $this->nullableInt($row['wp_post_parent'] ?? null),
                    'wp_guid' => $this->nullableString($row['wp_guid'] ?? null),
                    'wp_menu_order' => $this->nullableInt($row['wp_menu_order'] ?? null),
                    'wp_post_type' => $this->nullableString($row['wp_post_type'] ?? null),
                    'wp_post_mime_type' => $this->nullableString($row['wp_post_mime_type'] ?? null),
                    'wp_comment_count' => $this->nullableInt($row['wp_comment_count'] ?? null),
                ],
            );

            try {
                $images->attachArticleImages($article, $this->imagePaths($row), true);
            } catch (\Throwable $exception) {
                $failed[] = $this->failure($legacyId, $slug, 'failed_image_import: ' . $exception->getMessage());
            }

            $localizedBody = $inlineImages->localizeForArticle($article, (string) $article->body);
            if ($localizedBody !== (string) $article->body) {
                $article->forceFill(['body' => $localizedBody])->saveQuietly();
            }

            $imported++;

        }

        ImportLog::query()->create([
            'source' => 'articles',
            'imported_count' => $imported,
            'skipped_count' => count($failed),
            'failed_rows' => $failed,
        ]);

        $cache->flushPublicContent();

    }

    /** @return array<int, array<string, mixed>> */
    public function previewRows(LegacySourceReader $reader): array
    {
        return $this->articleRows($reader);
    }

    /** @return array{old_source_id:mixed,old_slug:string,reason:string} */
    private function failure(mixed $legacyId, string $slug, string $reason): array
    {
        return ['old_source_id' => $legacyId, 'old_slug' => $slug, 'reason' => $reason];
    }

    /** @param array<string, mixed> $row */
    private function resolveCategory(array $row): ?Category
    {
        $nestedCategory = is_array($row['category'] ?? null) ? $row['category'] : [];

        $categorySlug = $row['category_slug']
            ?? $nestedCategory['slug']
            ?? null;

        if (filled($categorySlug)) {
            $category = Category::query()->where('slug', (string) $categorySlug)->first();

            if ($category) {
                return $category;
            }
        }

        $categoryLegacyId = $row['category_old_id']
            ?? $row['category_legacy_id']
            ?? $row['category_id']
            ?? $nestedCategory['legacy_id']
            ?? $nestedCategory['old_source_id']
            ?? $nestedCategory['old_id']
            ?? $nestedCategory['id']
            ?? null;

        if (filled($categoryLegacyId)) {
            return Category::query()
                ->where('legacy_source_id', (string) $categoryLegacyId)
                ->first();
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function imagePaths(array $row): array
    {
        $images = $row['images'] ?? $row['image_urls'] ?? $row['image_paths'] ?? [];

        if (is_string($images)) {
            $images = array_filter(array_map('trim', explode(',', $images)));
        }

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $image): ?string {
            if (is_string($image) && filled($image)) {
                return $image;
            }

            if (is_array($image)) {
                $path = $image['url'] ?? $image['path'] ?? $image['src'] ?? null;

                return filled($path) ? (string) $path : null;
            }

            return null;
        }, $images)));
    }

    /** @param array<string, mixed> $row */
    private function legacyId(array $row): mixed
    {
        return $row['legacy_id'] ?? $row['old_source_id'] ?? $row['old_id'] ?? $row['ID'] ?? $row['id'] ?? null;
    }

    /** @return array<int, array<string, mixed>> */
    private function articleRows(LegacySourceReader $reader): array
    {
        $path = $this->sourcePath ?? config('legacy_import.articles');
        $rows = $reader->rows($path, config('legacy_import.article_tables', []));

        if ($this->looksNormalizedArticleRows($rows)) {
            return $rows;
        }

        return $this->normalizeWordPressArticles($reader, $path);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function looksNormalizedArticleRows(array $rows): bool
    {
        return $rows !== [] && isset($rows[0]['slug']) && isset($rows[0]['title']);
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeWordPressArticles(LegacySourceReader $reader, ?string $path): array
    {
        $posts = $reader->rows($path, ['wp_posts', 'posts', 'post']);
        $postMeta = $reader->rows($path, ['wp_postmeta', 'postmeta']);
        $termRelationships = $reader->rows($path, ['wp_term_relationships', 'term_relationships']);
        $termTaxonomy = $reader->rows($path, ['wp_term_taxonomy', 'term_taxonomy']);

        if ($posts === []) {
            return [];
        }

        $postsById = [];
        foreach ($posts as $post) {
            $id = $post['ID'] ?? $post['id'] ?? null;
            if ($id !== null) {
                $postsById[(string) $id] = $post;
            }
        }

        $categoryTaxonomyByObject = [];
        $categoryTermByTaxonomyId = [];
        foreach ($termTaxonomy as $taxonomyRow) {
            if (($taxonomyRow['taxonomy'] ?? null) !== 'category') {
                continue;
            }

            $taxonomyId = $taxonomyRow['term_taxonomy_id'] ?? null;
            $termId = $taxonomyRow['term_id'] ?? null;

            if ($taxonomyId !== null && $termId !== null) {
                $categoryTermByTaxonomyId[(string) $taxonomyId] = $termId;
            }
        }

        foreach ($termRelationships as $relationshipRow) {
            $objectId = $relationshipRow['object_id'] ?? null;
            $taxonomyId = $relationshipRow['term_taxonomy_id'] ?? null;

            if ($objectId === null || $taxonomyId === null) {
                continue;
            }

            $termId = $categoryTermByTaxonomyId[(string) $taxonomyId] ?? null;
            if ($termId !== null) {
                $categoryTaxonomyByObject[(string) $objectId] = $termId;
            }
        }

        $metaByPost = [];
        foreach ($postMeta as $metaRow) {
            $postId = $metaRow['post_id'] ?? null;
            $metaKey = $metaRow['meta_key'] ?? null;
            $metaValue = $metaRow['meta_value'] ?? null;

            if ($postId === null || ! is_string($metaKey)) {
                continue;
            }

            $metaByPost[(string) $postId][$metaKey][] = is_scalar($metaValue) ? (string) $metaValue : '';
        }

        $normalized = [];
        foreach ($posts as $post) {
            $postType = (string) ($post['post_type'] ?? '');
            $postStatus = (string) ($post['post_status'] ?? '');

            if ($postType !== 'post' || in_array($postStatus, ['trash', 'auto-draft', 'inherit'], true)) {
                continue;
            }

            $id = $post['ID'] ?? $post['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $postId = (string) $id;
            $images = $this->extractWordPressImages($metaByPost[$postId] ?? [], $metaByPost, $postsById);
            $normalized[] = [
                'legacy_id' => $id,
                'slug' => (string) ($post['post_name'] ?? Str::slug((string) ($post['post_title'] ?? $postId))),
                'title' => (string) ($post['post_title'] ?? $postId),
                'body' => (string) ($post['post_content'] ?? ''),
                'excerpt' => $post['post_excerpt'] ?? null,
                'published_at' => $post['post_date'] ?? null,
                'is_published' => $postStatus === 'publish',
                'wp_post_author' => $post['post_author'] ?? null,
                'wp_post_date_gmt' => $post['post_date_gmt'] ?? null,
                'wp_post_status' => $post['post_status'] ?? null,
                'wp_comment_status' => $post['comment_status'] ?? null,
                'wp_ping_status' => $post['ping_status'] ?? null,
                'wp_post_password' => $post['post_password'] ?? null,
                'wp_to_ping' => $post['to_ping'] ?? null,
                'wp_pinged' => $post['pinged'] ?? null,
                'wp_post_modified' => $post['post_modified'] ?? null,
                'wp_post_modified_gmt' => $post['post_modified_gmt'] ?? null,
                'wp_post_content_filtered' => $post['post_content_filtered'] ?? null,
                'wp_post_parent' => $post['post_parent'] ?? null,
                'wp_guid' => $post['guid'] ?? null,
                'wp_menu_order' => $post['menu_order'] ?? null,
                'wp_post_type' => $post['post_type'] ?? null,
                'wp_post_mime_type' => $post['post_mime_type'] ?? null,
                'wp_comment_count' => $post['comment_count'] ?? null,
                'category_legacy_id' => $categoryTaxonomyByObject[$postId] ?? null,
                'images' => $images,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<int, string>>  $meta
     * @param  array<string, array<string, array<int, string>>>  $allMetaByPost
     * @param  array<string, array<string, mixed>>  $postsById
     * @return array<int, string>
     */
    private function extractWordPressImages(array $meta, array $allMetaByPost, array $postsById): array
    {
        $images = [];

        foreach (['_thumbnail_url', '_thumbnail_path', 'image', 'images'] as $key) {
            foreach ($meta[$key] ?? [] as $value) {
                if (filled($value)) {
                    $images[] = $this->resolveWordPressImagePath($value);
                }
            }
        }

        foreach ($meta['_thumbnail_id'] ?? [] as $thumbnailId) {
            $attachmentMeta = $allMetaByPost[(string) $thumbnailId] ?? [];
            foreach ($attachmentMeta['_wp_attached_file'] ?? [] as $attachedFile) {
                if (filled($attachedFile)) {
                    $images[] = $this->resolveWordPressImagePath($attachedFile);
                }
            }

            $attachment = $postsById[(string) $thumbnailId] ?? null;
            $guid = is_array($attachment) ? ($attachment['guid'] ?? null) : null;
            if (filled($guid)) {
                $images[] = $this->resolveWordPressImagePath((string) $guid);
            }
        }

        foreach (['_wp_attached_file'] as $key) {
            foreach ($meta[$key] ?? [] as $value) {
                if (filled($value)) {
                    $images[] = $this->resolveWordPressImagePath($value);
                }
            }
        }

        return array_values(array_unique(array_filter($images)));
    }

    private function normalizeBodyImages(string $html): string
    {
        if (blank($html)) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            function (array $matches): string {
                $prefix = $matches[1] ?? '';
                $src = $matches[2] ?? '';
                $suffix = $matches[3] ?? '';

                return $prefix . $this->resolveWordPressImagePath($src) . $suffix;
            },
            $html,
        );
    }

    private function resolveWordPressImagePath(string $pathOrUrl): string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return $pathOrUrl;
        }

        $pathOrUrl = $this->fixDuplicatedAbsoluteUrl($pathOrUrl);

        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            return $this->applyHostFallbacks($pathOrUrl);
        }

        if (str_starts_with($pathOrUrl, '//')) {
            return 'https:' . $pathOrUrl;
        }

        $base = rtrim((string) config('legacy_import.wordpress_uploads_base_url'), '/');
        if ($base === '') {
            return $pathOrUrl;
        }

        if (str_starts_with($pathOrUrl, '/wp-content/uploads/')) {
            return $base . $pathOrUrl;
        }

        if (str_starts_with($pathOrUrl, 'wp-content/uploads/')) {
            return $base . '/' . $pathOrUrl;
        }

        if (preg_match('/^\d{4}\/\d{2}\//', $pathOrUrl) === 1) {
            return $base . '/wp-content/uploads/' . ltrim($pathOrUrl, '/');
        }

        if (str_starts_with($pathOrUrl, '/')) {
            return $base . $pathOrUrl;
        }

        return $base . '/' . ltrim($pathOrUrl, '/');
    }

    private function fixDuplicatedAbsoluteUrl(string $value): string
    {
        if (! str_contains($value, 'http://') && ! str_contains($value, 'https://')) {
            return $value;
        }

        // Handles malformed links like:
        // https://domain-a.com/https://domain-b.com/path.jpg
        $pos = stripos($value, '://');
        if ($pos !== false) {
            $secondHttp = stripos($value, 'http://', $pos + 3);
            $secondHttps = stripos($value, 'https://', $pos + 3);

            $candidates = array_filter([$secondHttp, $secondHttps], fn ($v) => $v !== false);
            if ($candidates !== []) {
                $start = min($candidates);
                if (is_int($start) && $start > 0) {
                    return substr($value, $start);
                }
            }
        }

        if (preg_match('/https?:\/\/.+?(https?:\/\/.+)$/i', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    private function applyHostFallbacks(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        if (! is_string($host) || $host === '') {
            return $url;
        }

        foreach ((array) config('legacy_import.legacy_image_host_fallbacks', []) as $rule) {
            if (! is_string($rule) || ! str_contains($rule, '=>')) {
                continue;
            }

            [$from, $to] = array_map('trim', explode('=>', $rule, 2));
            if ($from === '' || $to === '' || strcasecmp($host, $from) !== 0) {
                continue;
            }

            $scheme = $parts['scheme'] ?? 'https';
            $path = $parts['path'] ?? '';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

            return $scheme . '://' . $to . $path . $query . $fragment;
        }

        return $url;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableDateTime(mixed $value): ?Carbon
    {
        $normalized = $this->nullableString($value);
        if ($normalized === null || $normalized === '0000-00-00 00:00:00') {
            return null;
        }

        return Carbon::parse($normalized);
    }
}

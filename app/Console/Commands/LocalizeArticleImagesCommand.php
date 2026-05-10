<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class LocalizeArticleImagesCommand extends Command
{
    protected $signature = 'articles:localize-images
                            {--article= : Localize images for a specific article ID}
                            {--dry-run : Show what would be changed without saving}';

    protected $description = 'Download external images in article body content and replace with local URLs';

    /** @var array<string, string> */
    private array $cache = [];

    private int $downloaded = 0;

    private int $failed = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $query = Article::query();

        if ($articleId = $this->option('article')) {
            $query->where('id', $articleId);
        }

        $isDryRun = (bool) $this->option('dry-run');

        $articles = $query->get();
        $total = $articles->count();

        $this->info("Processing {$total} articles...");
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - no changes will be saved');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updatedCount = 0;

        foreach ($articles as $article) {
            $body = (string) $article->body;

            if ($body === '' || ! str_contains($body, '<img')) {
                $bar->advance();
                continue;
            }

            $newBody = $this->localizeImages($body);

            if ($newBody !== $body) {
                if (! $isDryRun) {
                    $article->forceFill(['body' => $newBody])->saveQuietly();
                }
                $updatedCount++;
                $this->newLine();
                $this->line("  ✓ Article #{$article->id} ({$article->slug}) - images localized");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total articles', $total],
                ['Articles updated', $updatedCount],
                ['Images downloaded', $this->downloaded],
                ['Images failed', $this->failed],
                ['Images already local', $this->skipped],
            ],
        );

        return self::SUCCESS;
    }

    private function localizeImages(string $html): string
    {
        return (string) preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            function (array $matches): string {
                $prefix = $matches[1] ?? '';
                $src = trim((string) ($matches[2] ?? ''));
                $suffix = $matches[3] ?? '';

                if ($src === '') {
                    return $prefix . $src . $suffix;
                }

                // Already a local URL
                if ($this->isLocalUrl($src)) {
                    $this->skipped++;
                    return $prefix . $src . $suffix;
                }

                // Unwrap proxy URLs to get the real source
                $src = $this->unwrapProxyUrl($src);

                if (! filter_var($src, FILTER_VALIDATE_URL)) {
                    return $prefix . $src . $suffix;
                }

                $localUrl = $this->downloadImage($src);

                if ($localUrl !== null) {
                    return $prefix . $localUrl . $suffix;
                }

                // Could not download, keep original
                return $prefix . $src . $suffix;
            },
            $html,
        );
    }

    private function isLocalUrl(string $url): bool
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($url, $appUrl)) {
            return true;
        }

        // Relative storage URLs
        if (str_starts_with($url, '/storage/')) {
            return true;
        }

        // Already localized inline images
        if (str_contains($url, 'imports/inline-images/')) {
            return true;
        }

        return false;
    }

    private function unwrapProxyUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_contains($path, 'legacy-image-proxy')) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return $url;
        }

        parse_str($query, $params);
        $inner = $params['url'] ?? null;

        return is_string($inner) && filter_var($inner, FILTER_VALIDATE_URL) ? $inner : $url;
    }

    private function downloadImage(string $url): ?string
    {
        if (isset($this->cache[$url])) {
            $cached = $this->cache[$url];
            return $cached === '' ? null : $cached;
        }

        try {
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->retry(3, 500)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'image/*,*/*;q=0.8',
                    'Referer' => $this->refererFromUrl($url),
                ])
                ->get($url);
        } catch (\Throwable $e) {
            $this->failed++;
            $this->cache[$url] = '';
            $this->newLine();
            $this->error("  ✗ Failed to download: {$url} ({$e->getMessage()})");
            return null;
        }

        if (! $response->successful()) {
            $this->failed++;
            $this->cache[$url] = '';
            $this->newLine();
            $this->error("  ✗ HTTP {$response->status()}: {$url}");
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if (! str_starts_with($contentType, 'image/')) {
            $this->failed++;
            $this->cache[$url] = '';
            $this->newLine();
            $this->warn("  ✗ Not an image ({$contentType}): {$url}");
            return null;
        }

        $extension = $this->extensionFromResponse($url, $contentType);
        $relativePath = 'imports/inline-images/' . date('Y/m') . '/' . sha1($url) . '.' . $extension;

        // Check if already downloaded before
        if (Storage::disk('public')->exists($relativePath)) {
            $localUrl = Storage::disk('public')->url($relativePath);
            $this->cache[$url] = $localUrl;
            $this->skipped++;
            return $localUrl;
        }

        Storage::disk('public')->put($relativePath, $response->body());

        $localUrl = Storage::disk('public')->url($relativePath);
        $this->cache[$url] = $localUrl;
        $this->downloaded++;

        return $localUrl;
    }

    private function refererFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        return $host === '' ? '' : $scheme . '://' . $host . '/';
    }

    private function extensionFromResponse(string $url, string $contentType): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            return $ext;
        }

        $type = strtolower(trim(explode(';', $contentType)[0]));

        return match ($type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }
}

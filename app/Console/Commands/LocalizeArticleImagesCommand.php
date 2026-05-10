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
        $origins = $this->parseCandidateOrigins();

        if ($origins === []) {
            $this->error('No candidate origins configured!');
            $this->warn('Set LEGACY_IMAGE_CANDIDATE_ORIGINS in your .env file.');
            $this->line('Format: host:pathPrefix,host:pathPrefix,...');
            $this->line('Example: LEGACY_IMAGE_CANDIDATE_ORIGINS="fa.dvtst.com:/wp/soluk,soluk.com.sa:/new,soluk.com.sa:,soluk.sa:"');

            return self::FAILURE;
        }

        $this->info('Candidate origins (in order):');
        foreach ($origins as $i => $origin) {
            $prefix = $origin['pathPrefix'] === '' ? '(root)' : $origin['pathPrefix'];
            $this->line("  " . ($i + 1) . ". {$origin['host']}{$prefix}");
        }
        $this->newLine();

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

            $newBody = $this->localizeImages($body, $origins);

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

    /**
     * Parse LEGACY_IMAGE_CANDIDATE_ORIGINS config into structured array.
     *
     * @return array<int, array{host: string, pathPrefix: string}>
     */
    private function parseCandidateOrigins(): array
    {
        $raw = (array) config('legacy_import.image_candidate_origins', []);
        $origins = [];

        foreach ($raw as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            // Format: host:pathPrefix  (e.g. "fa.dvtst.com:/wp/soluk" or "soluk.sa:")
            $colonPos = strpos($entry, ':');
            if ($colonPos === false) {
                // No colon = just a host with no prefix
                $origins[] = ['host' => $entry, 'pathPrefix' => ''];
                continue;
            }

            $host = substr($entry, 0, $colonPos);
            $pathPrefix = rtrim(substr($entry, $colonPos + 1), '/');

            if ($host === '') {
                continue;
            }

            $origins[] = ['host' => $host, 'pathPrefix' => $pathPrefix];
        }

        return $origins;
    }

    /**
     * @param array<int, array{host: string, pathPrefix: string}> $origins
     */
    private function localizeImages(string $html, array $origins): string
    {
        return (string) preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            function (array $matches) use ($origins): string {
                $prefix = $matches[1] ?? '';
                $src = trim((string) ($matches[2] ?? ''));
                $suffix = $matches[3] ?? '';

                if ($src === '') {
                    return $prefix . $src . $suffix;
                }

                if ($this->isLocalUrl($src)) {
                    $this->skipped++;
                    return $prefix . $src . $suffix;
                }

                $src = $this->unwrapProxyUrl($src);

                if (! filter_var($src, FILTER_VALIDATE_URL)) {
                    return $prefix . $src . $suffix;
                }

                $localUrl = $this->downloadWithFallbacks($src, $origins);

                if ($localUrl !== null) {
                    return $prefix . $localUrl . $suffix;
                }

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

        if (str_starts_with($url, '/storage/')) {
            return true;
        }

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

    /**
     * @param array<int, array{host: string, pathPrefix: string}> $origins
     */
    private function downloadWithFallbacks(string $originalUrl, array $origins): ?string
    {
        if (isset($this->cache[$originalUrl])) {
            $cached = $this->cache[$originalUrl];
            return $cached === '' ? null : $cached;
        }

        $candidates = $this->buildCandidateUrls($originalUrl, $origins);

        foreach ($candidates as $candidateUrl) {
            $result = $this->downloadImage($candidateUrl, $originalUrl);
            if ($result !== null) {
                $this->cache[$originalUrl] = $result;
                return $result;
            }
        }

        $this->failed++;
        $this->cache[$originalUrl] = '';
        $this->newLine();
        $this->error("  ✗ All candidates failed for: {$originalUrl}");

        return null;
    }

    /**
     * @param array<int, array{host: string, pathPrefix: string}> $origins
     * @return array<int, string>
     */
    private function buildCandidateUrls(string $originalUrl, array $origins): array
    {
        $parts = parse_url($originalUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $originalPath = $parts['path'] ?? '';

        $uploadsPath = $this->extractUploadsPath($originalPath);

        // Always try the original URL first
        $candidates = [$originalUrl];

        if ($uploadsPath !== null) {
            foreach ($origins as $origin) {
                $url = $scheme . '://' . $origin['host'] . $origin['pathPrefix'] . '/wp-content/uploads/' . $uploadsPath;
                if (! in_array($url, $candidates, true)) {
                    $candidates[] = $url;
                }
            }
        }

        return $candidates;
    }

    /**
     * Extract the portion after wp-content/uploads/ from a path.
     */
    private function extractUploadsPath(string $path): ?string
    {
        $marker = 'wp-content/uploads/';
        $pos = strpos($path, $marker);

        if ($pos === false) {
            return null;
        }

        return substr($path, $pos + strlen($marker));
    }

    private function downloadImage(string $url, string $cacheKey): ?string
    {
        $extension = $this->guessExtensionFromUrl($url);
        $relativePath = 'imports/inline-images/' . date('Y/m') . '/' . sha1($cacheKey) . '.' . $extension;

        if (Storage::disk('public')->exists($relativePath)) {
            $localUrl = Storage::disk('public')->url($relativePath);
            $this->skipped++;
            return $localUrl;
        }

        try {
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->retry(2, 500)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'image/*,*/*;q=0.8',
                    'Referer' => $this->refererFromUrl($url),
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if (! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $extension = $this->extensionFromResponse($url, $contentType);
        $relativePath = 'imports/inline-images/' . date('Y/m') . '/' . sha1($cacheKey) . '.' . $extension;

        Storage::disk('public')->put($relativePath, $response->body());

        $localUrl = Storage::disk('public')->url($relativePath);
        $this->downloaded++;

        $this->newLine();
        $this->info("  ↓ Downloaded: {$url}");

        return $localUrl;
    }

    private function refererFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        return $host === '' ? '' : $scheme . '://' . $host . '/';
    }

    private function guessExtensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true) ? $ext : 'jpg';
    }

    private function extensionFromResponse(string $url, string $contentType): string
    {
        $ext = $this->guessExtensionFromUrl($url);
        if ($ext !== 'jpg' || str_contains(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.jpg')) {
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

<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InlineArticleImageService
{
    /** @var array<string, string> */
    private array $resolvedUrlCache = [];

    public function localizeForArticle(Article $article, string $html): string
    {
        if ($html === '' || ! str_contains($html, '<img')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            function (array $matches) use ($article): string {
                $prefix = $matches[1] ?? '';
                $src = trim((string) ($matches[2] ?? ''));
                $suffix = $matches[3] ?? '';

                if ($src === '' || ! filter_var($src, FILTER_VALIDATE_URL)) {
                    return $prefix . $src . $suffix;
                }

                $normalized = $this->normalizeUrl($src);
                $localUrl = $this->downloadToMedia($article, $normalized);

                return $prefix . ($localUrl ?? $normalized) . $suffix;
            },
            $html,
        );
    }

    private function normalizeUrl(string $url): string
    {
        $url = $this->unwrapProxyUrl($url);
        $url = $this->fixDuplicatedAbsoluteUrl($url);
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

    private function downloadToMedia(Article $article, string $url): ?string
    {
        if (isset($this->resolvedUrlCache[$url])) {
            return $this->resolvedUrlCache[$url];
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->retry(2, 250)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                    'Accept' => 'image/*,*/*;q=0.8',
                    'Referer' => $this->refererFromUrl($url),
                ])
                ->get($url);
        } catch (\Throwable) {
            $this->resolvedUrlCache[$url] = '';
            return null;
        }

        if (! $response->successful()) {
            $this->resolvedUrlCache[$url] = '';
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if (! str_starts_with($contentType, 'image/')) {
            $this->resolvedUrlCache[$url] = '';
            return null;
        }

        $existing = $article->media()
            ->where('collection_name', 'content-images')
            ->where('custom_properties->source_url', $url)
            ->first();

        if ($existing) {
            $this->resolvedUrlCache[$url] = $existing->getUrl();
            return $this->resolvedUrlCache[$url];
        }

        $extension = $this->extensionFromUrlOrType($url, $contentType);
        $fileName = 'inline-' . sha1($url) . '.' . $extension;

        $media = $article
            ->addMediaFromString($response->body())
            ->usingFileName($fileName)
            ->withCustomProperties(['source_url' => $url])
            ->toMediaCollection('content-images');

        $this->resolvedUrlCache[$url] = $media->getUrl();

        return $this->resolvedUrlCache[$url];
    }

    private function refererFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        return $host === '' ? '' : $scheme . '://' . $host . '/';
    }

    private function extensionFromUrlOrType(string $url, string $contentType): string
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

    private function fixDuplicatedAbsoluteUrl(string $value): string
    {
        if (! str_contains($value, 'http://') && ! str_contains($value, 'https://')) {
            return $value;
        }

        $firstSchemePos = stripos($value, '://');
        if ($firstSchemePos !== false) {
            $secondHttp = stripos($value, 'http://', $firstSchemePos + 3);
            $secondHttps = stripos($value, 'https://', $firstSchemePos + 3);
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
}

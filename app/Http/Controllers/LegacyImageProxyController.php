<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class LegacyImageProxyController extends Controller
{
    public function __invoke(Request $request)
    {
        $sourceUrl = (string) $request->query('url', '');

        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        $scheme = parse_url($sourceUrl, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            abort(404);
        }

        $hash = sha1($sourceUrl);
        $directory = 'proxy-images/' . substr($hash, 0, 2);
        $baseName = $hash;

        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'] as $ext) {
            $candidate = $directory . '/' . $baseName . '.' . $ext;
            if (Storage::disk('public')->exists($candidate)) {
                return response(Storage::disk('public')->get($candidate), 200, [
                    'Content-Type' => $this->mimeTypeFromExt($ext),
                    'Cache-Control' => 'public, max-age=2592000',
                ]);
            }
        }

        $response = null;
        foreach ($this->candidateUrls($sourceUrl) as $candidateUrl) {
            $attempt = $this->safeFetch($candidateUrl);
            if ($attempt && $attempt->successful()) {
                $response = $attempt;
                $sourceUrl = $candidateUrl;
                break;
            }
        }

        if (! $response) {
            // Dynamic fallback: let browser try the original source directly.
            return redirect()->away($sourceUrl, Response::HTTP_FOUND);
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type', ''))[0]));
        if (! str_starts_with($contentType, 'image/')) {
            abort(404);
        }

        $ext = $this->extensionFromMime($contentType);
        $path = $directory . '/' . $baseName . '.' . $ext;
        Storage::disk('public')->put($path, $response->body());

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=2592000',
        ]);
    }

    /** @return array<int, string> */
    private function candidateUrls(string $sourceUrl): array
    {
        $urls = [$this->fixDuplicatedAbsoluteUrl($sourceUrl)];

        foreach ($urls as $url) {
            $parts = parse_url($url);
            $host = $parts['host'] ?? null;
            if (! is_string($host) || $host === '') {
                continue;
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

                $urls[] = $scheme . '://' . $to . $path . $query . $fragment;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function safeFetch(string $url): ?\Illuminate\Http\Client\Response
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?? 'https');
        $referer = $host !== '' ? ($scheme . '://' . $host . '/') : null;

        try {
            return Http::connectTimeout(5)
                ->timeout(20)
                ->retry(2, 250)
                ->withOptions(['verify' => false])
                ->withHeaders(array_filter([
                    'User-Agent' => 'Mozilla/5.0',
                    'Accept' => 'image/*,*/*;q=0.8',
                    'Referer' => $referer,
                ]))
                ->get($url);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fixDuplicatedAbsoluteUrl(string $value): string
    {
        if (! str_contains($value, 'http://') && ! str_contains($value, 'https://')) {
            return $value;
        }

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

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }

    private function mimeTypeFromExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}

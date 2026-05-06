<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        $host = (string) (parse_url($sourceUrl, PHP_URL_HOST) ?? '');
        $scheme = (string) (parse_url($sourceUrl, PHP_URL_SCHEME) ?? 'https');
        $referer = $host !== '' ? ($scheme . '://' . $host . '/') : null;

        $response = Http::timeout(20)
            ->withOptions(['verify' => false])
            ->withHeaders(array_filter([
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'image/*,*/*;q=0.8',
                'Referer' => $referer,
            ]))
            ->get($sourceUrl);

        if (! $response->successful()) {
            abort(404);
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


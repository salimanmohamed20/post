<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheInvalidationService
{
    public const VERSION_KEY = 'public_content_cache_version';

    public function currentVersion(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public function key(string $name, array $parts = []): string
    {
        $suffix = collect($parts)
            ->map(fn (mixed $part): string => str_replace([' ', '/', '\\'], '-', (string) $part))
            ->implode(':');

        return trim("public:v{$this->currentVersion()}:{$name}:{$suffix}", ':');
    }

    public function flushPublicContent(): void
    {
        Cache::forever(self::VERSION_KEY, $this->currentVersion() + 1);
    }
}

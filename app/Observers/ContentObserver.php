<?php

namespace App\Observers;

use App\Services\CacheInvalidationService;
use Illuminate\Database\Eloquent\Model;

class ContentObserver
{
    public function saved(Model $model): void
    {
        app(CacheInvalidationService::class)->flushPublicContent();
    }

    public function deleted(Model $model): void
    {
        app(CacheInvalidationService::class)->flushPublicContent();
    }
}

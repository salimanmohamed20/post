<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\CacheInvalidationService;
use App\Services\SeoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug, CacheInvalidationService $cache, SeoService $seo): View
    {
        $page = Cache::remember(
            $cache->key('page', [$slug]),
            now()->addMinutes(30),
            fn () => Page::query()->where('slug', $slug)->firstOrFail(),
        );

        return view('pages.show', [
            'page' => $page,
            'seo' => $seo->forPage($page),
        ]);
    }
}

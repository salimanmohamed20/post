<?php

namespace App\Http\Controllers;

use App\Services\ArticleService;
use App\Services\SeoService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(ArticleService $articles): View
    {
        return view('home', [
            'articles' => $articles->latest(),
            'seo' => [
                'title' => config('app.name'),
                'description' => 'Latest published articles.',
                'canonical' => route('home'),
            ],
        ]);
    }
}

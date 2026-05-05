<?php

namespace App\Http\Controllers;

use App\Services\ArticleService;
use App\Services\SeoService;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(ArticleService $articles): View
    {
        return view('articles.index', [
            'articles' => $articles->paginate(),
            'seo' => [
                'title' => 'Articles',
                'description' => 'Browse published articles.',
                'canonical' => route('articles.index'),
            ],
        ]);
    }

    public function show(string $slug, ArticleService $articles, SeoService $seo): View
    {
        $article = $articles->findPublishedBySlug($slug);

        return view('articles.show', [
            'article' => $article,
            'seo' => $seo->forArticle($article),
        ]);
    }
}

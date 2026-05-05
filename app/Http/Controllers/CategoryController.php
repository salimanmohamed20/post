<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ArticleService;
use App\Services\SeoService;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(string $slug, ArticleService $articles, SeoService $seo): View
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        return view('categories.show', [
            'category' => $category,
            'articles' => $articles->paginateByCategory($category),
            'seo' => $seo->forCategory($category),
        ]);
    }
}

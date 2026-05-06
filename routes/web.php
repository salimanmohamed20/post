<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyImageProxyController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->where('slug', '[^/]+')->name('articles.show');
Route::get('/categories/{slug}', [CategoryController::class, 'show'])->where('slug', '[^/]+')->name('categories.show');
Route::get('/pages/{slug}', [PageController::class, 'show'])->where('slug', '[^/]+')->name('pages.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/legacy-image-proxy', LegacyImageProxyController::class)->name('legacy-image-proxy');

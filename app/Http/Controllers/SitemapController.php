<?php

namespace App\Http\Controllers;

use App\Services\SeoService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(SeoService $seo): Response
    {
        return response()
            ->view('sitemap', ['urls' => $seo->sitemap()])
            ->header('Content-Type', 'application/xml');
    }
}

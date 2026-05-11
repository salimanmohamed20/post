<?php

use App\Jobs\ImportArticlesJob;
use App\Jobs\ImportCategoriesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('import:categories {path? : JSON path or local-disk path} {--now : Run immediately instead of queue}', function (?string $path = null) {
    $job = new ImportCategoriesJob($path);

    if ($this->option('now')) {
        app()->call([$job, 'handle']);
        $this->info('Categories import completed immediately.');

        return;
    }

    dispatch($job);
    $this->info('Categories import job dispatched to the database queue.');
})->purpose('Import categories from a legacy JSON source');

Artisan::command('import:articles {path? : JSON path or local-disk path} {--now : Run immediately instead of queue}', function (?string $path = null) {
    $job = new ImportArticlesJob($path);

    if ($this->option('now')) {
        app()->call([$job, 'handle']);
        $this->info('Articles import completed immediately.');

        return;
    }

    dispatch($job);
    $this->info('Articles import job dispatched to the database queue.');
})->purpose('Import articles from a legacy JSON source');

Artisan::command('import:all {categoriesPath? : Categories JSON path} {articlesPath? : Articles JSON path} {--now : Run immediately instead of queue}', function (?string $categoriesPath = null, ?string $articlesPath = null) {
    if ($this->option('now')) {
        app()->call([new ImportCategoriesJob($categoriesPath), 'handle']);
        app()->call([new ImportArticlesJob($articlesPath), 'handle']);
        $this->info('Categories and articles import completed immediately.');

        return;
    }

    Bus::chain([
        new ImportCategoriesJob($categoriesPath),
        new ImportArticlesJob($articlesPath),
    ])->dispatch();

    $this->info('Categories and articles import jobs dispatched to the database queue.');
})->purpose('Import categories first, then articles');

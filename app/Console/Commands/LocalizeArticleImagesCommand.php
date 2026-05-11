<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\InlineArticleImageService;
use Illuminate\Console\Command;

class LocalizeArticleImagesCommand extends Command
{
    protected $signature = 'articles:localize-images
                            {--article= : Localize images for a specific article ID}
                            {--dry-run : Show what would be changed without saving}';

    protected $description = 'Download external images in article body content to the media table and replace them with local URLs';

    public function handle(InlineArticleImageService $inlineImages): int
    {
        $query = Article::query();

        if ($articleId = $this->option('article')) {
            $query->where('id', $articleId);
        }

        $isDryRun = (bool) $this->option('dry-run');

        $articles = $query->get();
        $total = $articles->count();

        $this->info("Processing {$total} articles...");
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - no changes will be saved');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updatedCount = 0;

        foreach ($articles as $article) {
            $body = (string) $article->body;

            if ($body === '' || ! str_contains($body, '<img')) {
                $bar->advance();
                continue;
            }

            $newBody = $inlineImages->localizeForArticle($article, $body, ! $isDryRun);

            if ($newBody !== $body) {
                if (! $isDryRun) {
                    $article->forceFill(['body' => $newBody])->saveQuietly();
                }

                $updatedCount++;
                $this->newLine();
                $this->line("  Localized article #{$article->id} ({$article->slug})");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Done!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total articles', $total],
                ['Articles updated', $updatedCount],
            ],
        );

        return self::SUCCESS;
    }
}

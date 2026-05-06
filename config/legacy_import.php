<?php

return [
    'categories' => env('LEGACY_CATEGORIES_PATH'),
    'articles' => env('LEGACY_ARTICLES_PATH'),
    'category_tables' => ['categories', 'category'],
    'article_tables' => ['articles', 'article', 'posts', 'post'],
];

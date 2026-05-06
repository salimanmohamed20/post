<?php

return [
    'categories' => env('LEGACY_CATEGORIES_PATH'),
    'articles' => env('LEGACY_ARTICLES_PATH'),
    'wordpress_uploads_base_url' => env('LEGACY_WORDPRESS_UPLOADS_BASE_URL'),
    'legacy_image_host_fallbacks' => array_values(array_filter(array_map('trim', explode(',', (string) env('LEGACY_IMAGE_HOST_FALLBACKS', 'soluk.com.sa=>soluk.sa,soluk.com.sa=>www.soluk.sa'))))),
    'category_tables' => ['categories', 'category', 'wp_terms', 'terms', 'wp_term_taxonomy', 'term_taxonomy'],
    'article_tables' => ['articles', 'article', 'wp_posts', 'posts', 'post', 'wp_postmeta', 'postmeta', 'wp_term_relationships', 'term_relationships', 'wp_term_taxonomy', 'term_taxonomy'],
];

<?php

return [
    'categories' => env('LEGACY_CATEGORIES_PATH'),
    'articles' => env('LEGACY_ARTICLES_PATH'),
    'wordpress_uploads_base_url' => env('LEGACY_WORDPRESS_UPLOADS_BASE_URL'),
    'legacy_image_host_fallbacks' => array_values(array_filter(array_map('trim', explode(',', (string) env('LEGACY_IMAGE_HOST_FALLBACKS', 'soluk.com.sa=>soluk.sa,soluk.com.sa=>www.soluk.sa'))))),

    /*
    |--------------------------------------------------------------------------
    | Image Candidate Origins
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of host:pathPrefix pairs to try when downloading
    | images from external URLs. The command will extract the
    | wp-content/uploads/... portion and try each origin in order.
    |
    | Format: host:pathPrefix,host:pathPrefix,...
    | Example: fa.dvtst.com:/wp/soluk,soluk.com.sa:/new,soluk.com.sa:,soluk.sa:
    |
    | A trailing colon with no path means root (no prefix).
    |
    */
    'image_candidate_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('LEGACY_IMAGE_CANDIDATE_ORIGINS', ''))))),

    'category_tables' => ['categories', 'category', 'wp_terms', 'terms', 'wp_term_taxonomy', 'term_taxonomy'],
    'article_tables' => ['articles', 'article', 'wp_posts', 'posts', 'post', 'wp_postmeta', 'postmeta', 'wp_term_relationships', 'term_relationships', 'wp_term_taxonomy', 'term_taxonomy'],
];

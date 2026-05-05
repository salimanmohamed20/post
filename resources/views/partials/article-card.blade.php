@php($image = $article->getFirstMedia('images'))

<article class="article-card">
    @if($image)
        <a href="{{ route('articles.show', $article->slug) }}" class="article-card__image">
            <img src="{{ $image->getUrl('card') }}" alt="{{ $article->title }}">
        </a>
    @endif
    <div class="article-card__body">
        <div class="meta">
            <a href="{{ route('categories.show', $article->category->slug) }}">{{ $article->category->name }}</a>
            <span>{{ optional($article->published_at)->format('M d, Y') }}</span>
        </div>
        <h2><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h2>
        @if($article->excerpt)
            <p>{{ $article->excerpt }}</p>
        @endif
    </div>
</article>

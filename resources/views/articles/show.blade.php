@extends('layouts.app')

@section('content')
    <article class="article-detail">
        <div class="meta">
            <a href="{{ route('categories.show', $article->category->slug) }}">{{ $article->category->name }}</a>
            <span>{{ optional($article->published_at)->format('M d, Y') }}</span>
        </div>
        <h1>{{ $article->title }}</h1>
        @if($article->excerpt)
            <p class="lead">{{ $article->excerpt }}</p>
        @endif

        @if($article->getMedia('images')->isNotEmpty())
            <div class="image-gallery">
                @foreach($article->getMedia('images')->sortBy('order_column') as $image)
                    <img src="{{ $image->getUrl() }}" alt="{{ $article->title }}">
                @endforeach
            </div>
        @endif

        <div class="content">
            {!! $article->body !!}
        </div>

        <script>
            document.querySelectorAll('.content img').forEach((img) => {
                img.referrerPolicy = 'no-referrer';
                img.onerror = () => {
                    const tried = Number(img.dataset.fallbackTried ?? '0');
                    const src = img.getAttribute('src') || '';

                    if (src.includes('soluk.com.sa') && tried === 0) {
                        img.dataset.fallbackTried = '1';
                        img.src = src.replace('soluk.com.sa', 'soluk.sa');
                        return;
                    }

                    if (src.includes('soluk.sa') && !src.includes('www.soluk.sa') && tried === 1) {
                        img.dataset.fallbackTried = '2';
                        img.src = src.replace('soluk.sa', 'www.soluk.sa');
                        return;
                    }

                    img.style.display = 'none';
                };
            });
        </script>
    </article>
@endsection

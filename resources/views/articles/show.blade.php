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
    </article>
@endsection

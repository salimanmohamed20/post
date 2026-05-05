@extends('layouts.app')

@section('content')
    <section class="section-heading">
        <h1>Latest Articles</h1>
        <a href="{{ route('articles.index') }}">View all</a>
    </section>

    <section class="article-grid">
        @forelse($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="empty">No published articles yet.</p>
        @endforelse
    </section>
@endsection

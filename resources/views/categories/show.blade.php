@extends('layouts.app')

@section('content')
    <section class="section-heading">
        <h1>{{ $category->name }}</h1>
    </section>

    <section class="article-grid">
        @forelse($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="empty">No published articles in this category yet.</p>
        @endforelse
    </section>

    {{ $articles->links() }}
@endsection

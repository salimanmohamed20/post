@extends('layouts.app')

@section('content')
    <section class="section-heading">
        <h1>Articles</h1>
    </section>

    <section class="article-grid">
        @forelse($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="empty">No published articles yet.</p>
        @endforelse
    </section>

    {{ $articles->links() }}
@endsection

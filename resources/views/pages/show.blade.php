@extends('layouts.app')

@section('content')
    <article class="article-detail">
        <h1>{{ $page->title }}</h1>
        <div class="content">
            {!! $page->body !!}
        </div>
    </article>
@endsection

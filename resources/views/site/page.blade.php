@extends('site.layouts.app')

@section('title', $post->title . ' | ' . ($site->long_name ?? 'Notas'))

@section('content')
    <article class="article-content">
        <header>
            <h1>{{ $post->title }}</h1>
        </header>

        {!! \App\Support\PostBodyRenderer::render($post->body) !!}
    </article>
@endsection

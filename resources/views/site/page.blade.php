@extends('site.layouts.app')

@section('title', $post->title)
@section('description', $post->getExcerpt(30))

@section('jsonld')
@php
    $pageLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $post->title,
        'description' => $post->getExcerpt(30),
        'url' => $site->publicUrl($canonicalPath ?? $post->slug),
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<script type="application/ld+json">{!! json_encode($pageLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endsection

@section('content')
    <article class="article-content">
        <header>
            <h1>{{ $post->title }}</h1>
        </header>

        {!! \App\Support\PostBodyRenderer::render($post->body) !!}
    </article>
@endsection

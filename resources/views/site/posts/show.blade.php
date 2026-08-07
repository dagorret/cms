@extends('site.layouts.app')

@section('title', $post->title)
@section('description', $post->getExcerpt(30))
@section('og_type', 'article')
@section('article_published_time', (string) (($post->published_at ?? $post->created_at)?->toAtomString()))
@section('article_modified_time', (string) ($post->updated_at?->toAtomString()))
@if($post->category)
@section('article_section', $post->category->name)
@endif

@section('jsonld')
@php
    $articleUrl = $site->publicUrl($canonicalPath ?? $post->slug);
    $articleLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->getExcerpt(30),
        'datePublished' => ($post->published_at ?? $post->created_at)?->toAtomString(),
        'dateModified' => $post->updated_at?->toAtomString(),
        'author' => ['@type' => 'Person', 'name' => 'Carlos Dagorret'],
        'articleSection' => $post->category->name ?? null,
        'url' => $articleUrl,
        'mainEntityOfPage' => $articleUrl,
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<script type="application/ld+json">{!! json_encode($articleLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endsection

@section('content')
@php
    $previousPost = $previousPost ?? null;
    $nextPost = $nextPost ?? null;
@endphp
<article class="min-w-0 w-full border border-[#d8d0c3] bg-[#fffaf2] p-[clamp(20px,4vw,54px)] dark:border-[#4a4640] dark:bg-[#211f1c]">
<header class="mb-8 border-b border-[#d8d0c3] pb-6 dark:border-[#4a4640]">
<p class="font-sans text-[.86rem] text-[#66615a] dark:text-[#aaa298]">
<a href="{{ $subdirUrl }}/" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">← Volver a la bitácora</a>
</p>

<div class="mt-6 font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c]">
{{ $post->type === \App\Models\Post::TYPE_PAGE ? 'Página' : 'Post' }}
@if($post->category)
 · <a href="{{ $subdirUrl }}/category/{{ $post->category->slug }}/">{{ $post->category->name }}</a>
@endif
</div>

<h1 class="my-5 font-serif text-[clamp(2rem,4vw,4rem)] font-bold leading-[.98] tracking-[-.055em] text-[#171717] dark:text-[#f5f0e7]">
{{ $post->title }}
</h1>

<div class="font-sans text-[.86rem] leading-6 text-[#66615a] dark:text-[#aaa298]">
<time datetime="{{ $post->created_at->format('Y-m-d') }}">{{ $post->created_at->format('Y-m-d') }}</time>
@if(!empty($post->keywords))
<span> · {{ $post->keywords }}</span>
@endif
</div>
</header>

@include('site.posts.partials.content', ['renderedBody' => $post->renderedBodyHtml()])
</article>

@if($previousPost || $nextPost)
<nav aria-label="Navegación cronológica entre posts" class="mt-8 grid grid-cols-1 gap-4 border-y border-[#d8d0c3] py-6 font-sans dark:border-[#4a4640] md:grid-cols-2">
@if($previousPost)
<a href="{{ $previousPost['url'] }}" rel="prev" class="group block min-w-0 border border-[#d8d0c3] bg-[#fffaf2] px-5 py-4 transition-colors hover:border-[#0f4c5c] hover:bg-[#f5eddb] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c5c] dark:border-[#4a4640] dark:bg-[#211f1c] dark:hover:border-[#8fc3cf] dark:hover:bg-[#29251f] dark:focus-visible:outline-[#8fc3cf]">
<span class="block text-[.72rem] font-bold uppercase tracking-[.14em] text-[#66615a] group-hover:text-[#0f4c5c] dark:text-[#aaa298] dark:group-hover:text-[#8fc3cf]">← Anterior</span>
<span class="mt-2 block font-serif text-lg font-bold leading-snug text-[#171717] dark:text-[#f5f0e7]">{{ $previousPost['title'] }}</span>
</a>
@endif

@if($nextPost)
<a href="{{ $nextPost['url'] }}" rel="next" class="group block min-w-0 border border-[#d8d0c3] bg-[#fffaf2] px-5 py-4 transition-colors hover:border-[#0f4c5c] hover:bg-[#f5eddb] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c5c] dark:border-[#4a4640] dark:bg-[#211f1c] dark:hover:border-[#8fc3cf] dark:hover:bg-[#29251f] dark:focus-visible:outline-[#8fc3cf] md:col-start-2 md:text-right">
<span class="block text-[.72rem] font-bold uppercase tracking-[.14em] text-[#66615a] group-hover:text-[#0f4c5c] dark:text-[#aaa298] dark:group-hover:text-[#8fc3cf]">Siguiente →</span>
<span class="mt-2 block font-serif text-lg font-bold leading-snug text-[#171717] dark:text-[#f5f0e7]">{{ $nextPost['title'] }}</span>
</a>
@endif
</nav>
@endif
@endsection

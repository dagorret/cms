@extends('site.layouts.app')

@section('title', $post->title)

@section('content')
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
@endsection

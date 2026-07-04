@extends('site.layouts.app')

@section('title', $post->title)

@section('content')
    <article class="article-body border border-[#d8d0c3] bg-[#fffaf2] p-[clamp(24px,4vw,54px)]">
        <header class="mb-8 border-b border-[#d8d0c3] pb-6">
            <p class="font-sans text-[.86rem] text-[#66615a]">
                <a href="{{ $subdirUrl }}/" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">← Volver a la bitácora</a>
            </p>

            <div class="kicker mt-6 font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c]">
                {{ config('static_cms.types.' . ($post->type ?? 'post'), ucfirst($post->type ?? 'post')) }}
            </div>

            <h1 class="my-5 font-serif text-[clamp(2rem,4vw,4rem)] font-bold leading-[.98] tracking-[-.055em] text-[#171717]">
                {{ $post->title }}
            </h1>

            <div class="meta font-sans text-[.86rem] leading-6 text-[#66615a]">
                <time datetime="{{ $post->created_at->format('Y-m-d') }}">{{ $post->created_at->format('Y-m-d') }}</time>
                @if(!empty($post->keywords))
                    <span> · {{ $post->keywords }}</span>
                @endif
            </div>
        </header>

        <div class="article-content text-[1.12rem] leading-[1.68] text-[#171717] [&_blockquote]:my-7 [&_blockquote]:border-l-[5px] [&_blockquote]:border-[#8a6f2a] [&_blockquote]:bg-[#f5eddb] [&_blockquote]:px-5 [&_blockquote]:py-4 [&_blockquote]:italic [&_h2]:mt-[2.1em] [&_h2]:text-[1.55rem] [&_h2]:font-bold [&_h2]:leading-[1.15] [&_h2]:tracking-[-.02em] [&_p]:my-[1.25em] [&_pre]:overflow-x-auto [&_pre]:bg-[#24211d] [&_pre]:p-[18px] [&_pre]:text-[#f7f3eb]">
            {!! $post->body !!}
        </div>
    </article>
@endsection

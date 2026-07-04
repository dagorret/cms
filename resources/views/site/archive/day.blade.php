@extends('site.layouts.app')

@section('title', 'Publicaciones del ' . $day . '/' . $month . '/' . $year)

@section('content')
    <section class="article-list">
        <header class="mb-8">
            <p class="kicker">Archivo diario</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717]">Publicaciones del {{ $day }}/{{ $month }}/{{ $year }}</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333]">{{ $totalPosts }} publicaciones registradas en esta fecha.</p>
        </header>

        <ol>
            @foreach($posts as $post)
                <li class="archive-item">
                    <a href="{{ $subdirUrl }}/{{ $post->slug }}/" class="flex items-center justify-between gap-6 decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                        <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">{{ $post->title }}</span>
                        <time datetime="{{ $post->created_at->format('Y-m-d') }}" class="meta">{{ $post->created_at->format('Y-m-d') }}</time>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

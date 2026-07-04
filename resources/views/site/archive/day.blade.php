@extends('site.layouts.app')

@section('title', 'Publicaciones del ' . $day . '/' . $month . '/' . $year)

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-12">
        <header class="mb-10 border-b border-slate-800/80 pb-6">
            <p class="mb-2 text-sm font-medium uppercase tracking-wide text-sky-400">Archivo diario</p>
            <h1 class="text-3xl font-bold text-slate-100 mb-6">Publicaciones del {{ $day }}/{{ $month }}/{{ $year }}</h1>
            <p class="text-base leading-7 text-slate-400">{{ $totalPosts }} publicaciones registradas en esta fecha.</p>
        </header>

        <div class="space-y-8">
            @foreach($posts as $post)
                <article class="border-b border-slate-800/60 pb-8">
                    <h2>
                        <a href="{{ $subdirUrl }}/{{ $post->slug }}/" class="text-xl font-semibold text-sky-400 hover:text-sky-300 transition-colors">{{ $post->title }}</a>
                    </h2>
                    <div class="flex items-center text-sm text-slate-400 gap-2 mt-1">
                        <span>📅</span>
                        <time datetime="{{ $post->created_at->format('Y-m-d') }}">{{ $post->created_at->format('d/m/Y') }}</time>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection

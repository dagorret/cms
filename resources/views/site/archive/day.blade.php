@extends('site.layouts.app')

@section('title', 'Publicaciones del ' . $day . '/' . $month . '/' . $year)

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-12">
        <header class="mb-10 border-b border-slate-800/80 pb-6">
            <p class="mb-2 text-sm font-medium uppercase tracking-wide text-sky-400">Archivo diario</p>
            <h1 class="text-3xl font-bold text-slate-100 mb-6">Publicaciones del {{ $day }}/{{ $month }}/{{ $year }}</h1>
            <p class="text-base leading-7 text-slate-400">{{ $totalPosts }} publicaciones registradas en esta fecha.</p>
        </header>

        <ol class="overflow-hidden border-t border-slate-800/60">
            @foreach($posts as $post)
                <li class="border-b border-slate-800/60">
                    <a href="{{ $subdirUrl }}/{{ $post->slug }}/" class="flex items-center justify-between gap-6 px-4 py-5 text-slate-200 transition-colors hover:bg-slate-800/50 hover:text-sky-300">
                        <span class="text-xl font-semibold">{{ $post->title }}</span>
                        <span class="flex items-center gap-2 text-sm text-slate-400">
                            <span>📅</span>
                            <time datetime="{{ $post->created_at->format('Y-m-d') }}">{{ $post->created_at->format('d/m/Y') }}</time>
                        </span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

@extends('site.layouts.app')

@section('title', $post->title)

@section('content')
    <article class="mx-auto max-w-3xl px-6 py-12">
        <header class="mb-10 border-b border-slate-800/80 pb-6">
            <p>
                <a href="{{ $subdirUrl }}/">← Volver a la bitácora</a>
            </p>

            <h1 class="text-3xl font-bold text-slate-100 mb-6">{{ $post->title }}</h1>

            <div class="meta flex items-center text-sm text-slate-400 gap-2 mt-1">
                <span>📅</span>
                <time datetime="{{ $post->created_at->format('Y-m-d') }}">{{ $post->created_at->format('d/m/Y') }}</time>

                @if(!empty($post->type))
                    <span>📂</span>
                    <span class="badge">{{ $post->type }}</span>
                @endif
            </div>
        </header>

        <div class="prose prose-invert prose-slate max-w-none text-slate-200">
            {!! $post->body !!}
        </div>

        <footer class="site-footer">
            <p class="meta">Etiquetas: {{ $post->keywords ?? 'Ninguna' }}</p>
        </footer>
    </article>
@endsection

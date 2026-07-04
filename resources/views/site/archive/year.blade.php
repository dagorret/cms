@extends('site.layouts.app')

@section('title', 'Archivo: ' . $year)

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-12">
        <header class="mb-10 border-b border-slate-800/80 pb-6">
            <p class="mb-2 text-sm font-medium uppercase tracking-wide text-sky-400">Archivo anual</p>
            <h1 class="text-3xl font-bold text-slate-100 mb-6">Archivo: {{ $year }}</h1>
            <p class="text-base leading-7 text-slate-400">Meses con publicaciones registradas durante este año.</p>
        </header>

        <ol class="grid gap-3 sm:grid-cols-2">
            @foreach($months as $month)
                <li>
                    <a href="{{ $subdirUrl }}/archive/{{ $year }}/{{ $month }}/index.html" class="block border border-slate-800/60 px-5 py-4 text-slate-200 transition-colors hover:border-sky-500/40 hover:bg-slate-800/50 hover:text-sky-300">
                        <span class="block text-lg font-semibold">Mes {{ $month }}</span>
                        <span class="mt-1 block text-sm text-slate-500">Consultar días disponibles</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

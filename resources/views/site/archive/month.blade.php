@extends('site.layouts.app')

@section('title', 'Archivo: ' . $month . '/' . $year)

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-12">
        <header class="mb-10 border-b border-slate-800/80 pb-6">
            <p class="mb-2 text-sm font-medium uppercase tracking-wide text-sky-400">Archivo mensual</p>
            <h1 class="text-3xl font-bold text-slate-100 mb-6">Archivo: {{ $month }} / {{ $year }}</h1>
            <p class="text-base leading-7 text-slate-400">Días con actividad editorial publicada.</p>
        </header>

        <ol class="overflow-hidden border-t border-slate-800/60">
            @foreach($days as $day => $count)
                <li class="border-b border-slate-800/60">
                    <a href="{{ $subdirUrl }}/archive/{{ $year }}/{{ $month }}/{{ $day }}/index.html" class="flex items-center justify-between px-4 py-5 text-slate-200 transition-colors hover:bg-slate-800/50 hover:text-sky-300">
                        <span class="text-xl font-semibold">Día {{ $day }}</span>
                        <span class="text-slate-400 text-sm">🗂️ {{ $count }} publicaciones</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

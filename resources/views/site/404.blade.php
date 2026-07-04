@extends('site.layouts.app')

@section('title', 'Página no encontrada')

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-16">
        <header class="mb-10 border-b border-slate-800/80 pb-6">
            <p class="mb-2 text-sm font-medium uppercase tracking-wide text-sky-400">Error 404</p>
            <h1 class="text-3xl font-bold text-slate-100 mb-6">Página no encontrada</h1>
            <p class="text-base leading-7 text-slate-400">La ruta solicitada no existe o fue movida dentro del archivo estático.</p>
        </header>

        <nav class="overflow-hidden border-t border-slate-800/60">
            <a href="{{ $subdirUrl }}/" class="flex items-center justify-between px-4 py-5 text-slate-200 transition-colors hover:bg-slate-800/50 hover:text-sky-300 border-b border-slate-800/60">
                <span class="text-xl font-semibold">Volver al inicio</span>
                <span class="text-sm text-slate-500">Portada</span>
            </a>
            <a href="{{ $subdirUrl }}/archive/index.html" class="flex items-center justify-between px-4 py-5 text-slate-200 transition-colors hover:bg-slate-800/50 hover:text-sky-300 border-b border-slate-800/60">
                <span class="text-xl font-semibold">Archivo Histórico</span>
                <span class="text-sm text-slate-500">Explorar fechas</span>
            </a>
        </nav>

        @include('site.partials.footer')
    </section>
@endsection

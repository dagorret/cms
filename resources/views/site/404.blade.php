@extends('site.layouts.app')

@section('title', 'Página no encontrada')

@section('content')
    <section class="article-list">
        <header class="mb-8">
            <p class="kicker">Error 404</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717]">Página no encontrada</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333]">La ruta solicitada no existe o fue movida dentro del archivo estático.</p>
        </header>

        <nav>
            <a href="{{ $subdirUrl }}/" class="archive-item flex items-center justify-between gap-6 decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">Volver al inicio</span>
                <span class="meta">Portada</span>
            </a>
            <a href="{{ $subdirUrl }}/archive/index.html" class="archive-item flex items-center justify-between gap-6 decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">Archivo Histórico</span>
                <span class="meta">Explorar fechas</span>
            </a>
        </nav>
    </section>
@endsection

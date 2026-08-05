@extends('site.layouts.app')

@section('title', 'Página no encontrada')

@section('content')
    <section class="mt-0 border-t-[3px] border-[#171717] pt-[18px] dark:border-[#e8e1d5]">
        <header class="mb-8">
            <p class="font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c]">Error 404</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717] dark:text-[#f5f0e7]">Página no encontrada</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333] dark:text-[#cbc4b8]">La ruta solicitada no existe o fue movida dentro del archivo estático.</p>
        </header>

        <nav>
            <a href="{{ $subdirUrl }}/" class="flex items-center justify-between gap-6 border-b border-[#d8d0c3] py-[18px] decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c] max-[900px]:items-start dark:border-[#4a4640] dark:hover:text-[#8fc3cf]">
                <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">Volver al inicio</span>
                <span class="font-sans text-[.86rem] text-[#66615a] dark:text-[#aaa298]">Portada</span>
            </a>
            <a href="{{ $subdirUrl }}/archive/index.html" class="flex items-center justify-between gap-6 border-b border-[#d8d0c3] py-[18px] decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c] max-[900px]:items-start dark:border-[#4a4640] dark:hover:text-[#8fc3cf]">
                <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">Archivo Histórico</span>
                <span class="font-sans text-[.86rem] text-[#66615a] dark:text-[#aaa298]">Explorar fechas</span>
            </a>
        </nav>
    </section>
@endsection

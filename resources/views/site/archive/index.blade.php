@extends('site.layouts.app')

@section('title', 'Archivo Histórico')
@section('description', 'Exploración cronológica de las publicaciones de ' . ($site->long_name ?? 'el sitio') . '.')

@section('content')
    <section class="mt-0 border-t-[3px] border-[#171717] pt-[18px] dark:border-[#e8e1d5]">
        <header class="mb-8">
            <p class="font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c]">Archivo</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717] dark:text-[#f5f0e7]">Archivo Histórico</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333] dark:text-[#cbc4b8]">Exploración cronológica de las publicaciones disponibles.</p>
        </header>

        <ol>
            @foreach($years as $year)
                <li class="border-b border-[#d8d0c3] py-[18px] dark:border-[#4a4640]">
                    <a href="{{ $subdirUrl }}/archive/{{ $year }}/index.html" class="flex items-center justify-between gap-6 decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c] max-[900px]:items-start dark:hover:text-[#8fc3cf]">
                        <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">{{ $year }}</span>
                        <span class="font-sans text-[.86rem] text-[#66615a] dark:text-[#aaa298]">Ver meses</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

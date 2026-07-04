@extends('site.layouts.app')

@section('title', 'Archivo Histórico')

@section('content')
    <section class="article-list">
        <header class="mb-8">
            <p class="kicker">Archivo</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717]">Archivo Histórico</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333]">Exploración cronológica de las publicaciones disponibles.</p>
        </header>

        <ol>
            @foreach($years as $year)
                <li class="archive-item">
                    <a href="{{ $subdirUrl }}/archive/{{ $year }}/index.html" class="flex items-center justify-between gap-6 decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                        <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">{{ $year }}</span>
                        <span class="meta">Ver meses</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

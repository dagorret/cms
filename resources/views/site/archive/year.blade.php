@extends('site.layouts.app')

@section('title', 'Archivo: ' . $year)

@section('content')
    <section class="article-list">
        <header class="mb-8">
            <p class="kicker">Archivo anual</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717]">Archivo: {{ $year }}</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333]">Meses con publicaciones registradas durante este año.</p>
        </header>

        <ol>
            @foreach($months as $month)
                <li class="archive-item">
                    <a href="{{ $subdirUrl }}/archive/{{ $year }}/{{ $month }}/index.html" class="flex items-center justify-between gap-6 decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                        <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">Mes {{ $month }}</span>
                        <span class="meta">Ver días</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

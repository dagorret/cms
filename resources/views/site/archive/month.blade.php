@extends('site.layouts.app')

@section('title', 'Archivo: ' . $month . '/' . $year)

@section('content')
    <section class="article-list">
        <header class="mb-8">
            <p class="kicker">Archivo mensual</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717]">Archivo: {{ $month }} / {{ $year }}</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333]">Días con actividad editorial publicada.</p>
        </header>

        <ol>
            @foreach($days as $day => $count)
                <li class="archive-item">
                    <a href="{{ $subdirUrl }}/archive/{{ $year }}/{{ $month }}/{{ $day }}/index.html" class="flex items-center justify-between gap-6 decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c]">
                        <span class="font-serif text-[1.55rem] font-bold leading-[1.12] tracking-[-.03em]">Día {{ $day }}</span>
                        <span class="meta">🗂️ {{ $count }} publicaciones</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endsection

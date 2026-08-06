@extends('site.layouts.app')

@section('title', 'Publicaciones del ' . $day . '/' . $month . '/' . $year)

@section('content')
    <section class="mt-0 border-t-[3px] border-[#171717] pt-[18px] dark:border-[#e8e1d5]">
        <header class="mb-8">
            <p class="font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c]">Archivo diario</p>
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold leading-[1.02] tracking-[-.045em] text-[#171717] dark:text-[#f5f0e7]">Publicaciones del {{ $day }}/{{ $month }}/{{ $year }}</h1>
            <p class="text-[1.05rem] leading-[1.68] text-[#333333] dark:text-[#cbc4b8]">{{ $totalPosts }} publicaciones registradas en esta fecha.</p>
        </header>

        <ol>{!! $postsStreamMarker ?? '' !!}
            @foreach($posts as $post)
                @include('site.archive.partials.post', ['post' => $post, 'subdirUrl' => $subdirUrl])
            @endforeach
        </ol>
    </section>
@endsection

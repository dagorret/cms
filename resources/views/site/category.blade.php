@extends('site.layouts.app')

@section('title', $category->name)

@section('content')
<section class="article-list">
    <header class="mb-8">
        <p class="kicker">Categoría</p>
        <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold">{{ $categoryPath }}</h1>
        @if($category->description)
            <p>{{ $category->description }}</p>
        @endif
    </header>

    @foreach($posts as $post)
        <article class="archive-item">
            <a href="{{ data_get($post, 'url') }}">{{ data_get($post, 'title') }}</a>
        </article>
    @endforeach
</section>
@endsection

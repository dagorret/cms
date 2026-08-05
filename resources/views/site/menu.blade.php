@php
    $homeUrl = ($subdirUrl ?? '') === '' ? '/' : rtrim($subdirUrl, '/') . '/';
@endphp
<a href="{{ $homeUrl }}" data-tag="">Inicio</a>
@foreach($items as $item)
@if(($item['type'] ?? null) === \App\Models\Post::TYPE_PAGE)
<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
@else
<a href="{{ rtrim($subdirUrl ?? '', '/') }}/category/{{ $item['slug'] }}/" data-tag="{{ $item['tag'] }}">{{ $item['title'] }}</a>
@endif
@endforeach

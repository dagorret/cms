@php
    $homeUrl = ($subdirUrl ?? '') === '' ? '/' : rtrim($subdirUrl, '/') . '/';
@endphp
<a href="{{ $homeUrl }}" data-tag="">Inicio</a>
@foreach($items as $item)
<a href="{{ $homeUrl }}?tag={{ $item['tag'] }}" data-tag="{{ $item['tag'] }}">{{ $item['title'] }}</a>
@endforeach

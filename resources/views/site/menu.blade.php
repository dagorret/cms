<a href="{{ $subdirUrl ?: '/' }}/" data-tag="">Inicio</a>
@foreach($items as $item)
<a href="{{ $subdirUrl }}/?tag={{ $item['tag'] }}" data-tag="{{ $item['tag'] }}">{{ $item['title'] }}</a>
@endforeach

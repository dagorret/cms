<h2>Archivo: {{ $year }} / {{ $month }}</h2>
<ul>
    @foreach($days as $day => $count)
        <li><a href="{{ $subdirUrl }}/archive/{{ $year }}/{{ $month }}/{{ $day }}/index.html">Día {{ $day }} ({{ $count }} posts)</a></li>
    @endforeach
</ul>

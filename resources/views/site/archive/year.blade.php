<h2>Archivo: {{ $year }}</h2>
<ul>
    @foreach($months as $month)
        <li><a href="{{ $subdirUrl }}/archive/{{ $year }}/{{ $month }}/index.html">Mes {{ $month }}</a></li>
    @endforeach
</ul>

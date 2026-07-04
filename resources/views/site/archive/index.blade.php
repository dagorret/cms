<h2>Archivo Histórico</h2>
<ul>
    @foreach($years as $year)
        <li><a href="{{ $subdirUrl }}/archive/{{ $year }}/index.html">{{ $year }}</a></li>
    @endforeach
</ul>

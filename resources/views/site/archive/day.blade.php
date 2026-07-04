<h2>Publicaciones del {{ $day }}/{{ $month }}/{{ $year }} ({{ $totalPosts }} posts)</h2>
<ul>
    @foreach($posts as $post)
        <li><a href="{{ $subdirUrl }}/{{ $post->slug }}/">{{ $post->title }}</a></li>
    @endforeach
</ul>

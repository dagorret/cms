{{ '<?xml version="1.0" encoding="utf-8"?>' }}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Bitácora de Ensayos</title>
    <link href="{{ $baseUrl }}/feed.xml" rel="self"/>
    <link href="{{ $baseUrl }}/"/>
    <updated>{{ now()->toAtomString() }}</updated>
    <id>{{ $baseUrl }}/</id>

    @foreach($posts as $post)
    <entry>
        <title><![CDATA[{{ $post->title }}]]></title>
        <link href="{{ $baseUrl }}/{{ $post->slug }}/"/>
        <id>{{ $baseUrl }}/{{ $post->slug }}/</id>
        <updated>{{ $post->updated_at->toAtomString() }}</updated>
        <summary><![CDATA[{{ $post->keywords }}]]></summary>
    </entry>
    @endforeach
</feed>

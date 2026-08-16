{{ '<?xml version="1.0" encoding="utf-8"?>' }}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Dagorret</title>
    <link>https://dagorret.com.ar/</link>
    <description>Bitácora de ensayos sobre sistemas, filosofía y tecnología.</description>
    <atom:link href="https://dagorret.com.ar/feed.xml" rel="self" type="application/rss+xml" />

    @foreach($posts as $post)
    <item>
      <title><![CDATA[{{ $post->title }}]]></title>
      <link>https://dagorret.com.ar/{{ $post->slug }}/</link>
      <guid>https://dagorret.com.ar/{{ $post->slug }}/</guid>
      <pubDate>{{ $post->updated_at->toRfc2822String() }}</pubDate>
      @if(isset($post->category))
      <category>{{ $post->category }}</category>
      @endif
    </item>
    @endforeach
  </channel>
</rss>

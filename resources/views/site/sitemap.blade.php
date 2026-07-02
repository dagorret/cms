{!! '<?xml version="1.0" encoding="UTF-8"?>'."\n" !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ $baseUrl }}/</loc>
        <priority>1.0</priority>
    </url>
    @foreach($posts as $post)
    <url>
        <loc>{{ $baseUrl }}/{{ $post->slug }}/</loc>
        <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        <priority>0.8</priority>
    </url>
    @endforeach
</urlset>

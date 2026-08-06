@foreach($posts as $post)
    @php
        $slug = trim((string) data_get($post, 'slug'), '/');
        $url = data_get($post, 'url') ?: "/{$slug}/";
        $title = data_get($post, 'title');
        $date = data_get($post, 'date');
        $categoryName = data_get($post, 'category.name');
        $keywordsValue = data_get($post, 'keywords', []);
        $keywords = collect(is_array($keywordsValue) ? $keywordsValue : explode(',', (string) $keywordsValue))->map(fn($keyword) => trim($keyword))->filter()->values();
        $excerpt = trim((string) data_get($post, 'excerpt', ''));
    @endphp
    <article class="border-b border-[#d8d0c3] py-9 dark:border-[#4a4640]">
        <div class="font-sans text-[.78rem] lowercase tracking-[.14em] text-[#66615a] dark:text-[#aaa298]">
            @if($date)<time datetime="{{ $date }}">{{ $date }}</time>@endif
            @if($categoryName)<span>{{ $date ? ' · ' : '' }}{{ $categoryName }}</span>@endif
            @if($keywords->isNotEmpty())<span> · {{ $keywords->join(', ') }}</span>@endif
        </div>
        <h2 class="my-3 font-serif text-[clamp(1.875rem,4vw,2.25rem)] font-bold leading-[1.04] tracking-[-.045em] text-[#171717] dark:text-[#f5f0e7]">
            <a href="{{ $url }}" class="decoration-[#0f4c5c]/35 underline-offset-[3px] hover:text-[#0f4c5c] dark:hover:text-[#8fc3cf]">{{ $title }}</a>
        </h2>
        @if($excerpt !== '')
            <p class="post-excerpt mt-3 max-w-2xl text-[1.06rem] leading-[1.62]">{{ $excerpt }}</p>
        @endif
    </article>
@endforeach

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} — Vista previa</title>
    <script>
        (() => { document.documentElement.classList.add('js'); let value = null; try { value = localStorage.getItem('cms-faro-theme'); } catch {} const dark = value === 'dark' || (value !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches); document.documentElement.classList.toggle('dark', dark); document.documentElement.style.colorScheme = dark ? 'dark' : 'light'; })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/post-preview.js'])
</head>
<body class="m-0 bg-[#f7f3eb] font-serif leading-[1.68] text-[#171717] antialiased [text-rendering:optimizeLegibility] [&_a]:text-inherit [&_a]:decoration-[#0f4c5c]/35 [&_a]:underline-offset-[3px] dark:bg-[#171717] dark:text-[#e8e1d5] dark:[&_a]:decoration-[#8fc3cf]/50">
<main class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
    <article class="min-w-0 w-full border border-[#d8d0c3] bg-[#fffaf2] p-[clamp(20px,4vw,54px)] dark:border-[#4a4640] dark:bg-[#211f1c]">
        <header class="mb-8 border-b border-[#d8d0c3] pb-6 dark:border-[#4a4640]">
            <div class="font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c] dark:text-[#8fc3cf]">
                Vista previa · {{ $type === \App\Models\Post::TYPE_PAGE ? 'Página' : 'Post' }}
                @if($categoryName)
                    · {{ $categoryName }}
                @endif
            </div>

            <h1 class="my-5 font-serif text-[clamp(2rem,4vw,4rem)] font-bold leading-[.98] tracking-[-.055em] text-[#171717] dark:text-[#f5f0e7]">
                {{ $title }}
            </h1>

            @if($publishedAt || $keywords)
                <div class="font-sans text-[.86rem] leading-6 text-[#66615a] dark:text-[#aaa298]">
                    @if($publishedAt)
                        <time datetime="{{ $publishedAt->format('Y-m-d') }}">{{ $publishedAt->format('Y-m-d') }}</time>
                    @endif
                    @if($publishedAt && $keywords)<span> · </span>@endif
                    @if($keywords)<span>{{ $keywords }}</span>@endif
                </div>
            @endif
        </header>

        @include('site.posts.partials.content', ['renderedBody' => $renderedBody])
    </article>
</main>
</body>
</html>

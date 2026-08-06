<script>
    (() => {
        const reveal = () => document.documentElement.classList.remove('math-pending');
        document.documentElement.classList.add('math-pending');
        window.setTimeout(reveal, 10000);
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true,
                packages: {'[+]': ['ams']},
            },
            svg: {
                fontCache: 'local',
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
            },
            startup: {
                ready() {
                    window.MathJax.startup.defaultReady();
                    window.MathJax.startup.promise.then(reveal, reveal);
                },
            },
        };
    })();
</script>

@if(isset($staticAssets) && $staticAssets instanceof \App\Support\StaticViteAssets)
    @if($staticAssets->mathJaxScriptUrl())
        <script type="module" src="{{ $staticAssets->mathJaxScriptUrl() }}"></script>
    @endif
@else
    @vite('resources/js/mathjax.js')
@endif

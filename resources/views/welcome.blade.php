<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a CMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#0a0a0a] text-zinc-100 min-h-screen flex flex-col justify-center items-center p-6 selection:bg-red-500 selection:text-white">

    <main class="max-w-xl text-center space-y-8">
        <span class="inline-flex items-center gap-x-1.5 rounded-full bg-red-500/10 px-3 py-1 text-xs font-medium text-red-400 ring-1 ring-inset ring-red-500/20">
            CachyOS Powered 🚀
        </span>

        <div class="space-y-4">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl bg-gradient-to-r from-white via-zinc-200 to-zinc-500 bg-clip-text text-transparent">
                Faro CMS
            </h1>
            <p class="text-zinc-400 text-lg leading-relaxed">
                Carlos Dagorret
            </p>
        </div>

        <div class="pt-4">
            <a href="/dash" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-red-600/20 hover:bg-red-500 transition-all duration-200 active:scale-95">
                Ingresar al Panel
                <svg class="ml-2 -mr-1 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </main>

    <footer class="absolute bottom-6 text-xs text-zinc-600">
        &copy; {{ date('Y') }} - Desarrollo Local Seguro
    </footer>

</body>
</html>

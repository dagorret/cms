import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',       // <--- ESTO: Le dice a Vite que escuche hacia el exterior del contenedor
        hmr: {
            host: 'localhost', // <--- ESTO: Le dice a tu navegador dónde buscar el Hot Reload
        },
        watch: {
            usePolling: true,  // <--- RECOMENDADO en Arch: fuerza a Docker a ver cambios de archivos en tiempo real
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
        // Aplicación instalable. El service worker solo precachea los estáticos
        // compilados: las navegaciones y toda /api/ van siempre a la red.
        // Vite lo deja en public/build/ y routes/web.php lo sirve desde /sw.js.
        VitePWA({
            registerType: 'autoUpdate',
            outDir: 'public/build',
            buildBase: '/build/',
            scope: '/',
            injectRegister: false,
            manifest: {
                name: 'Control de exámenes · UMSS',
                short_name: 'Exámenes UMSS',
                lang: 'es',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                theme_color: '#003870',
                background_color: '#f3f7fd',
                // Los generales llevan las esquinas redondeadas (en la computadora
                // se muestran tal cual); el enmascarable va a sangre para que
                // Android lo recorte a su forma, y el de iPhone
                // (apple-touch-icon) va cuadrado porque iOS lo redondea.
                icons: [
                    { src: '/iconos/icono-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/iconos/icono-512.png', sizes: '512x512', type: 'image/png' },
                    {
                        src: '/iconos/icono-512-maskable.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,woff2,svg,png}'],
                // El detector de rostros solo se usa en la pantalla de la foto.
                globIgnores: ['**/vision_bundle-*.js'],
                // El service worker se sirve desde /sw.js y los estáticos están
                // en /build/: las direcciones del precaché van absolutas y el
                // código de workbox, dentro del propio sw.js.
                modifyURLPrefix: { '': '/build/' },
                inlineWorkboxRuntime: true,
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
                        handler: 'NetworkOnly',
                    },
                ],
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/filament-chart-js-plugins.js',
                'resources/js/drag-drop-upload.js',
                'resources/js/receipt-upload-handler.js',
                'resources/js/sticky-blur-veil.js',
                'resources/js/select-value-marquee.js',
                'resources/js/receipt-image-preview.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '192.168.100.6',
        port: 5173,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

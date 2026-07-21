import os from 'os';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Prefer 192.168.*, then 10.*, for phone-reachable Vite HMR on the LAN.
 */
function detectLanIpv4() {
    const interfaces = os.networkInterfaces();
    const candidates = [];

    for (const addresses of Object.values(interfaces)) {
        if (!addresses) {
            continue;
        }

        for (const address of addresses) {
            if (address.family !== 'IPv4' || address.internal) {
                continue;
            }

            if (address.address.startsWith('192.168.')) {
                return address.address;
            }

            candidates.push(address.address);
        }
    }

    for (const ip of candidates) {
        if (ip.startsWith('10.')) {
            return ip;
        }
    }

    return candidates[0] ?? null;
}

const lanIp = detectLanIpv4();

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/filament-chart-js-plugins.js',
                'resources/js/disable-mobile-tippy.js',
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
        host: true,
        port: 5173,
        ...(lanIp ? { hmr: { host: lanIp } } : {}),
        cors: {
            origin: [
                /^https?:\/\/localhost(:\d+)?$/,
                /^https?:\/\/127\.0\.0\.1(:\d+)?$/,
                /^https?:\/\/192\.168\.\d+\.\d+(:\d+)?$/,
                /^https?:\/\/10\.\d+\.\d+\.\d+(:\d+)?$/,
            ],
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

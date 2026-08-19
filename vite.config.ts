import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig } from 'vite';

const devServerUrl = process.env.VITE_DEV_SERVER_URL ?? 'http://localhost:5173';
const devServer = new URL(devServerUrl);
const appOrigin = new URL(process.env.APP_URL ?? 'http://localhost:8000').origin;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: devServerUrl,
        cors: {
            origin: appOrigin,
        },
        hmr: {
            host: devServer.hostname,
            clientPort: Number(devServer.port || 80),
        },
    },
});

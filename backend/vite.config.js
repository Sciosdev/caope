import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        emptyOutDir: true,
        manifest: 'manifest.json',
        outDir: 'public/assets/build',
        rollupOptions: {
            output: {
                entryFileNames: (chunkInfo) => {
                    const facade = chunkInfo.facadeModuleId ?? '';

                    if (facade.endsWith('/resources/js/app.js')) {
                        return 'js/app.js';
                    }

                    if (facade.endsWith('/resources/js/expedientes/anexos.js')) {
                        return 'js/anexos.js';
                    }

                    return 'js/[name]-[hash].js';
                },
                chunkFileNames: 'js/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.name ?? '';
                    const normalized = name.split('/').pop() ?? '';
                    const originals = Array.isArray(assetInfo.originalFileNames)
                        ? assetInfo.originalFileNames.join('|')
                        : '';

                    if (normalized === 'app.css') {
                        if (originals.includes('resources/css/app.css')) {
                            return 'css/app.css';
                        }

                        if (originals.includes('resources/js/app.js')) {
                            return 'css/app-editor.css';
                        }

                        return 'css/app-[hash][extname]';
                    }

                    if (normalized === 'anexos.css') {
                        if (originals.includes('resources/js/expedientes/anexos.js')) {
                            return 'css/anexos.css';
                        }

                        return 'css/anexos-[hash][extname]';
                    }

                    if (normalized.endsWith('.css')) {
                        return `css/${normalized}`;
                    }

                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
    plugins: [
        laravel({
            buildDirectory: 'assets/build',
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/expedientes/anexos.js'],
            refresh: true,
        }),
    ],
});

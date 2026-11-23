import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/teams/theme.css',
                'resources/js/app.tsx',
                'resources/js/filament-select-scroll.ts',
                'resources/js/org-chart.js',
                'resources/js/teams.ts',
                'resources/js/teams-motion.ts',
                'resources/js/teams-popup.ts',
            ],
            // refresh: true,
            refresh: [
                'app/Filament/**',
                'app/Livewire/**',
                'resources/views/**',
            ],
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});

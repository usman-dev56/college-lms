import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Georgia', 'Cambria', 'Times New Roman', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                navy: {
                    DEFAULT: '#0F2B5B',
                    dark: '#0A1E40',
                    light: '#1A3D78',
                },
                gold: {
                    DEFAULT: '#C9A227',
                    light: '#E5C458',
                    dark: '#8B6F1A',
                },
                surface: {
                    DEFAULT: '#F8F9FB',
                    card: '#FFFFFF',
                },
            },
        },
    },

    plugins: [forms],
};
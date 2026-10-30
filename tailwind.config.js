import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['ui-monospace', ...defaultTheme.fontFamily.mono],
            },
            boxShadow : {
                card: '0 1px 0 0 rgb(255 255 255 / 0.05) inset, 0 8px 32px rgb(0 0 0 / 0.35)',
            },
        },
    },

    plugins: [forms],
};

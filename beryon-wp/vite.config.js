import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from 'tailwindcss';

/**
 * پیکربندی Vite قالب بریون
 * ------------------------------------------------------------------
 * ورودی‌ها:
 *   resources/css/app.css  → طراحی سیستم بریون + فونت خودمیمان + استایل ویرایشگر
 *   resources/js/app.js    → اسکریپت اصلی سایت (بدون وابستگی خارجی)
 *   resources/js/admin.js  → اسکریپت پنل تنظیمات و به‌روزرسانی Patch
 *
 * خروجی: public/assets/ با نام فایل «ثابت» (بدون hash) تا قالب حتی بدون اجرای
 * npm run build هم با فایل‌های از پیش بیلدشدهٔ داخل بسته کار کند؛ نسخه‌گذاری کش
 * از راه filemtime() در app/Assets.php انجام می‌شود.
 *
 * @see app/Assets.php — ترتیب بارگذاری: manifest.json → public/assets → resources (raw)
 */
export default defineConfig({
    base: './',
    publicDir: false,
    build: {
        outDir: 'public/assets',
        emptyOutDir: false,
        assetsDir: 'media',
        target: 'es2018',
        cssTarget: 'chrome61',
        cssMinify: true,
        minify: 'esbuild',
        reportCompressedSize: false,
        manifest: 'manifest.json',
        rollupOptions: {
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin.js',
            ],
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/chunks/[name].js',
                assetFileNames: (asset) => {
                    const name = asset.name || '';

                    if (name.endsWith('.css')) {
                        return 'css/[name][extname]';
                    }
                    if (/\.(png|jpe?g|webp|svg|gif|avif)$/.test(name)) {
                        return 'images/[name][extname]';
                    }
                    if (/\.(woff2?|ttf|otf|eot)$/.test(name)) {
                        return 'fonts/[name][extname]';
                    }

                    return 'media/[name][extname]';
                },
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin.js',
            ],
            refresh: [
                'resources/views/**/*',
                'app/**/*.php',
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        watch: { ignored: ['**/vendor/**', '**/node_modules/**'] },
    },
});

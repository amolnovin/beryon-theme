/** @type {import('tailwindcss').Config} */
export default {
    /*
     * Tailwind در این قالب «لایه اختیاری» است: تمام ظاهر سایت با طراحی‌سیستم
     * اختصاصی بریون (resources/css/beryon.css) ساخته می‌شود تا DOM و ظاهر صفحات
     * دقیقاً مطابق قالب HTML نهایی بماند. اگر خواستید از utility classها استفاده
     * کنید، کافی است corePlugins.preflight را true کنید.
     */
    corePlugins: {
        preflight: false,
    },
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],
    safelist: [],
    theme: {
        extend: {
            colors: {
                brand: {
                    DEFAULT: '#d5232c',
                    dark: '#a4121b',
                    light: '#ff6a5e',
                    soft: '#fdeceb',
                },
                ink: {
                    DEFAULT: '#181a1e',
                    soft: '#3f424a',
                    muted: '#6a6e77',
                },
                surface: '#fffaf7',
                line: '#f1e3dc',
                gold: '#f5b301',
                green: '#1e9e55',
            },
            fontFamily: {
                sans: ['Vazirmatn', 'system-ui', 'Segoe UI', 'Tahoma', 'Arial', 'sans-serif'],
            },
            borderRadius: {
                card: '20px',
            },
            boxShadow: {
                card: '0 10px 30px rgba(24,26,30,.08)',
                lift: '0 26px 60px rgba(164,18,27,.16)',
            },
            spacing: {
                header: '80px',
            },
        },
    },
    plugins: [],
};

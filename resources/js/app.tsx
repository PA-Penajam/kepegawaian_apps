import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { ErrorBoundary } from '@/components/error-boundary';
import { TooltipProvider } from '@/components/ui/tooltip';
import '../css/app.css';
import { initializeTheme } from '@/hooks/use-appearance';
import { setupInertiaErrorListeners } from '@/lib/inertia-error-listener';

const appName = import.meta.env.VITE_APP_NAME || 'Kepegawaian';

// Inisialisasi pendengar error global Inertia
setupInertiaErrorListeners();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <ErrorBoundary>
                    <TooltipProvider delayDuration={0}>
                        <App {...props} />
                    </TooltipProvider>
                </ErrorBoundary>
            </StrictMode>,
        );
    },
    progress: {
        color: '#166534',
    },
});

// Inisialisasi tema light / dark mode
initializeTheme();

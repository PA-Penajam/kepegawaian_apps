import type { Auth } from '@/types/auth';
import type { SharedFlash } from '@/types/sync';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            flash?: SharedFlash;
            [key: string]: unknown;
        };
    }
}

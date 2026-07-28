import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            extensionAssets: {
                version: string;
                styles: {
                    extension: string;
                    url: string;
                    integrity: string;
                }[];
                scripts: {
                    extension: string;
                    url: string;
                    integrity: string;
                }[];
            };
            auth: Auth;
            notificationSummary: {
                unreadCount: number;
            };
            messageSummary: {
                unreadCount: number;
            };
            draftSummary: {
                count: number;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

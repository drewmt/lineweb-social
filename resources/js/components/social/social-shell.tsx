import type { PropsWithChildren } from 'react';
import {
    DesktopSocialNav,
    MobileSocialHeader,
    MobileSocialTabs,
} from './social-nav';

export function SocialShell({ children }: PropsWithChildren) {
    return (
        <div className="min-h-dvh bg-background text-foreground">
            <DesktopSocialNav />
            <MobileSocialHeader />
            <div className="relative min-h-dvh pb-28 lg:pb-0 lg:pl-[18.5rem]">
                <div className="pointer-events-none fixed inset-x-0 top-0 -z-0 h-72 bg-[radial-gradient(circle_at_72%_0%,oklch(0.92_0.055_251_/_0.55),transparent_48%)] lg:left-[18.5rem]" />
                {children}
            </div>
            <MobileSocialTabs />
        </div>
    );
}

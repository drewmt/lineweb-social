import { Link } from '@inertiajs/react';
import { BellRing, Palette, ShieldCheck, UserRound, UserX } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: UserRound,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
    {
        title: 'Safety',
        href: '/settings/safety',
        icon: UserX,
    },
    {
        title: 'Notifications',
        href: '/settings/notifications',
        icon: BellRing,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="social-page max-w-6xl">
            <div className="social-page-heading">
                <p className="social-eyebrow">Your account</p>
                <Heading
                    title="Settings"
                    description="Shape your public identity, privacy, security, and experience."
                />
            </div>

            <div className="mt-6 grid items-start gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <aside className="social-card overflow-hidden rounded-[1.5rem] p-2 lg:sticky lg:top-5">
                    <p className="px-3 pt-3 pb-2 text-[0.67rem] font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                        Account controls
                    </p>
                    <nav
                        className="flex gap-1 overflow-x-auto lg:flex-col"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'min-h-11 shrink-0 justify-start gap-3 rounded-xl px-3 lg:w-full',
                                    {
                                        'bg-primary/10 text-primary hover:bg-primary/14 hover:text-primary':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="size-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="lg:hidden" />

                <div className="social-card min-w-0 rounded-[1.5rem] p-5 sm:p-7 lg:p-9">
                    <section className="max-w-2xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}

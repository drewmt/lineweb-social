import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    FileQuestion,
    LayoutDashboard,
    MessageSquareWarning,
    ScrollText,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { AppContent } from '@/components/app-content';
import AppLogoIcon from '@/components/app-logo-icon';
import { AppShell } from '@/components/app-shell';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarSeparator,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

const adminSections = [
    {
        title: 'Overview',
        description: 'Platform pulse',
        href: '/admin',
        icon: LayoutDashboard,
        exact: true,
    },
    {
        title: 'Members',
        description: 'Account access',
        href: '/admin/members',
        icon: UsersRound,
        exact: false,
    },
    {
        title: 'Appeals',
        description: 'Human account review',
        href: '/admin/appeals',
        icon: FileQuestion,
        exact: false,
    },
    {
        title: 'Safety',
        description: 'Message reports',
        href: '/admin/message-reports',
        icon: MessageSquareWarning,
        exact: false,
    },
    {
        title: 'Audit',
        description: 'Privileged actions',
        href: '/admin/audit',
        icon: ScrollText,
        exact: false,
    },
] as const;

export function AdminShell({ children }: PropsWithChildren) {
    return (
        <AppShell>
            <AdminSidebar />
            <AppContent
                variant="sidebar"
                className="min-w-0 overflow-x-hidden bg-background"
            >
                <AdminHeader />
                <div className="pointer-events-none fixed inset-x-0 top-0 -z-0 h-80 bg-[radial-gradient(circle_at_72%_0%,oklch(0.92_0.055_251_/_0.48),transparent_52%)] md:left-[var(--sidebar-width)]" />
                {children}
            </AppContent>
        </AppShell>
    );
}

function AdminSidebar() {
    const { currentUrl } = useCurrentUrl();
    const { setOpenMobile } = useSidebar();

    const closeMobile = () => setOpenMobile(false);

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="border-sidebar-border/70"
        >
            <SidebarHeader className="p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            size="lg"
                            className="h-14 rounded-2xl px-2 data-[active=true]:bg-transparent"
                        >
                            <Link
                                href="/admin"
                                aria-label="Lineweb Social administration"
                                onClick={closeMobile}
                            >
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-[0.95rem] bg-foreground text-background">
                                    <AppLogoIcon className="size-6" />
                                </span>
                                <span className="min-w-0 group-data-[collapsible=icon]:hidden">
                                    <span className="block truncate text-sm font-black tracking-[-0.025em]">
                                        Control center
                                    </span>
                                    <span className="mt-0.5 block truncate text-[0.65rem] font-bold tracking-[0.13em] text-muted-foreground uppercase">
                                        Lineweb Social
                                    </span>
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarSeparator />

            <SidebarContent>
                <SidebarGroup className="px-3 py-4">
                    <SidebarGroupLabel className="mb-1 px-2 text-[0.65rem] font-extrabold tracking-[0.14em] uppercase">
                        Platform
                    </SidebarGroupLabel>
                    <SidebarMenu className="gap-1.5">
                        {adminSections.map((section) => {
                            const active = section.exact
                                ? currentUrl === section.href
                                : currentUrl.startsWith(section.href);
                            const Icon = section.icon;

                            return (
                                <SidebarMenuItem key={section.href}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={active}
                                        size="lg"
                                        tooltip={section.title}
                                        className={cn(
                                            'h-13 rounded-2xl px-3 transition-colors',
                                            active &&
                                                'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary hover:text-sidebar-primary-foreground',
                                        )}
                                    >
                                        <Link
                                            href={section.href}
                                            prefetch
                                            onClick={closeMobile}
                                        >
                                            <Icon
                                                className="size-4.5"
                                                strokeWidth={2.15}
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-extrabold">
                                                    {section.title}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'mt-0.5 block truncate text-[0.66rem] font-semibold',
                                                        active
                                                            ? 'text-sidebar-primary-foreground/70'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {section.description}
                                                </span>
                                            </span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter className="gap-2 p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            size="lg"
                            tooltip="Back to community"
                            className="h-12 rounded-2xl border border-sidebar-border/75 bg-background/65 px-3"
                        >
                            <Link href="/feed" prefetch onClick={closeMobile}>
                                <ArrowLeft
                                    className="size-4.5"
                                    aria-hidden="true"
                                />
                                <span className="font-extrabold">
                                    Back to community
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}

function AdminHeader() {
    const { currentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const currentSection =
        adminSections.find((section) =>
            section.exact
                ? currentUrl === section.href
                : currentUrl.startsWith(section.href),
        ) ?? adminSections[0];

    return (
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-border/70 bg-card/88 px-3 backdrop-blur-xl sm:px-5">
            <SidebarTrigger className="social-focus size-11 rounded-xl border border-border/75 bg-background/70 hover:bg-secondary" />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-black tracking-[-0.02em]">
                    {currentSection.title}
                </p>
                <p className="hidden truncate text-xs font-semibold text-muted-foreground sm:block">
                    {currentSection.description}
                </p>
            </div>
            <div className="inline-flex min-h-10 items-center gap-2 rounded-full border border-primary/15 bg-primary/[0.06] px-3 text-xs font-extrabold text-primary">
                <ShieldCheck className="size-4" aria-hidden="true" />
                <span className="hidden sm:inline">Protected operator</span>
                <span className="sm:hidden">Admin</span>
                <span className="sr-only">
                    , signed in as {auth.user?.name}
                </span>
            </div>
        </header>
    );
}

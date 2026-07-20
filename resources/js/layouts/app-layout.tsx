import { SocialShell } from '@/components/social/social-shell';

export default function AppLayout({ children }: { children: React.ReactNode }) {
    return <SocialShell>{children}</SocialShell>;
}

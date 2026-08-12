import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    eyebrow,
    children,
}: {
    title?: string;
    description?: string;
    eyebrow?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            eyebrow={eyebrow}
        >
            {children}
        </AuthLayoutTemplate>
    );
}

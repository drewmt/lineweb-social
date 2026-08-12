import { Form, Head } from '@inertiajs/react';
import { Check, MailCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2.5 sm:col-span-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="How people will know you"
                                    className="h-12 rounded-2xl px-4"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2.5 sm:col-span-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="you@example.com"
                                    className="h-12 rounded-2xl px-4"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                    className="h-12 rounded-2xl px-4"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                    className="h-12 rounded-2xl px-4"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-start gap-2.5 rounded-2xl bg-secondary/55 px-3.5 py-3 text-xs leading-5 font-semibold text-muted-foreground sm:col-span-2">
                                <MailCheck className="mt-0.5 size-4 shrink-0 text-primary" />
                                We will ask you to verify your email before you
                                join community spaces.
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="mt-1 w-full sm:col-span-2"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="flex items-center justify-center gap-1.5 text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink
                                href={login()}
                                tabIndex={6}
                                className="font-extrabold text-primary no-underline hover:underline"
                            >
                                Log in
                            </TextLink>
                        </div>

                        <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[0.68rem] font-semibold text-muted-foreground">
                            {[
                                'Chronological by default',
                                'Your privacy controls',
                            ].map((item) => (
                                <span
                                    key={item}
                                    className="inline-flex items-center gap-1.5"
                                >
                                    <Check className="size-3.5 text-primary" />
                                    {item}
                                </span>
                            ))}
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    eyebrow: 'Join the community',
    title: 'Your place in the network starts here.',
    description:
        'Create an identity you control, discover purposeful spaces, and choose the conversations that reach you.',
};

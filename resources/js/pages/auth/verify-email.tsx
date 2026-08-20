// Components
import { Form, Head } from '@inertiajs/react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { errorsToArray } from '@/lib/form-errors';
import { logout as keycloakLogout } from '@/routes/keycloak';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Verify email"
            description="Please verify your email address by clicking on the link we just emailed to you."
        >
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-primary">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing, errors }) => (
                    <>
                        {Object.keys(errors).length > 0 && (
                            <AlertError
                                errors={errorsToArray(errors)}
                                title="Gagal mengirim ulang email verifikasi"
                            />
                        )}
                        <Button processing={processing} variant="secondary">
                            Resend verification email
                        </Button>

                        <Form method="post" action={keycloakLogout()} className="mx-auto block">
                            <button
                                type="submit"
                                className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Log out
                            </button>
                        </Form>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}

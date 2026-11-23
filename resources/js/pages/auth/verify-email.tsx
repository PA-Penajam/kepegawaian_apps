// Components
import { Form, Head } from '@inertiajs/react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { errorsToArray } from '@/lib/form-errors';
import { logout } from '@/routes/auth/sso';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Verifikasi email"
            description="Silakan verifikasi alamat email Anda dengan mengklik tautan yang baru saja kami kirimkan ke email Anda."
        >
            <Head title="Verifikasi email" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-primary">
                    Tautan verifikasi baru telah dikirim ke alamat email yang
                    Anda daftarkan.
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
                            Kirim ulang email verifikasi
                        </Button>
                    </>
                )}
            </Form>

            <Form {...logout.form()} className="mt-4 text-center">
                <button
                    type="submit"
                    className="cursor-pointer text-sm text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                >
                    Keluar
                </button>
            </Form>
        </AuthLayout>
    );
}

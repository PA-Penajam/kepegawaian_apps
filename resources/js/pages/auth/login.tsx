import { Head, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { login as ssoLogin } from '@/routes/auth/sso';

type Props = {
    status?: string;
};

export default function Login({ status }: Props) {
    const { flash } = usePage<{
        flash?: { error?: string | null; success?: string | null };
    }>().props;
    const shouldReduceMotion = useReducedMotion();

    return (
        <AuthLayout
            title="SIMPEG PA Penajam"
            description="Masuk menggunakan akun SSO PA Penajam"
        >
            <Head title="Login SIMPEG" />

            <AnimatePresence>
                {flash?.error && (
                    <motion.div
                        initial={
                            shouldReduceMotion
                                ? { opacity: 0 }
                                : { opacity: 0, y: -6 }
                        }
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.25 }}
                    >
                        <AlertError
                            errors={[flash.error]}
                            title="Autentikasi SSO Gagal"
                        />
                    </motion.div>
                )}
            </AnimatePresence>

            <AnimatePresence>
                {status && (
                    <motion.div
                        initial={
                            shouldReduceMotion
                                ? { opacity: 0 }
                                : { opacity: 0, y: 6 }
                        }
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.25 }}
                        role="status"
                        className="rounded-lg border border-primary/20 bg-primary/10 p-3 text-center text-sm font-medium text-primary"
                    >
                        {status}
                    </motion.div>
                )}
            </AnimatePresence>

            <div className="space-y-5">
                <div className="rounded-xl border border-border bg-muted/40 p-4 text-sm leading-relaxed text-muted-foreground">
                    <div className="flex items-start gap-3">
                        <ShieldCheck
                            className="mt-0.5 size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <p>
                            SIMPEG menggunakan autentikasi terpusat. NIP,
                            password, dan verifikasi keamanan dikelola melalui
                            SSO PA Penajam.
                        </p>
                    </div>
                </div>

                <Button
                    asChild
                    size="lg"
                    className="h-11 w-full text-sm font-semibold tracking-wide"
                    data-test="sso-login-button"
                >
                    <a href={ssoLogin.url()}>
                        <ShieldCheck className="size-4" aria-hidden="true" />
                        Masuk dengan SSO PA Penajam
                    </a>
                </Button>

                <p className="text-center text-xs text-muted-foreground">
                    Kendala akses?{' '}
                    <span className="font-medium text-foreground/80">
                        Hubungi Kepegawaian & Ortala PA Penajam
                    </span>
                </p>
            </div>
        </AuthLayout>
    );
}

import { Form, Head, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { errorsToArray } from '@/lib/form-errors';
import { login as ssoLogin } from '@/routes/auth/sso';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { flash } = usePage<{
        flash?: { error?: string | null; success?: string | null };
    }>().props;
    const shouldReduceMotion = useReducedMotion();

    const containerVariants = {
        hidden: { opacity: 0 },
        visible: {
            opacity: 1,
            transition: {
                staggerChildren: shouldReduceMotion ? 0 : 0.05,
                delayChildren: shouldReduceMotion ? 0 : 0.05,
            },
        },
    };

    const itemVariants = {
        hidden: shouldReduceMotion ? { opacity: 0 } : { opacity: 0, y: 8 },
        visible: {
            opacity: 1,
            y: 0,
            transition: { duration: 0.25, ease: 'easeOut' as const },
        },
    };

    return (
        <AuthLayout
            title="SIMPEG PA Penajam"
            description="Sistem Informasi Kepegawaian Pengadilan Agama Penajam"
        >
            <Head title="Login SIMPEG" />

            {/* Flash Error (misal kegagalan callback SSO) */}
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

            {/* Status Pesan Sistem */}
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

            {/* FORM LOGIN LOKAL (NIP & PASSWORD - DEFAULT & UTAMA) */}
            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <AnimatePresence mode="wait">
                            {Object.keys(errors).length > 0 && (
                                <motion.div
                                    key="login-error-banner"
                                    initial={
                                        shouldReduceMotion
                                            ? { opacity: 0 }
                                            : { opacity: 0, y: -6, scale: 0.98 }
                                    }
                                    animate={
                                        shouldReduceMotion
                                            ? { opacity: 1 }
                                            : {
                                                  opacity: 1,
                                                  y: 0,
                                                  scale: 1,
                                                  x: [0, -4, 4, -2, 2, 0],
                                                  transition: {
                                                      duration: 0.35,
                                                      ease: 'easeOut',
                                                  },
                                              }
                                    }
                                    exit={{
                                        opacity: 0,
                                        height: 0,
                                        marginTop: 0,
                                    }}
                                >
                                    <AlertError
                                        errors={errorsToArray(errors)}
                                        title="Login gagal"
                                    />
                                </motion.div>
                            )}
                        </AnimatePresence>

                        <motion.div
                            variants={containerVariants}
                            initial="hidden"
                            animate="visible"
                            className="grid gap-5"
                        >
                            <motion.div
                                variants={itemVariants}
                                className="grid gap-2"
                            >
                                <Label htmlFor="nip">
                                    NIP (Nomor Induk Pegawai)
                                </Label>
                                <Input
                                    id="nip"
                                    type="text"
                                    name="nip"
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    maxLength={18}
                                    required
                                    autoFocus
                                    autoComplete="username"
                                    placeholder="Contoh: 199001012020121001"
                                    onInput={(e) => {
                                        const target = e.currentTarget;
                                        target.value = target.value.replace(
                                            /\D/g,
                                            '',
                                        );
                                    }}
                                    aria-invalid={errors.nip ? true : undefined}
                                    aria-describedby={
                                        errors.nip ? 'nip-error' : undefined
                                    }
                                />
                                <InputError
                                    id="nip-error"
                                    message={errors.nip}
                                />
                            </motion.div>

                            <motion.div
                                variants={itemVariants}
                                className="grid gap-2"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-1">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="text-xs text-muted-foreground transition-colors hover:text-foreground"
                                        >
                                            Lupa password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder="Masukkan password akun"
                                    aria-invalid={
                                        errors.password ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.password
                                            ? 'password-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="password-error"
                                    message={errors.password}
                                />
                            </motion.div>

                            <motion.div
                                variants={itemVariants}
                                className="flex min-h-[36px] items-center space-x-2.5"
                            >
                                <Checkbox id="remember" name="remember" />
                                <Label
                                    htmlFor="remember"
                                    className="cursor-pointer text-sm font-normal text-muted-foreground select-none hover:text-foreground"
                                >
                                    Ingat saya di perangkat ini
                                </Label>
                            </motion.div>

                            <motion.div
                                variants={itemVariants}
                                className="space-y-3"
                            >
                                <Button
                                    type="submit"
                                    className="h-10 w-full text-sm font-semibold tracking-wide"
                                    processing={processing}
                                    data-test="login-button"
                                >
                                    Masuk ke SIMPEG
                                </Button>

                                <p className="text-center text-xs text-muted-foreground">
                                    Kendala akses?{' '}
                                    <span className="font-medium text-foreground/80">
                                        Hubungi Kepegawaian & Ortala PA Penajam
                                    </span>
                                </p>
                            </motion.div>
                        </motion.div>
                    </>
                )}
            </Form>

            {/* PEMISAH */}
            <div className="flex items-center gap-3 py-1">
                <div className="h-px flex-1 bg-border" />
                <span className="text-xs tracking-wider text-muted-foreground uppercase">
                    atau
                </span>
                <div className="h-px flex-1 bg-border" />
            </div>

            {/* OPSI SEKUNDER: MASUK DENGAN SSO PA PENAJAM */}
            <Button
                asChild
                variant="outline"
                size="lg"
                className="h-10 w-full border-input bg-background text-sm font-semibold tracking-wide text-foreground shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                data-test="sso-login-button"
            >
                <a href={ssoLogin.url()}>
                    <ShieldCheck
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    Masuk dengan SSO PA Penajam
                </a>
            </Button>
        </AuthLayout>
    );
}

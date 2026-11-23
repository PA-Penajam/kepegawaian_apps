import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { errorsToArray } from '@/lib/form-errors';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan profil',
        href: edit(),
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan profil" />

            <h1 className="sr-only">Pengaturan profil</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Informasi profil"
                        description="Perbarui nama lengkap dan alamat email Anda"
                    />

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                {Object.keys(errors).length > 0 && (
                                    <AlertError
                                        errors={errorsToArray(errors)}
                                        title="Gagal memperbarui profil"
                                    />
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="nama_lengkap">
                                        Nama lengkap
                                    </Label>

                                    <Input
                                        id="nama_lengkap"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.nama_lengkap}
                                        name="nama_lengkap"
                                        required
                                        autoComplete="name"
                                        placeholder="Nama lengkap"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.nama_lengkap}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Alamat email</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email ?? ''}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Alamat email"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.email}
                                    />
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div>
                                            <p className="-mt-4 text-sm text-muted-foreground">
                                                Alamat email Anda belum
                                                terverifikasi.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    Klik di sini untuk mengirim
                                                    ulang email verifikasi.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                                    Tautan verifikasi baru telah
                                                    dikirim ke alamat email
                                                    Anda.
                                                </div>
                                            )}
                                        </div>
                                    )}

                                <div className="flex items-center gap-4">
                                    <Button
                                        processing={processing}
                                        data-test="update-profile-button"
                                    >
                                        Simpan
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            Tersimpan
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ShieldCheck
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-semibold text-foreground">
                                Akun dikelola oleh SSO PA Penajam
                            </p>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                Pengubahan kata sandi serta penghapusan atau
                                penonaktifan akun dilakukan terpusat melalui
                                portal SSO PA Penajam dan administrator
                                kepegawaian.
                            </p>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

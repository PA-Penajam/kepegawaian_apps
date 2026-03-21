import { Head, Link, usePage } from '@inertiajs/react';
import {
    Users,
    TrendingUp,
    Calendar,
    GraduationCap,
    Building2,
} from 'lucide-react';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Sistem Informasi Kepegawaian" />
            <div className="flex min-h-screen flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
                <header className="w-full border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                    <div className="mx-auto flex max-w-7xl items-center justify-between">
                        <div className="flex items-center gap-2 text-xl font-bold text-blue-600 dark:text-blue-500">
                            <Building2 className="h-6 w-6" />
                            <span>Kepegawaian</span>
                        </div>
                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-gray-900"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-gray-900"
                                >
                                    Log in
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="flex-1">
                    <section className="relative overflow-hidden bg-white px-6 py-24 sm:py-32 lg:px-8 dark:bg-gray-900">
                        <div className="mx-auto max-w-2xl text-center">
                            <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white">
                                Sistem Informasi Kepegawaian
                            </h1>
                            <p className="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-400">
                                Solusi terpadu untuk manajemen data pegawai,
                                monitoring kenaikan pangkat, dan pelacakan
                                riwayat karir secara efisien dan akurat.
                            </p>
                            <div className="mt-10 flex items-center justify-center gap-x-6">
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                                    >
                                        Buka Dashboard
                                    </Link>
                                ) : (
                                    <Link
                                        href={login()}
                                        className="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                                    >
                                        Mulai Sekarang
                                    </Link>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto max-w-7xl px-6 py-16 sm:py-24 lg:px-8">
                        <div className="mx-auto max-w-2xl lg:text-center">
                            <h2 className="text-base leading-7 font-semibold text-blue-600 dark:text-blue-400">
                                Fitur Utama
                            </h2>
                            <p className="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">
                                Kelola SDM dengan Lebih Baik
                            </p>
                        </div>
                        <div className="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                            <dl className="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-4">
                                <div className="flex flex-col">
                                    <dt className="flex items-center gap-x-3 text-base leading-7 font-semibold text-gray-900 dark:text-white">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                            <Users
                                                className="h-6 w-6 text-white"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        Manajemen Data Pegawai
                                    </dt>
                                    <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                        <p className="flex-auto">
                                            Penyimpanan dan pengelolaan data
                                            profil pegawai, keluarga, dan
                                            dokumen kepegawaian secara terpusat.
                                        </p>
                                    </dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="flex items-center gap-x-3 text-base leading-7 font-semibold text-gray-900 dark:text-white">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                            <TrendingUp
                                                className="h-6 w-6 text-white"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        Monitoring Kenaikan Pangkat
                                    </dt>
                                    <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                        <p className="flex-auto">
                                            Sistem peringatan dini dan pelacakan
                                            untuk pegawai yang memenuhi syarat
                                            kenaikan pangkat.
                                        </p>
                                    </dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="flex items-center gap-x-3 text-base leading-7 font-semibold text-gray-900 dark:text-white">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                            <Calendar
                                                className="h-6 w-6 text-white"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        Tracking Kenaikan Gaji Berkala
                                    </dt>
                                    <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                        <p className="flex-auto">
                                            Otomatisasi pemantauan jadwal
                                            Kenaikan Gaji Berkala (KGB)
                                            berdasarkan masa kerja golongan.
                                        </p>
                                    </dd>
                                </div>
                                <div className="flex flex-col">
                                    <dt className="flex items-center gap-x-3 text-base leading-7 font-semibold text-gray-900 dark:text-white">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                            <GraduationCap
                                                className="h-6 w-6 text-white"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        Riwayat Pendidikan & Diklat
                                    </dt>
                                    <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                        <p className="flex-auto">
                                            Pencatatan komprehensif riwayat
                                            pendidikan formal dan
                                            pelatihan/diklat yang pernah diikuti
                                            pegawai.
                                        </p>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-gray-200 bg-white py-8 dark:border-gray-800 dark:bg-gray-900">
                    <div className="mx-auto max-w-7xl px-6 lg:px-8">
                        <p className="text-center text-sm leading-5 text-gray-500 dark:text-gray-400">
                            &copy; {new Date().getFullYear()} Sistem Informasi
                            Kepegawaian. All rights reserved.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Award,
    BookOpen,
    Briefcase,
    Building,
    Edit,
    FileText,
    GraduationCap,
    Scale,
    Trophy,
    User,
    Users,
} from 'lucide-react';
import DokumenPegawaiController from '@/actions/App/Http/Controllers/Kepegawaian/DokumenPegawaiController';
import HukumanDisiplinController from '@/actions/App/Http/Controllers/Kepegawaian/HukumanDisiplinController';
import KeluargaController from '@/actions/App/Http/Controllers/Kepegawaian/KeluargaController';
import PegawaiController from '@/actions/App/Http/Controllers/Kepegawaian/PegawaiController';
import PenghargaanController from '@/actions/App/Http/Controllers/Kepegawaian/PenghargaanController';
import RiwayatDiklatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatDiklatController';
import RiwayatJabatanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatJabatanController';
import RiwayatPangkatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPangkatController';
import RiwayatPendidikanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPendidikanController';
import { FotoUpload } from '@/components/pegawai/FotoUpload';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { PegawaiDetail } from '@/types/pegawai-detail';

// Controller Actions untuk redirect

export default function PegawaiShow({ pegawai }: { pegawai: PegawaiDetail }) {
    const { auth } = usePage().props;
    const canUpdate = (auth.user.permissions ?? []).includes('pegawai.update');

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Pegawai',
            href: '/kepegawaian/pegawai',
        },
        {
            title: pegawai.nama_lengkap,
            href: `/kepegawaian/pegawai/${pegawai.id}`,
        },
    ];

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((item) => item[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={pegawai.nama_lengkap} />

            <div className="flex w-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:mx-auto lg:max-w-6xl">
                <div className="flex flex-col gap-6 rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm md:flex-row md:items-start md:justify-between">
                    <div className="flex items-start gap-4">
                        <FotoUpload
                            pegawaiId={pegawai.id}
                            currentFotoUrl={pegawai.foto_url}
                            initials={getInitials(pegawai.nama_lengkap)}
                            canUpdate={canUpdate}
                        />
                        <div className="space-y-1.5">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {pegawai.nama_lengkap}
                            </h1>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <Badge variant="secondary" className="tnum">
                                    NIP: {pegawai.nip ?? '-'}
                                </Badge>
                                <span className="flex items-center gap-1 font-medium text-foreground/90">
                                    <Briefcase className="h-4 w-4 text-muted-foreground" />
                                    {pegawai.jabatan?.nama ??
                                        'Belum ada jabatan'}
                                </span>
                                <span className="hidden md:inline">•</span>
                                <span className="flex items-center gap-1 font-medium text-foreground/90">
                                    <Building className="h-4 w-4 text-muted-foreground" />
                                    {pegawai.unit_kerja?.nama ??
                                        'Belum ada unit kerja'}
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm font-medium text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <Award className="h-4 w-4" />
                                    {pegawai.pangkat
                                        ? `${pegawai.pangkat.nama} (${pegawai.pangkat.golongan}/${pegawai.pangkat.ruang})`
                                        : 'Belum ada pangkat'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <Button variant="outline" asChild>
                            <Link href="/kepegawaian/pegawai">
                                <ArrowLeft className="h-4 w-4" />
                                Kembali
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={`/kepegawaian/pegawai/${pegawai.id}/edit`}
                            >
                                <Edit className="h-4 w-4" />
                                Ubah Data
                            </Link>
                        </Button>
                    </div>
                </div>

                <div>
                    <h2 className="mb-4 text-lg font-semibold tracking-tight">
                        Menu Pengelolaan Data Pegawai
                    </h2>

                    {/* Grid Kartu Launcher Border-First sesuai DESIGN.md */}
                    <div className="grid grid-cols-1 gap-4 pb-12 sm:grid-cols-2 lg:grid-cols-3">
                        {/* Biodata */}
                        <Link
                            href={toUrl(PegawaiController.edit(pegawai.id))}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <User className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Biodata Pribadi
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Ubah data personal, kontak, dan alamat
                                    domisili untuk pegawai ini.
                                </p>
                            </div>
                        </Link>

                        {/* Keluarga */}
                        <Link
                            href={toUrl(KeluargaController.index(pegawai.id))}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Users className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Keluarga
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Kelola dan lihat data pasangan & anak
                                    tanggungan dari pegawai.
                                </p>
                            </div>
                        </Link>

                        {/* Riwayat Jabatan */}
                        <Link
                            href={toUrl(
                                RiwayatJabatanController.index(pegawai.id),
                            )}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Briefcase className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Riwayat Jabatan
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Lihat daftar riwayat mutasi, fungsional, dan
                                    promosi pelantikan.
                                </p>
                            </div>
                        </Link>

                        {/* Riwayat Pangkat */}
                        <Link
                            href={toUrl(
                                RiwayatPangkatController.index(pegawai.id),
                            )}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Award className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Riwayat Pangkat
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Data riwayat update golongan, hingga status
                                    kenaikan pangkat resmi.
                                </p>
                            </div>
                        </Link>

                        {/* Pendidikan */}
                        <Link
                            href={toUrl(
                                RiwayatPendidikanController.index(pegawai.id),
                            )}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <GraduationCap className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Pendidikan
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Rekam jejak gelar akademik formal dan
                                    rincian ijazah dari institut.
                                </p>
                            </div>
                        </Link>

                        {/* Diklat */}
                        <Link
                            href={toUrl(
                                RiwayatDiklatController.index(pegawai.id),
                            )}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <BookOpen className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Riwayat Diklat
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Kualifikasi bersertifikasi teknis serta
                                    pelengkap riwayat kompetensi.
                                </p>
                            </div>
                        </Link>

                        {/* Penghargaan */}
                        <Link
                            href={toUrl(
                                PenghargaanController.index(pegawai.id),
                            )}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Trophy className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Penghargaan
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Daftar rekapan satyalancana karya satya dan
                                    penghargaan lainnya.
                                </p>
                            </div>
                        </Link>

                        {/* Hukuman Disiplin */}
                        <Link
                            href={toUrl(
                                HukumanDisiplinController.index(pegawai.id),
                            )}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-destructive/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                                <Scale className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Hukuman Disiplin
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Data riwayat teguran tertulis atau
                                    pemotongan terkait tata ruang disiplin.
                                </p>
                            </div>
                        </Link>

                        {/* Dokumen */}
                        <Link
                            href={toUrl(
                                DokumenPegawaiController.index(pegawai.id),
                            )}
                            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/40 hover:bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <FileText className="h-5 w-5" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-base font-semibold">
                                    Dokumen Digital
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Kelola berkas terverifikasi, PDF SK legal,
                                    dan semua arsip digital personal.
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

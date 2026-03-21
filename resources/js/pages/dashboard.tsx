import { Head, Link } from '@inertiajs/react';
import {
    Users,
    AlertCircle,
    TrendingUp,
    Building2,
    UserCircle,
    UserPlus,
    Briefcase,
    GraduationCap,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface DashboardStats {
    total_pegawai_aktif: number;
    distribusi_golongan: Record<string, number>;
    distribusi_unit_kerja: Array<{ nama: string; pegawai_count: number }>;
    distribusi_jenis_kelamin: Array<{ jenis_kelamin: string; total: number }>;
    kgb_segera_count: number;
    kp_eligible_count: number;
    distribusi_jabatan: Array<{ nama: string; pegawai_count: number }>;
    distribusi_pendidikan: Array<{ pendidikan: string; pegawai_count: number }>;
    pegawai_baru_bulan_ini: number;
}

interface Props {
    stats: DashboardStats;
}

export default function Dashboard({ stats }: Props) {
    // Calculate percentages for Golongan
    const totalGolongan = Object.values(stats.distribusi_golongan).reduce(
        (a, b) => a + b,
        0,
    );

    // Calculate percentages for Jenis Kelamin
    const totalJK = stats.distribusi_jenis_kelamin.reduce(
        (a, b) => a + b.total,
        0,
    );

    // Calculate max unit kerja for progress bar scaling
    const maxUnitKerja =
        stats.distribusi_unit_kerja.length > 0
            ? Math.max(
                  ...stats.distribusi_unit_kerja.map((u) => u.pegawai_count),
              )
            : 0;

    // Calculate max jabatan for progress bar scaling
    const maxJabatan =
        stats.distribusi_jabatan.length > 0
            ? Math.max(...stats.distribusi_jabatan.map((j) => j.pegawai_count))
            : 0;

    // Calculate max pendidikan for progress bar scaling
    const maxPendidikan =
        stats.distribusi_pendidikan.length > 0
            ? Math.max(
                  ...stats.distribusi_pendidikan.map((p) => p.pegawai_count),
              )
            : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                {/* Top Cards Row */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Pegawai Aktif
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_pegawai_aktif}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pegawai dengan status aktif
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pegawai Baru (Bulan Ini)
                            </CardTitle>
                            <UserPlus className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.pegawai_baru_bulan_ini}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pegawai masuk bulan ini
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                KGB Segera (≤2 bln)
                            </CardTitle>
                            <AlertCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    {stats.kgb_segera_count}
                                </div>
                                {stats.kgb_segera_count > 0 && (
                                    <Badge variant="destructive">
                                        Perlu Perhatian
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                <Link
                                    href="/kepegawaian/monitoring/kgb"
                                    className="text-primary hover:underline"
                                >
                                    Lihat Monitoring KGB
                                </Link>
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                KP Eligible
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    {stats.kp_eligible_count}
                                </div>
                                {stats.kp_eligible_count > 0 && (
                                    <Badge
                                        variant="default"
                                        className="bg-blue-600 hover:bg-blue-700"
                                    >
                                        Eligible
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                <Link
                                    href="/kepegawaian/monitoring/kenaikan-pangkat"
                                    className="text-primary hover:underline"
                                >
                                    Lihat Monitoring KP
                                </Link>
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Distribution Row */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {/* Golongan Distribution */}
                    <Card className="col-span-1">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserCircle className="h-5 w-5" />
                                Distribusi Golongan
                            </CardTitle>
                            <CardDescription>
                                Berdasarkan pangkat terakhir
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {['I', 'II', 'III', 'IV'].map((gol) => {
                                const count =
                                    stats.distribusi_golongan[gol] || 0;
                                const percentage =
                                    totalGolongan > 0
                                        ? Math.round(
                                              (count / totalGolongan) * 100,
                                          )
                                        : 0;

                                return (
                                    <div key={gol} className="space-y-1">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium">
                                                Golongan {gol}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {count} ({percentage}%)
                                            </span>
                                        </div>
                                        <Progress
                                            value={percentage}
                                            className="h-2"
                                        />
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    {/* Unit Kerja Distribution */}
                    <Card className="col-span-1 lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Top Unit Kerja
                            </CardTitle>
                            <CardDescription>
                                Berdasarkan jumlah pegawai aktif
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {stats.distribusi_unit_kerja.length > 0 ? (
                                stats.distribusi_unit_kerja.map((unit, idx) => {
                                    const percentage =
                                        maxUnitKerja > 0
                                            ? (unit.pegawai_count /
                                                  maxUnitKerja) *
                                              100
                                            : 0;

                                    return (
                                        <div key={idx} className="space-y-1">
                                            <div className="flex items-center justify-between text-sm">
                                                <span
                                                    className="truncate pr-4 font-medium"
                                                    title={unit.nama}
                                                >
                                                    {unit.nama}
                                                </span>
                                                <span className="whitespace-nowrap text-muted-foreground">
                                                    {unit.pegawai_count} pegawai
                                                </span>
                                            </div>
                                            <Progress
                                                value={percentage}
                                                className="h-2"
                                            />
                                        </div>
                                    );
                                })
                            ) : (
                                <div className="py-4 text-center text-sm text-muted-foreground">
                                    Belum ada data unit kerja
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Jabatan Distribution */}
                    <Card className="col-span-1 lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5" />
                                Top Jabatan
                            </CardTitle>
                            <CardDescription>
                                Berdasarkan jumlah pegawai aktif
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {stats.distribusi_jabatan.length > 0 ? (
                                stats.distribusi_jabatan.map((jabatan, idx) => {
                                    const percentage =
                                        maxJabatan > 0
                                            ? (jabatan.pegawai_count /
                                                  maxJabatan) *
                                              100
                                            : 0;

                                    return (
                                        <div key={idx} className="space-y-1">
                                            <div className="flex items-center justify-between text-sm">
                                                <span
                                                    className="truncate pr-4 font-medium"
                                                    title={jabatan.nama}
                                                >
                                                    {jabatan.nama}
                                                </span>
                                                <span className="whitespace-nowrap text-muted-foreground">
                                                    {jabatan.pegawai_count}{' '}
                                                    pegawai
                                                </span>
                                            </div>
                                            <Progress
                                                value={percentage}
                                                className="h-2"
                                            />
                                        </div>
                                    );
                                })
                            ) : (
                                <div className="py-4 text-center text-sm text-muted-foreground">
                                    Belum ada data jabatan
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Pendidikan Distribution */}
                    <Card className="col-span-1">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <GraduationCap className="h-5 w-5" />
                                Distribusi Pendidikan
                            </CardTitle>
                            <CardDescription>
                                Berdasarkan pendidikan terakhir
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {stats.distribusi_pendidikan.length > 0 ? (
                                stats.distribusi_pendidikan.map(
                                    (pendidikan, idx) => {
                                        const percentage =
                                            maxPendidikan > 0
                                                ? (pendidikan.pegawai_count /
                                                      maxPendidikan) *
                                                  100
                                                : 0;

                                        return (
                                            <div
                                                key={idx}
                                                className="space-y-1"
                                            >
                                                <div className="flex items-center justify-between text-sm">
                                                    <span
                                                        className="truncate pr-4 font-medium"
                                                        title={
                                                            pendidikan.pendidikan
                                                        }
                                                    >
                                                        {pendidikan.pendidikan}
                                                    </span>
                                                    <span className="whitespace-nowrap text-muted-foreground">
                                                        {
                                                            pendidikan.pegawai_count
                                                        }{' '}
                                                        pegawai
                                                    </span>
                                                </div>
                                                <Progress
                                                    value={percentage}
                                                    className="h-2"
                                                />
                                            </div>
                                        );
                                    },
                                )
                            ) : (
                                <div className="py-4 text-center text-sm text-muted-foreground">
                                    Belum ada data pendidikan
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Jenis Kelamin Distribution */}
                    <Card className="col-span-1 md:col-span-2 lg:col-span-3">
                        <CardHeader>
                            <CardTitle>Distribusi Jenis Kelamin</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                {stats.distribusi_jenis_kelamin.length > 0 ? (
                                    stats.distribusi_jenis_kelamin.map(
                                        (jk, idx) => {
                                            const percentage =
                                                totalJK > 0
                                                    ? Math.round(
                                                          (jk.total / totalJK) *
                                                              100,
                                                      )
                                                    : 0;
                                            const label =
                                                jk.jenis_kelamin === 'L'
                                                    ? 'Laki-laki'
                                                    : jk.jenis_kelamin === 'P'
                                                      ? 'Perempuan'
                                                      : jk.jenis_kelamin;

                                            return (
                                                <div
                                                    key={idx}
                                                    className="flex items-center rounded-lg border p-4"
                                                >
                                                    <div className="flex-1 space-y-1">
                                                        <p className="text-sm leading-none font-medium">
                                                            {label}
                                                        </p>
                                                        <p className="text-2xl font-bold">
                                                            {jk.total}
                                                        </p>
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="text-sm text-muted-foreground">
                                                            {percentage}%
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        },
                                    )
                                ) : (
                                    <div className="col-span-2 py-4 text-center text-sm text-muted-foreground">
                                        Belum ada data jenis kelamin
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

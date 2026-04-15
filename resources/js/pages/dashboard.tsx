import { Head, Link } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { NumberTicker } from '@/components/ui/number-ticker';
import { BorderBeam } from '@/components/ui/border-beam';
import { BlurFade } from '@/components/ui/blur-fade';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { useDashboardStats } from '@/hooks/use-dashboard-stats';
import type { DashboardStats } from '@/hooks/use-dashboard-stats';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface Props {
    stats: DashboardStats;
}

export default function Dashboard({ stats }: Props) {
    const {
        golonganItems,
        unitKerjaItems,
        jabatanItems,
        pendidikanItems,
        jenisKelaminItems,
    } = useDashboardStats(stats);

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
                            <Users className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                <NumberTicker value={stats.total_pegawai_aktif} />
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
                            <UserPlus className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                <NumberTicker value={stats.pegawai_baru_bulan_ini} />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pegawai masuk bulan ini
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden">
                        <BorderBeam />
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                KGB Segera (≤2 bln)
                            </CardTitle>
                            <AlertCircle className="h-4 w-4 text-destructive" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    <NumberTicker value={stats.kgb_segera_count} />
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

                    <Card className="relative overflow-hidden">
                        <BorderBeam />
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                KP Eligible
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    <NumberTicker value={stats.kp_eligible_count} />
                                </div>
                                {stats.kp_eligible_count > 0 && (
                                    <Badge
                                        variant="default"
                                        className="bg-accent hover:bg-accent/90"
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
                    <BlurFade delay={0.1} className="col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <UserCircle className="h-5 w-5 text-accent" />
                                    Distribusi Golongan
                                </CardTitle>
                                <CardDescription>
                                    Berdasarkan pangkat terakhir
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {golonganItems.map((item) => (
                                    <div key={item.golongan} className="space-y-1">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium">
                                                Golongan {item.golongan}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {item.count} ({item.percentage}%)
                                            </span>
                                        </div>
                                        <Progress
                                            value={item.percentage}
                                            className="h-2"
                                        />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </BlurFade>

                    {/* Unit Kerja Distribution */}
                    <BlurFade delay={0.2} className="col-span-1 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="h-5 w-5 text-accent" />
                                    Top Unit Kerja
                                </CardTitle>
                                <CardDescription>
                                    Berdasarkan jumlah pegawai aktif
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {unitKerjaItems.length > 0 ? (
                                    unitKerjaItems.map((item, idx) => (
                                        <div key={idx} className="space-y-1">
                                            <div className="flex items-center justify-between text-sm">
                                                <span
                                                    className="truncate pr-4 font-medium"
                                                    title={item.nama}
                                                >
                                                    {item.nama}
                                                </span>
                                                <span className="whitespace-nowrap text-muted-foreground">
                                                    {item.count} pegawai
                                                </span>
                                            </div>
                                            <Progress
                                                value={item.percentage}
                                                className="h-2"
                                            />
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-4 text-center text-sm text-muted-foreground">
                                        Belum ada data unit kerja
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </BlurFade>

                    {/* Jabatan Distribution */}
                    <BlurFade delay={0.3} className="col-span-1 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Briefcase className="h-5 w-5 text-accent" />
                                    Top Jabatan
                                </CardTitle>
                                <CardDescription>
                                    Berdasarkan jumlah pegawai aktif
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {jabatanItems.length > 0 ? (
                                    jabatanItems.map((item, idx) => (
                                        <div key={idx} className="space-y-1">
                                            <div className="flex items-center justify-between text-sm">
                                                <span
                                                    className="truncate pr-4 font-medium"
                                                    title={item.nama}
                                                >
                                                    {item.nama}
                                                </span>
                                                <span className="whitespace-nowrap text-muted-foreground">
                                                    {item.count} pegawai
                                                </span>
                                            </div>
                                            <Progress
                                                value={item.percentage}
                                                className="h-2"
                                            />
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-4 text-center text-sm text-muted-foreground">
                                        Belum ada data jabatan
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </BlurFade>

                    {/* Pendidikan Distribution */}
                    <BlurFade delay={0.4} className="col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <GraduationCap className="h-5 w-5 text-accent" />
                                    Distribusi Pendidikan
                                </CardTitle>
                                <CardDescription>
                                    Berdasarkan pendidikan terakhir
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {pendidikanItems.length > 0 ? (
                                    pendidikanItems.map((item, idx) => (
                                        <div key={idx} className="space-y-1">
                                            <div className="flex items-center justify-between text-sm">
                                                <span
                                                    className="truncate pr-4 font-medium"
                                                    title={item.pendidikan}
                                                >
                                                    {item.pendidikan}
                                                </span>
                                                <span className="whitespace-nowrap text-muted-foreground">
                                                    {item.count} pegawai
                                                </span>
                                            </div>
                                            <Progress
                                                value={item.percentage}
                                                className="h-2"
                                            />
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-4 text-center text-sm text-muted-foreground">
                                        Belum ada data pendidikan
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </BlurFade>

                    {/* Jenis Kelamin Distribution */}
                    <BlurFade delay={0.5} className="col-span-1 md:col-span-2 lg:col-span-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>Distribusi Jenis Kelamin</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {jenisKelaminItems.length > 0 ? (
                                        jenisKelaminItems.map((item, idx) => (
                                            <div
                                                key={idx}
                                                className="flex items-center rounded-lg border p-4"
                                            >
                                                <div className="flex-1 space-y-1">
                                                    <p className="text-sm leading-none font-medium">
                                                        {item.label}
                                                    </p>
                                                    <p className="text-2xl font-bold">
                                                        <NumberTicker value={item.total} />
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <div className="text-sm text-muted-foreground">
                                                        {item.percentage}%
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="col-span-2 py-4 text-center text-sm text-muted-foreground">
                                            Belum ada data jenis kelamin
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </BlurFade>
                </div>
            </div>
        </AppLayout>
    );
}

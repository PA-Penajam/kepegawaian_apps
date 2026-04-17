import { usePage } from '@inertiajs/react';
import {
    Briefcase, Building2, GraduationCap, UserCircle,
} from 'lucide-react';
import {
    Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card';
import { NumberTicker } from '@/components/ui/number-ticker';
import { Progress } from '@/components/ui/progress';
import { BlurFade } from '@/components/ui/blur-fade';
import { useDashboardStats } from '@/hooks/use-dashboard-stats';
import type { HeavyDashboardStats } from '@/hooks/use-dashboard-stats';

export function DashboardHeavySection() {
    const { heavyStats } = usePage<{ heavyStats: HeavyDashboardStats }>().props;
    const {
        golonganItems, unitKerjaItems, jabatanItems, pendidikanItems, jenisKelaminItems,
    } = useDashboardStats(heavyStats);

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <BlurFade delay={0.1} className="col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserCircle className="h-5 w-5 text-accent" />
                            Distribusi Golongan
                        </CardTitle>
                        <CardDescription>Berdasarkan pangkat terakhir</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {golonganItems.map((item) => (
                            <div key={item.golongan} className="space-y-1">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium">Golongan {item.golongan}</span>
                                    <span className="text-muted-foreground">
                                        {item.count} ({item.percentage}%)
                                    </span>
                                </div>
                                <Progress value={item.percentage} className="h-2" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.2} className="col-span-1 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5 text-accent" />
                            Top Unit Kerja
                        </CardTitle>
                        <CardDescription>Berdasarkan jumlah pegawai aktif</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {unitKerjaItems.length > 0 ? (
                            unitKerjaItems.map((item, idx) => (
                                <div key={idx} className="space-y-1">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="truncate pr-4 font-medium" title={item.nama}>{item.nama}</span>
                                        <span className="whitespace-nowrap text-muted-foreground">{item.count} pegawai</span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data unit kerja</p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.3} className="col-span-1 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Briefcase className="h-5 w-5 text-accent" />
                            Top Jabatan
                        </CardTitle>
                        <CardDescription>Berdasarkan jumlah pegawai aktif</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {jabatanItems.length > 0 ? (
                            jabatanItems.map((item, idx) => (
                                <div key={idx} className="space-y-1">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="truncate pr-4 font-medium" title={item.nama}>{item.nama}</span>
                                        <span className="whitespace-nowrap text-muted-foreground">{item.count} pegawai</span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data jabatan</p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.4} className="col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GraduationCap className="h-5 w-5 text-accent" />
                            Distribusi Pendidikan
                        </CardTitle>
                        <CardDescription>Berdasarkan pendidikan terakhir</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {pendidikanItems.length > 0 ? (
                            pendidikanItems.map((item, idx) => (
                                <div key={idx} className="space-y-1">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="truncate pr-4 font-medium">{item.pendidikan}</span>
                                        <span className="whitespace-nowrap text-muted-foreground">{item.count} pegawai</span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data pendidikan</p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.5} className="col-span-1 md:col-span-2 lg:col-span-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Distribusi Jenis Kelamin</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            {jenisKelaminItems.length > 0 ? (
                                jenisKelaminItems.map((item, idx) => (
                                    <div key={idx} className="flex items-center rounded-lg border p-4">
                                        <div className="flex-1 space-y-1">
                                            <p className="text-sm leading-none font-medium">{item.label}</p>
                                            <p className="text-2xl font-bold">
                                                <NumberTicker value={item.total} />
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-sm text-muted-foreground">{item.percentage}%</div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="col-span-2 py-4 text-center text-sm text-muted-foreground">Belum ada data jenis kelamin</p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </BlurFade>
        </div>
    );
}

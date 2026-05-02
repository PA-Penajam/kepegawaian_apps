import { Deferred, Head, Link } from '@inertiajs/react';
import { AlertCircle, TrendingUp, UserPlus, Users } from 'lucide-react';
import { DashboardDistribusiSkeleton } from '@/components/dashboard/DashboardDistribusiSkeleton';
import { DashboardHeader } from '@/components/dashboard/DashboardHeader';
import { DashboardHeavySection } from '@/components/dashboard/DashboardHeavySection';
import { Badge } from '@/components/ui/badge';
import { BorderBeam } from '@/components/ui/border-beam';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NumberTicker } from '@/components/ui/number-ticker';
import type { FastDashboardStats } from '@/hooks/use-dashboard-stats';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

interface Props {
    fastStats: FastDashboardStats;
}

export default function Dashboard({ fastStats }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <DashboardHeader />
                
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Pegawai Aktif</CardTitle>
                            <Users className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                <NumberTicker value={fastStats.total_pegawai_aktif} />
                            </div>
                            <p className="text-xs text-muted-foreground">Pegawai dengan status aktif</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pegawai Baru (Bulan Ini)</CardTitle>
                            <UserPlus className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                <NumberTicker value={fastStats.pegawai_baru_bulan_ini} />
                            </div>
                            <p className="text-xs text-muted-foreground">Pegawai masuk bulan ini</p>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden">
                        <BorderBeam />
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">KGB Segera (≤2 bln)</CardTitle>
                            <AlertCircle className="h-4 w-4 text-destructive" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    <NumberTicker value={fastStats.kgb_segera_count} />
                                </div>
                                {fastStats.kgb_segera_count > 0 && (
                                    <Badge variant="destructive">Perlu Perhatian</Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                <Link href="/kepegawaian/monitoring/kgb" className="text-primary hover:underline">
                                    Lihat Monitoring KGB
                                </Link>
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden">
                        <BorderBeam />
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">KP Eligible</CardTitle>
                            <TrendingUp className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    <NumberTicker value={fastStats.kp_eligible_count} />
                                </div>
                                {fastStats.kp_eligible_count > 0 && (
                                    <Badge variant="default" className="bg-accent hover:bg-accent/90">Eligible</Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                <Link href="/kepegawaian/monitoring/kenaikan-pangkat" className="text-primary hover:underline">
                                    Lihat Monitoring KP
                                </Link>
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Deferred data="heavyStats" fallback={<DashboardDistribusiSkeleton />}>
                    <DashboardHeavySection />
                </Deferred>
            </div>
        </AppLayout>
    );
}

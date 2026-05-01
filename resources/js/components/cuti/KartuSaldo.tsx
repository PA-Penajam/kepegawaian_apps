import { CalendarDays } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import type { SaldoBucketData } from '@/types/cuti';

type Props = {
    saldo: SaldoBucketData;
};

/**
 * Kartu saldo cuti tahunan (CT).
 * Menampilkan sisa saldo berdasarkan hak awal dinamis dan progress bar.
 */
export function KartuSaldo({ saldo }: Props) {
    // Hak awal diambil dari prop agar mendukung pro-rata (CPNS) dan carry-over
    const hakAwal = saldo.hak_awal;
    const tersedia = saldo.CT;
    const terpakai = hakAwal - tersedia;
    const persentase = hakAwal > 0 ? Math.round((tersedia / hakAwal) * 100) : 0;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Cuti Tahunan {saldo.tahun}</CardTitle>
                <CalendarDays className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{tersedia} hari</div>
                <p className="mb-3 text-xs text-muted-foreground">
                    Terpakai {terpakai} dari {hakAwal} hari
                </p>
                <Progress value={persentase} className="h-2" />
            </CardContent>
        </Card>
    );
}

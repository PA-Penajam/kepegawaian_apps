import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import RiwayatPangkatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPangkatController';
import { DetailTabCard } from './detail-tab-card';

export function RiwayatPangkatTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Pangkat/Golongan', 'TMT', 'Masa Kerja', 'No. SK', 'Status'];
    const isEmpty = !pegawai.riwayat_pangkat?.length;

    return (
        <DetailTabCard
            title="Riwayat Pangkat"
            description="Daftar riwayat kepangkatan pegawai."
            manageUrl={toUrl(RiwayatPangkatController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada riwayat pangkat."
            isEmpty={isEmpty}
            colSpan={5}
        >
            {pegawai.riwayat_pangkat?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {item.pangkat
                            ? `${item.pangkat.nama} (${item.pangkat.golongan}/${item.pangkat.ruang})`
                            : '-'}
                    </TableCell>
                    <TableCell>
                        {item.tmt ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.masa_kerja_tahun} thn{' '}
                        {item.masa_kerja_bulan} bln
                    </TableCell>
                    <TableCell>
                        <div>{item.no_sk}</div>
                        <div className="text-xs text-muted-foreground">
                            {item.tanggal_sk}
                        </div>
                    </TableCell>
                    <TableCell>
                        {item.is_aktif ? (
                            <Badge className="bg-emerald-500">
                                Aktif
                            </Badge>
                        ) : (
                            <Badge variant="outline">
                                Nonaktif
                            </Badge>
                        )}
                    </TableCell>
                </TableRow>
            ))}
        </DetailTabCard>
    );
}

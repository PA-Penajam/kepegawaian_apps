import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import RiwayatJabatanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatJabatanController';
import { DetailTabCard } from './detail-tab-card';

export function RiwayatJabatanTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Jabatan', 'Unit Kerja', 'TMT', 'No. SK', 'Status'];
    const isEmpty = !pegawai.riwayat_jabatan?.length;

    return (
        <DetailTabCard
            title="Riwayat Jabatan"
            description="Daftar riwayat jabatan pegawai."
            manageUrl={toUrl(RiwayatJabatanController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada riwayat jabatan."
            isEmpty={isEmpty}
            colSpan={5}
        >
            {pegawai.riwayat_jabatan?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {item.jabatan?.nama ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.unit_kerja?.nama ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.tmt ?? '-'}
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

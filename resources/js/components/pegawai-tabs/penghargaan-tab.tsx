import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import PenghargaanController from '@/actions/App/Http/Controllers/Kepegawaian/PenghargaanController';
import { DetailTabCard } from './detail-tab-card';

export function PenghargaanTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Nama Penghargaan', 'Jenis', 'Tahun', 'No. SK'];
    const isEmpty = !pegawai.penghargaan?.length;

    return (
        <DetailTabCard
            title="Penghargaan"
            description="Daftar penghargaan yang pernah diterima pegawai."
            manageUrl={toUrl(PenghargaanController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada data penghargaan."
            isEmpty={isEmpty}
            colSpan={4}
        >
            {pegawai.penghargaan?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {item.nama_penghargaan}
                    </TableCell>
                    <TableCell>
                        {item.jenis_penghargaan?.nama ??
                            '-'}
                    </TableCell>
                    <TableCell>
                        {item.tahun ?? '-'}
                    </TableCell>
                    <TableCell>
                        <div>{item.no_sk ?? '-'}</div>
                        <div className="text-xs text-muted-foreground">
                            {item.tanggal_sk}
                        </div>
                    </TableCell>
                </TableRow>
            ))}
        </DetailTabCard>
    );
}

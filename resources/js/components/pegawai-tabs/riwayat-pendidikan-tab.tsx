import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import { JenjangPendidikanLabels } from '@/types/kepegawaian';
import RiwayatPendidikanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPendidikanController';
import { DetailTabCard } from './detail-tab-card';

export function RiwayatPendidikanTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Tingkat', 'Nama Sekolah/Institusi', 'Jurusan', 'Tahun Lulus'];
    const isEmpty = !pegawai.riwayat_pendidikan?.length;

    return (
        <DetailTabCard
            title="Riwayat Pendidikan"
            description="Daftar riwayat pendidikan formal pegawai."
            manageUrl={toUrl(RiwayatPendidikanController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada riwayat pendidikan."
            isEmpty={isEmpty}
            colSpan={4}
        >
            {pegawai.riwayat_pendidikan?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {JenjangPendidikanLabels[
                            item.jenjang
                        ] ?? item.jenjang}
                    </TableCell>
                    <TableCell>
                        {item.nama_sekolah}
                    </TableCell>
                    <TableCell>
                        {item.jurusan ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.tahun_lulus}
                    </TableCell>
                </TableRow>
            ))}
        </DetailTabCard>
    );
}

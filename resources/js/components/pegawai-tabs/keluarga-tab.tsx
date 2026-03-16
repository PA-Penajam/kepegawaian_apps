import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import KeluargaController from '@/actions/App/Http/Controllers/Kepegawaian/KeluargaController';
import { DetailTabCard } from './detail-tab-card';

export function KeluargaTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Nama', 'Hubungan', 'Jenis Kelamin', 'Tanggal Lahir', 'Pekerjaan'];
    const isEmpty = !pegawai.keluarga?.length;

    return (
        <DetailTabCard
            title="Data Keluarga"
            description="Daftar anggota keluarga pegawai."
            manageUrl={toUrl(KeluargaController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada data keluarga."
            isEmpty={isEmpty}
            colSpan={5}
        >
            {pegawai.keluarga?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {item.nama}
                    </TableCell>
                    <TableCell>
                        {item.hubungan}
                    </TableCell>
                    <TableCell>
                        {item.jenis_kelamin}
                    </TableCell>
                    <TableCell>
                        {item.tanggal_lahir ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.pekerjaan ?? '-'}
                    </TableCell>
                </TableRow>
            ))}
        </DetailTabCard>
    );
}

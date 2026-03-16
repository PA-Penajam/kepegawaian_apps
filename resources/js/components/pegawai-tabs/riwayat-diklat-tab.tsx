import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import RiwayatDiklatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatDiklatController';
import { DetailTabCard } from './detail-tab-card';

export function RiwayatDiklatTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Nama Diklat', 'Jenis', 'Penyelenggara', 'Waktu Pelaksanaan', 'Durasi (JP)'];
    const isEmpty = !pegawai.riwayat_diklat?.length;

    return (
        <DetailTabCard
            title="Riwayat Diklat"
            description="Daftar riwayat pendidikan dan pelatihan pegawai."
            manageUrl={toUrl(RiwayatDiklatController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada riwayat diklat."
            isEmpty={isEmpty}
            colSpan={5}
        >
            {pegawai.riwayat_diklat?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {item.nama_diklat}
                    </TableCell>
                    <TableCell>
                        {item.jenis_diklat?.nama ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.penyelenggara}
                    </TableCell>
                    <TableCell>
                        {item.tanggal_mulai}{' '}
                        {item.tanggal_selesai
                            ? `s/d ${item.tanggal_selesai}`
                            : ''}
                    </TableCell>
                    <TableCell>
                        {item.jam_pelajaran ?? '-'}
                    </TableCell>
                </TableRow>
            ))}
        </DetailTabCard>
    );
}

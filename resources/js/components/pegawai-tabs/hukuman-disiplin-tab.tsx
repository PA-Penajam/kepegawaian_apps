import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import HukumanDisiplinController from '@/actions/App/Http/Controllers/Kepegawaian/HukumanDisiplinController';
import { DetailTabCard } from './detail-tab-card';

export function HukumanDisiplinTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Jenis Hukuman', 'Pelanggaran', 'TMT Berlaku', 'TMT Selesai', 'No. SK'];
    const isEmpty = !pegawai.hukuman_disiplin?.length;

    return (
        <DetailTabCard
            title="Hukuman Disiplin"
            description="Daftar hukuman disiplin pegawai."
            manageUrl={toUrl(HukumanDisiplinController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada data hukuman disiplin."
            isEmpty={isEmpty}
            colSpan={5}
        >
            {pegawai.hukuman_disiplin?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {item.jenis_hukuman_disiplin
                            ?.nama ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.pelanggaran}
                    </TableCell>
                    <TableCell>
                        {item.tmt_berlaku}
                    </TableCell>
                    <TableCell>
                        {item.tmt_selesai ??
                            'Masih Berlaku'}
                    </TableCell>
                    <TableCell>
                        <div>{item.no_sk}</div>
                        <div className="text-xs text-muted-foreground">
                            {item.tanggal_sk}
                        </div>
                    </TableCell>
                </TableRow>
            ))}
        </DetailTabCard>
    );
}

import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { toUrl } from '@/lib/utils';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import DokumenPegawaiController from '@/actions/App/Http/Controllers/Kepegawaian/DokumenPegawaiController';
import { DetailTabCard } from './detail-tab-card';

export function DokumenTab({ pegawai }: { pegawai: PegawaiDetail }) {
    const columns = ['Jenis Dokumen', 'Nomor Dokumen', 'Tanggal', 'File'];
    const isEmpty = !pegawai.dokumen_pegawai?.length;

    return (
        <DetailTabCard
            title="Dokumen Pegawai"
            description="Daftar dokumen digital pegawai."
            manageUrl={toUrl(DokumenPegawaiController.index(pegawai.id))}
            columns={columns}
            emptyMessage="Belum ada dokumen."
            isEmpty={isEmpty}
            colSpan={4}
        >
            {pegawai.dokumen_pegawai?.map((item) => (
                <TableRow key={item.id}>
                    <TableCell className="font-medium">
                        {item.jenis_dokumen}
                    </TableCell>
                    <TableCell>
                        {item.nomor_dokumen ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.tanggal_dokumen ?? '-'}
                    </TableCell>
                    <TableCell>
                        {item.file_path ? (
                            <Badge
                                variant="outline"
                                className="border-blue-200 bg-blue-50 text-blue-700"
                            >
                                Ada File
                            </Badge>
                        ) : (
                            <span className="text-muted-foreground">
                                -
                            </span>
                        )}
                    </TableCell>
                </TableRow>
            ))}
        </DetailTabCard>
    );
}

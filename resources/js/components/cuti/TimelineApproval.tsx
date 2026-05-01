import { CheckCircle2, Circle, Clock, XCircle } from 'lucide-react';
import { CutiStateLabels  } from '@/types/cuti';
import type { CutiStateHistory } from '@/types/cuti';
import type {CutiState} from '@/types/cuti';

type Props = {
    stateHistory: CutiStateHistory[];
};

/**
 * Ikon berdasarkan state tujuan pada history.
 */
function getStateIcon(stateTo: string) {
    if (stateTo.startsWith('DITOLAK') || stateTo === 'DIBATALKAN') {
        return <XCircle className="h-5 w-5 text-red-500" />;
    }

    if (stateTo === 'DISETUJUI') {
        return <CheckCircle2 className="h-5 w-5 text-green-500" />;
    }

    if (stateTo === 'DICABUT_SETELAH_DISETUJUI') {
        return <XCircle className="h-5 w-5 text-orange-500" />;
    }

    if (stateTo === 'DRAFT') {
        return <Circle className="h-5 w-5 text-gray-400" />;
    }

    return <Clock className="h-5 w-5 text-blue-500" />;
}

/**
 * Komponen timeline vertikal untuk menampilkan riwayat perubahan state pengajuan cuti.
 */
export function TimelineApproval({ stateHistory }: Props) {
    if (!stateHistory || stateHistory.length === 0) {
        return <p className="text-sm text-muted-foreground">Belum ada riwayat.</p>;
    }

    return (
        <div className="relative space-y-0">
            {stateHistory.map((entry, index) => {
                const isLast = index === stateHistory.length - 1;
                const label = CutiStateLabels[entry.state_to as CutiState] ?? entry.state_to;

                return (
                    <div key={entry.id} className="relative flex gap-4 pb-6">
                        {/* Garis vertikal penghubung */}
                        {!isLast && (
                            <div className="absolute left-[9px] top-6 h-full w-0.5 bg-border" />
                        )}

                        {/* Ikon state */}
                        <div className="relative z-10 shrink-0">
                            {getStateIcon(entry.state_to)}
                        </div>

                        {/* Konten */}
                        <div className="flex-1 space-y-1">
                            <p className="text-sm font-semibold">{label}</p>
                            {entry.aktor && (
                                <p className="text-xs text-muted-foreground">
                                    oleh {entry.aktor.nama_lengkap}
                                </p>
                            )}
                            {entry.catatan && (
                                <p className="text-xs text-muted-foreground italic">
                                    &ldquo;{entry.catatan}&rdquo;
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {new Date(entry.created_at).toLocaleString('id-ID', {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                })}
                            </p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

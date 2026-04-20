import { Head, Link } from '@inertiajs/react';
import { Award, Briefcase, Building } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { index as selfServiceIndex } from '@/routes/self-service';
import type { BreadcrumbItem } from '@/types';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import { toUrl } from '@/lib/utils';

// Controller Actions untuk redirect
import PegawaiController from '@/actions/App/Http/Controllers/Kepegawaian/PegawaiController';
import KeluargaController from '@/actions/App/Http/Controllers/Kepegawaian/KeluargaController';
import RiwayatJabatanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatJabatanController';
import RiwayatPangkatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPangkatController';
import RiwayatPendidikanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPendidikanController';
import RiwayatDiklatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatDiklatController';
import PenghargaanController from '@/actions/App/Http/Controllers/Kepegawaian/PenghargaanController';
import HukumanDisiplinController from '@/actions/App/Http/Controllers/Kepegawaian/HukumanDisiplinController';
import DokumenPegawaiController from '@/actions/App/Http/Controllers/Kepegawaian/DokumenPegawaiController';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Data Saya', href: '/self-service' },
    { title: 'Detail', href: '/self-service/detail' },
];

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((item) => item[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

export default function SelfServiceDetail({
    pegawai,
}: {
    pegawai: PegawaiDetail;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Launcher Menu Data Pegawai" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:max-w-6xl lg:mx-auto lg:w-full">
                {/* Header Profil (Bergaya Sedikit Retro) */}
                <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between bg-card text-card-foreground p-6 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] rounded-xl">
                    <div className="flex items-center gap-6">
                        <Avatar className="h-20 w-20 border-2 border-foreground drop-shadow-[2px_2px_0_rgba(0,0,0,1)]">
                            <AvatarImage
                                src={pegawai.foto_url ?? undefined}
                                alt={pegawai.nama_lengkap}
                            />
                            <AvatarFallback className="text-2xl bg-yellow-400 font-black text-black">
                                {getInitials(pegawai.nama_lengkap)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="space-y-1.5">
                            <h1 className="text-2xl font-black tracking-tight">
                                {pegawai.nama_lengkap}
                            </h1>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-foreground/80">
                                <Badge
                                    variant="secondary"
                                    className="font-bold border-2 border-foreground"
                                >
                                    NIP: {pegawai.nip ?? '-'}
                                </Badge>
                                <span className="flex items-center gap-1 font-semibold">
                                    <Briefcase className="h-4 w-4" />
                                    {pegawai.jabatan?.nama ??
                                        'Belum ada jabatan'}
                                </span>
                                <span className="hidden md:inline font-bold">•</span>
                                <span className="flex items-center gap-1 font-semibold">
                                    <Building className="h-4 w-4" />
                                    {pegawai.unit_kerja?.nama ??
                                        'Belum ada unit kerja'}
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-foreground/80 font-semibold">
                                <span className="flex items-center gap-1">
                                    <Award className="h-4 w-4" />
                                    {pegawai.pangkat
                                        ? `${pegawai.pangkat.nama} (${pegawai.pangkat.golongan}/${pegawai.pangkat.ruang})`
                                        : 'Belum ada pangkat'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <Link 
                            href={selfServiceIndex()} 
                            className="inline-flex h-10 items-center justify-center rounded-xl bg-background px-4 py-2 text-sm font-bold border-2 border-foreground drop-shadow-[2px_2px_0_rgba(0,0,0,1)] hover:bg-accent hover:text-accent-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Kembali
                        </Link>
                    </div>
                </div>

                <div className="mt-4">
                    <h2 className="text-xl font-black mb-6 uppercase tracking-tighter border-b-4 border-foreground inline-block pb-1">
                        Menu Pengelolaan Data
                    </h2>

                    {/* Retro Grid App Launcher */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 pb-12">
                        
                        {/* Biodata */}
                        <Link 
                            href={toUrl(PegawaiController.edit(pegawai.id))}
                            className="group block bg-[#facc15] dark:bg-yellow-600 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">📝</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2 group-hover:text-foreground">Biodata Pribadi</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Ubah data personal, kontak, dan sinkronisasi alamat domisili.</p>
                        </Link>

                        {/* Keluarga */}
                        <Link 
                            href={toUrl(KeluargaController.index(pegawai.id))}
                            className="group block bg-[#fce7f3] dark:bg-pink-900 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">👨‍👩‍👧</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Keluarga</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Pengajuan sinkronisasi data pasangan & anak tanggungan.</p>
                        </Link>

                        {/* Riwayat Jabatan */}
                        <Link 
                            href={toUrl(RiwayatJabatanController.index(pegawai.id))}
                            className="group block bg-[#dcfce3] dark:bg-green-900 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">💼</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Riwayat Jabatan</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Kelola riwayat mutasi, fungsional, dan promosi pelantikan jabatan.</p>
                        </Link>

                        {/* Riwayat Pangkat */}
                        <Link 
                            href={toUrl(RiwayatPangkatController.index(pegawai.id))}
                            className="group block bg-[#e0e7ff] dark:bg-indigo-900 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">⭐</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Riwayat Pangkat</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Update golongan, pencantuman gelar, dan kenaikan pangkat.</p>
                        </Link>

                        {/* Pendidikan */}
                        <Link 
                            href={toUrl(RiwayatPendidikanController.index(pegawai.id))}
                            className="group block bg-[#ffedd5] dark:bg-orange-900 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">🎓</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Pendidikan</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Pengajuan gelar akademik formal atau penyesuaian ijazah.</p>
                        </Link>

                        {/* Diklat */}
                        <Link 
                            href={toUrl(RiwayatDiklatController.index(pegawai.id))}
                            className="group block bg-[#dbeafe] dark:bg-blue-900 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">📜</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Riwayat Diklat</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Sertifikasi, kursus kompetensi teknis, dan diklat kepemimpinan.</p>
                        </Link>

                        {/* Penghargaan */}
                        <Link 
                            href={toUrl(PenghargaanController.index(pegawai.id))}
                            className="group block bg-[#fae8ff] dark:bg-fuchsia-900 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">🏆</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Penghargaan</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Satyalancana karya satya dan validasi tanda kehormatan lainnya.</p>
                        </Link>

                        {/* Hukuman Disiplin */}
                        <Link 
                            href={toUrl(HukumanDisiplinController.index(pegawai.id))}
                            className="group block bg-[#fee2e2] dark:bg-red-900 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">⚖️</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Hukuman Disiplin</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Riwayat teguran, pemotongan, hingga pemberhentian kedisiplinan.</p>
                        </Link>

                        {/* Dokumen */}
                        <Link 
                            href={toUrl(DokumenPegawaiController.index(pegawai.id))}
                            className="group block bg-[#f3f4f6] dark:bg-gray-800 border-2 border-foreground drop-shadow-[4px_4px_0_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] rounded-xl p-6 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="text-4xl mb-4 group-hover:scale-110 transition-transform origin-bottom-left">📁</div>
                            <h3 className="text-xl font-black mb-2 border-b-2 border-foreground/30 pb-2">Dokumen Digital</h3>
                            <p className="text-sm font-semibold text-foreground/80 leading-tight">Arsip elektronik, upload PDF berkas, dan penarikan dokumen fisik.</p>
                        </Link>

                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

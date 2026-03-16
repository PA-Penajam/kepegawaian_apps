import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import {
    BiodataTab,
    KeluargaTab,
    RiwayatPangkatTab,
    RiwayatJabatanTab,
    RiwayatPendidikanTab,
    RiwayatDiklatTab,
    PenghargaanTab,
    HukumanDisiplinTab,
    DokumenTab,
} from '@/components/pegawai-tabs';

export function PegawaiDetailTabs({ pegawai }: { pegawai: PegawaiDetail }) {
    return (
        <Tabs defaultValue="biodata" className="w-full">
            <div className="overflow-x-auto pb-2">
                <TabsList className="w-full justify-start sm:w-auto">
                    <TabsTrigger value="biodata">Biodata</TabsTrigger>
                    <TabsTrigger value="keluarga">Keluarga</TabsTrigger>
                    <TabsTrigger value="riwayat-pangkat">
                        Riwayat Pangkat
                    </TabsTrigger>
                    <TabsTrigger value="riwayat-jabatan">
                        Riwayat Jabatan
                    </TabsTrigger>
                    <TabsTrigger value="riwayat-pendidikan">
                        Riwayat Pendidikan
                    </TabsTrigger>
                    <TabsTrigger value="riwayat-diklat">
                        Riwayat Diklat
                    </TabsTrigger>
                    <TabsTrigger value="penghargaan">Penghargaan</TabsTrigger>
                    <TabsTrigger value="hukuman-disiplin">
                        Hukuman Disiplin
                    </TabsTrigger>
                    <TabsTrigger value="dokumen">Dokumen</TabsTrigger>
                </TabsList>
            </div>

            <TabsContent value="biodata" className="mt-4 space-y-6">
                <BiodataTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="keluarga" className="mt-4">
                <KeluargaTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="riwayat-pangkat" className="mt-4">
                <RiwayatPangkatTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="riwayat-jabatan" className="mt-4">
                <RiwayatJabatanTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="riwayat-pendidikan" className="mt-4">
                <RiwayatPendidikanTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="riwayat-diklat" className="mt-4">
                <RiwayatDiklatTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="penghargaan" className="mt-4">
                <PenghargaanTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="hukuman-disiplin" className="mt-4">
                <HukumanDisiplinTab pegawai={pegawai} />
            </TabsContent>

            <TabsContent value="dokumen" className="mt-4">
                <DokumenTab pegawai={pegawai} />
            </TabsContent>
        </Tabs>
    );
}

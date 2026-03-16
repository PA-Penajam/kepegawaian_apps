import { useMemo } from 'react';

interface DashboardStats {
    total_pegawai_aktif: number;
    distribusi_golongan: Record<string, number>;
    distribusi_unit_kerja: Array<{ nama: string; pegawai_count: number }>;
    distribusi_jenis_kelamin: Array<{ jenis_kelamin: string; total: number }>;
    kgb_segera_count: number;
    kp_eligible_count: number;
    distribusi_jabatan: Array<{ nama: string; pegawai_count: number }>;
    distribusi_pendidikan: Array<{ pendidikan: string; pegawai_count: number }>;
    pegawai_baru_bulan_ini: number;
}

interface GolonganItem {
    golongan: string;
    count: number;
    percentage: number;
}

interface DistribusiBarItem {
    nama: string;
    count: number;
    percentage: number;
}

interface PendidikanBarItem {
    pendidikan: string;
    count: number;
    percentage: number;
}

interface JenisKelaminItem {
    label: string;
    total: number;
    percentage: number;
}

interface DashboardComputedStats {
    totalGolongan: number;
    totalJK: number;
    golonganItems: GolonganItem[];
    unitKerjaItems: DistribusiBarItem[];
    jabatanItems: DistribusiBarItem[];
    pendidikanItems: PendidikanBarItem[];
    jenisKelaminItems: JenisKelaminItem[];
}

function pct(value: number, total: number): number {
    return total > 0 ? Math.round((value / total) * 100) : 0;
}

function barPct(value: number, max: number): number {
    return max > 0 ? (value / max) * 100 : 0;
}

function jkLabel(code: string): string {
    if (code === 'L') return 'Laki-laki';
    if (code === 'P') return 'Perempuan';
    return code;
}

export function useDashboardStats(
    stats: DashboardStats,
): DashboardComputedStats {
    return useMemo(() => {
        const totalGolongan = Object.values(stats.distribusi_golongan).reduce(
            (a, b) => a + b,
            0,
        );

        const totalJK = stats.distribusi_jenis_kelamin.reduce(
            (a, b) => a + b.total,
            0,
        );

        const maxUnitKerja =
            stats.distribusi_unit_kerja.length > 0
                ? Math.max(
                      ...stats.distribusi_unit_kerja.map(
                          (u) => u.pegawai_count,
                      ),
                  )
                : 0;

        const maxJabatan =
            stats.distribusi_jabatan.length > 0
                ? Math.max(
                      ...stats.distribusi_jabatan.map((j) => j.pegawai_count),
                  )
                : 0;

        const maxPendidikan =
            stats.distribusi_pendidikan.length > 0
                ? Math.max(
                      ...stats.distribusi_pendidikan.map(
                          (p) => p.pegawai_count,
                      ),
                  )
                : 0;

        const golonganItems: GolonganItem[] = ['I', 'II', 'III', 'IV'].map(
            (gol) => {
                const count = stats.distribusi_golongan[gol] || 0;
                return {
                    golongan: gol,
                    count,
                    percentage: pct(count, totalGolongan),
                };
            },
        );

        const unitKerjaItems: DistribusiBarItem[] =
            stats.distribusi_unit_kerja.map((u) => ({
                nama: u.nama,
                count: u.pegawai_count,
                percentage: barPct(u.pegawai_count, maxUnitKerja),
            }));

        const jabatanItems: DistribusiBarItem[] = stats.distribusi_jabatan.map(
            (j) => ({
                nama: j.nama,
                count: j.pegawai_count,
                percentage: barPct(j.pegawai_count, maxJabatan),
            }),
        );

        const pendidikanItems: PendidikanBarItem[] =
            stats.distribusi_pendidikan.map((p) => ({
                pendidikan: p.pendidikan,
                count: p.pegawai_count,
                percentage: barPct(p.pegawai_count, maxPendidikan),
            }));

        const jenisKelaminItems: JenisKelaminItem[] =
            stats.distribusi_jenis_kelamin.map((jk) => ({
                label: jkLabel(jk.jenis_kelamin),
                total: jk.total,
                percentage: pct(jk.total, totalJK),
            }));

        return {
            totalGolongan,
            totalJK,
            golonganItems,
            unitKerjaItems,
            jabatanItems,
            pendidikanItems,
            jenisKelaminItems,
        };
    }, [stats]);
}

export type { DashboardStats, DashboardComputedStats };

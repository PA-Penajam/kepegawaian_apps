import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AlertError from '@/components/alert-error';
import { EnumSelect } from '@/components/kepegawaian/enum-select';
import { MultiStepForm } from '@/components/kepegawaian/multi-step-form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type { BreadcrumbItem } from '@/types';

interface EditProps {
    pegawai: {
        id: string;
        nama_lengkap: string;
        nip: string | null;
        nip_lama: string | null;
        tempat_lahir: string;
        tanggal_lahir: string;
        jenis_kelamin: string;
        agama: string;
        golongan_darah: string | null;
        status_perkawinan: string;
        alamat: string | null;
        no_telepon: string | null;
        email: string | null;
        status_pegawai: string;
        status_kepegawaian: string;
        tmt_cpns: string | null;
        tmt_pns: string | null;
        pendidikan_terakhir: string | null;
        tanggal_masuk: string;
        ref_unit_kerja_id: string | null;
        ref_jabatan_id: string | null;
        ref_pangkat_id: string | null;
        no_karpeg: string | null;
        no_karis_karsu: string | null;
        npwp: string | null;
        no_bpjs_kesehatan: string | null;
        no_bpjs_ketenagakerjaan: string | null;
        no_taspen: string | null;
    };
    refPangkat: { id: string; kode: string; nama: string }[];
    refJabatan: { id: string; nama: string; jenis_jabatan: string }[];
    refUnitKerja: { id: string; nama: string }[];
    enums: {
        jenisKelamin: string[];
        agama: string[];
        golonganDarah: string[];
        statusPerkawinan: string[];
        statusPegawai: string[];
        statusKepegawaian: string[];
        pendidikanTerakhir: string[];
    };
}

export default function PegawaiEdit({
    pegawai,
    refPangkat,
    refJabatan,
    refUnitKerja,
    enums,
}: EditProps) {
    const [currentStep, setCurrentStep] = useState(1);
    const steps = ['Biodata', 'Kontak & Alamat', 'Kepegawaian'];

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Pegawai',
            href: '/kepegawaian/pegawai',
        },
        {
            title: pegawai.nama_lengkap,
            href: `/kepegawaian/pegawai/${pegawai.id}`,
        },
        {
            title: 'Ubah',
            href: `/kepegawaian/pegawai/${pegawai.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nama_lengkap: pegawai.nama_lengkap || '',
        nip: pegawai.nip || '',
        nip_lama: pegawai.nip_lama || '',
        tempat_lahir: pegawai.tempat_lahir || '',
        tanggal_lahir: pegawai.tanggal_lahir || '',
        jenis_kelamin: pegawai.jenis_kelamin || '',
        agama: pegawai.agama || '',
        golongan_darah: pegawai.golongan_darah || '',
        status_perkawinan: pegawai.status_perkawinan || '',
        alamat: pegawai.alamat || '',
        no_telepon: pegawai.no_telepon || '',
        email: pegawai.email || '',
        status_pegawai: pegawai.status_pegawai || '',
        status_kepegawaian: pegawai.status_kepegawaian || '',
        tmt_cpns: pegawai.tmt_cpns || '',
        tmt_pns: pegawai.tmt_pns || '',
        pendidikan_terakhir: pegawai.pendidikan_terakhir || '',
        tanggal_masuk: pegawai.tanggal_masuk || '',
        ref_unit_kerja_id: pegawai.ref_unit_kerja_id || '',
        ref_jabatan_id: pegawai.ref_jabatan_id || '',
        ref_pangkat_id: pegawai.ref_pangkat_id || '',
        no_karpeg: pegawai.no_karpeg || '',
        no_karis_karsu: pegawai.no_karis_karsu || '',
        npwp: pegawai.npwp || '',
        no_bpjs_kesehatan: pegawai.no_bpjs_kesehatan || '',
        no_bpjs_ketenagakerjaan: pegawai.no_bpjs_ketenagakerjaan || '',
        no_taspen: pegawai.no_taspen || '',
    });

    const handleNext = () => {
        setCurrentStep((prev) => Math.min(prev + 1, steps.length));
    };

    const handlePrev = () => {
        setCurrentStep((prev) => Math.max(prev - 1, 1));
    };

    const handleSubmit = () => {
        put(`/kepegawaian/pegawai/${pegawai.id}`);
    };

    const toOptions = (arr: string[]) =>
        arr.map((val) => ({
            value: val,
            label: val
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (l) => l.toUpperCase()),
        }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ubah ${pegawai.nama_lengkap}`} />

            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 rounded-xl p-4">
                {Object.keys(errors).length > 0 && (
                    <AlertError
                        errors={errorsToArray(errors)}
                        title="Gagal memperbarui data pegawai"
                    />
                )}
                <MultiStepForm
                    steps={steps}
                    currentStep={currentStep}
                    onNext={handleNext}
                    onPrev={handlePrev}
                    onSubmit={handleSubmit}
                    isFirstStep={currentStep === 1}
                    isLastStep={currentStep === steps.length}
                    processing={processing}
                    title={`Ubah Data ${pegawai.nama_lengkap}`}
                >
                    {currentStep === 1 && (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="nama_lengkap">
                                    Nama Lengkap *
                                </Label>
                                <Input
                                    id="nama_lengkap"
                                    value={data.nama_lengkap}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) =>
                                        setData('nama_lengkap', e.target.value)
                                    }
                                    className={
                                        errors.nama_lengkap
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.nama_lengkap && (
                                    <p className="text-sm text-destructive">
                                        {errors.nama_lengkap}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="nip">NIP</Label>
                                <Input
                                    id="nip"
                                    value={data.nip}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('nip', e.target.value)}
                                    className={
                                        errors.nip ? 'border-destructive' : ''
                                    }
                                />
                                {errors.nip && (
                                    <p className="text-sm text-destructive">
                                        {errors.nip}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="nip_lama">NIP Lama</Label>
                                <Input
                                    id="nip_lama"
                                    value={data.nip_lama}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('nip_lama', e.target.value)}
                                    className={
                                        errors.nip_lama
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.nip_lama && (
                                    <p className="text-sm text-destructive">
                                        {errors.nip_lama}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tempat_lahir">
                                    Tempat Lahir *
                                </Label>
                                <Input
                                    id="tempat_lahir"
                                    value={data.tempat_lahir}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) =>
                                        setData('tempat_lahir', e.target.value)
                                    }
                                    className={
                                        errors.tempat_lahir
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.tempat_lahir && (
                                    <p className="text-sm text-destructive">
                                        {errors.tempat_lahir}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tanggal_lahir">
                                    Tanggal Lahir *
                                </Label>
                                <Input
                                    id="tanggal_lahir"
                                    type="date"
                                    value={data.tanggal_lahir}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) =>
                                        setData('tanggal_lahir', e.target.value)
                                    }
                                    className={
                                        errors.tanggal_lahir
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.tanggal_lahir && (
                                    <p className="text-sm text-destructive">
                                        {errors.tanggal_lahir}
                                    </p>
                                )}
                            </div>

                            <EnumSelect
                                id="jenis_kelamin"
                                label="Jenis Kelamin *"
                                options={toOptions(enums.jenisKelamin)}
                                value={data.jenis_kelamin}
                                onChange={(val) =>
                                    setData('jenis_kelamin', val)
                                }
                                error={errors.jenis_kelamin}
                            />

                            <EnumSelect
                                id="agama"
                                label="Agama *"
                                options={toOptions(enums.agama)}
                                value={data.agama}
                                onChange={(val) => setData('agama', val)}
                                error={errors.agama}
                            />

                            <EnumSelect
                                id="golongan_darah"
                                label="Golongan Darah"
                                options={toOptions(enums.golonganDarah)}
                                value={data.golongan_darah}
                                onChange={(val) =>
                                    setData('golongan_darah', val)
                                }
                                error={errors.golongan_darah}
                            />

                            <EnumSelect
                                id="status_perkawinan"
                                label="Status Perkawinan *"
                                options={toOptions(enums.statusPerkawinan)}
                                value={data.status_perkawinan}
                                onChange={(val) =>
                                    setData('status_perkawinan', val)
                                }
                                error={errors.status_perkawinan}
                            />
                        </div>
                    )}

                    {currentStep === 2 && (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="alamat">Alamat</Label>
                                <Textarea
                                    id="alamat"
                                    value={data.alamat}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLTextAreaElement>,
                                    ) => setData('alamat', e.target.value)}
                                    className={
                                        errors.alamat
                                            ? 'border-destructive'
                                            : ''
                                    }
                                    rows={3}
                                />
                                {errors.alamat && (
                                    <p className="text-sm text-destructive">
                                        {errors.alamat}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="no_telepon">No. Telepon</Label>
                                <Input
                                    id="no_telepon"
                                    value={data.no_telepon}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('no_telepon', e.target.value)}
                                    className={
                                        errors.no_telepon
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.no_telepon && (
                                    <p className="text-sm text-destructive">
                                        {errors.no_telepon}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('email', e.target.value)}
                                    className={
                                        errors.email ? 'border-destructive' : ''
                                    }
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">
                                        {errors.email}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {currentStep === 3 && (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <EnumSelect
                                id="status_pegawai"
                                label="Status Pegawai *"
                                options={toOptions(enums.statusPegawai)}
                                value={data.status_pegawai}
                                onChange={(val) =>
                                    setData('status_pegawai', val)
                                }
                                error={errors.status_pegawai}
                            />

                            <EnumSelect
                                id="status_kepegawaian"
                                label="Status Kepegawaian *"
                                options={toOptions(enums.statusKepegawaian)}
                                value={data.status_kepegawaian}
                                onChange={(val) =>
                                    setData('status_kepegawaian', val)
                                }
                                error={errors.status_kepegawaian}
                            />

                            <div className="space-y-2">
                                <Label htmlFor="tanggal_masuk">
                                    Tanggal Masuk *
                                </Label>
                                <Input
                                    id="tanggal_masuk"
                                    type="date"
                                    value={data.tanggal_masuk}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) =>
                                        setData('tanggal_masuk', e.target.value)
                                    }
                                    className={
                                        errors.tanggal_masuk
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.tanggal_masuk && (
                                    <p className="text-sm text-destructive">
                                        {errors.tanggal_masuk}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tmt_cpns">TMT CPNS</Label>
                                <Input
                                    id="tmt_cpns"
                                    type="date"
                                    value={data.tmt_cpns}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('tmt_cpns', e.target.value)}
                                    className={
                                        errors.tmt_cpns
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.tmt_cpns && (
                                    <p className="text-sm text-destructive">
                                        {errors.tmt_cpns}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tmt_pns">TMT PNS</Label>
                                <Input
                                    id="tmt_pns"
                                    type="date"
                                    value={data.tmt_pns}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('tmt_pns', e.target.value)}
                                    className={
                                        errors.tmt_pns
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.tmt_pns && (
                                    <p className="text-sm text-destructive">
                                        {errors.tmt_pns}
                                    </p>
                                )}
                            </div>

                            <EnumSelect
                                id="pendidikan_terakhir"
                                label="Pendidikan Terakhir"
                                options={toOptions(enums.pendidikanTerakhir)}
                                value={data.pendidikan_terakhir}
                                onChange={(val) =>
                                    setData('pendidikan_terakhir', val)
                                }
                                error={errors.pendidikan_terakhir}
                            />

                            <div className="space-y-2">
                                <Label htmlFor="ref_unit_kerja_id">
                                    Unit Kerja
                                </Label>
                                <Select
                                    value={data.ref_unit_kerja_id}
                                    onValueChange={(val) =>
                                        setData('ref_unit_kerja_id', val)
                                    }
                                >
                                    <SelectTrigger
                                        id="ref_unit_kerja_id"
                                        className={
                                            errors.ref_unit_kerja_id
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    >
                                        <SelectValue placeholder="Pilih Unit Kerja" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {refUnitKerja.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.nama}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.ref_unit_kerja_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.ref_unit_kerja_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="ref_jabatan_id">Jabatan</Label>
                                <Select
                                    value={data.ref_jabatan_id}
                                    onValueChange={(val) =>
                                        setData('ref_jabatan_id', val)
                                    }
                                >
                                    <SelectTrigger
                                        id="ref_jabatan_id"
                                        className={
                                            errors.ref_jabatan_id
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    >
                                        <SelectValue placeholder="Pilih Jabatan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {refJabatan.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.nama}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.ref_jabatan_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.ref_jabatan_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="ref_pangkat_id">
                                    Pangkat / Golongan
                                </Label>
                                <Select
                                    value={data.ref_pangkat_id}
                                    onValueChange={(val) =>
                                        setData('ref_pangkat_id', val)
                                    }
                                >
                                    <SelectTrigger
                                        id="ref_pangkat_id"
                                        className={
                                            errors.ref_pangkat_id
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    >
                                        <SelectValue placeholder="Pilih Pangkat" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {refPangkat.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.nama} ({item.kode})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.ref_pangkat_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.ref_pangkat_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="no_karpeg">No. Karpeg</Label>
                                <Input
                                    id="no_karpeg"
                                    value={data.no_karpeg}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('no_karpeg', e.target.value)}
                                    className={
                                        errors.no_karpeg
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {errors.no_karpeg && (
                                    <p className="text-sm text-destructive">
                                        {errors.no_karpeg}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="npwp">NPWP</Label>
                                <Input
                                    id="npwp"
                                    value={data.npwp}
                                    onChange={(
                                        e: React.ChangeEvent<HTMLInputElement>,
                                    ) => setData('npwp', e.target.value)}
                                    className={
                                        errors.npwp ? 'border-destructive' : ''
                                    }
                                />
                                {errors.npwp && (
                                    <p className="text-sm text-destructive">
                                        {errors.npwp}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </MultiStepForm>
            </div>
        </AppLayout>
    );
}

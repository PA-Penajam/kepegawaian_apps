import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ChecklistItem = {
    id?: string;
    kode: string;
    nama: string;
    wajib: boolean;
    urutan: number;
};

type ChecklistTemplate = {
    id: string;
    kode: string;
    nama: string;
    jenis: string;
    deskripsi: string | null;
    aktif: boolean;
    urutan: number | null;
    items: ChecklistItem[];
};

type FormData = {
    kode: string;
    nama: string;
    jenis: string;
    deskripsi: string;
    aktif: boolean;
    urutan: number | string;
    items: ChecklistItem[];
};

type Props = {
    template: ChecklistTemplate | null;
};

const routeBase = '/admin/checklist-template';

const makeEmptyItem = (urutan: number): ChecklistItem => ({
    kode: '',
    nama: '',
    wajib: true,
    urutan,
});

export default function Form({ template }: Props) {
    const isEdit = !!template;
    const form = useForm<FormData>({
        kode: template?.kode ?? '',
        nama: template?.nama ?? '',
        jenis: template?.jenis ?? '',
        deskripsi: template?.deskripsi ?? '',
        aktif: template?.aktif ?? true,
        urutan: template?.urutan ?? 0,
        items: template?.items?.length ? template.items : [makeEmptyItem(1)],
    });

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Admin', href: '#' },
            { title: 'Checklist Template', href: routeBase },
            { title: isEdit ? 'Edit' : 'Tambah', href: '#' },
        ],
        [isEdit],
    );

    const updateItem = <K extends keyof ChecklistItem>(index: number, key: K, value: ChecklistItem[K]) => {
        form.setData(
            'items',
            form.data.items.map((item, itemIndex) => (itemIndex === index ? { ...item, [key]: value } : item)),
        );
    };

    const addItem = () => {
        form.setData('items', [...form.data.items, makeEmptyItem(form.data.items.length + 1)]);
    };

    const removeItem = (index: number) => {
        if (form.data.items.length === 1) {
            return;
        }

        form.setData(
            'items',
            form.data.items
                .filter((_, itemIndex) => itemIndex !== index)
                .map((item, itemIndex) => ({ ...item, urutan: itemIndex + 1 })),
        );
    };

    const moveItem = (index: number, direction: -1 | 1) => {
        const targetIndex = index + direction;

        if (targetIndex < 0 || targetIndex >= form.data.items.length) {
            return;
        }

        const nextItems = [...form.data.items];
        [nextItems[index], nextItems[targetIndex]] = [nextItems[targetIndex], nextItems[index]];

        form.setData(
            'items',
            nextItems.map((item, itemIndex) => ({ ...item, urutan: itemIndex + 1 })),
        );
    };

    const submit = (event: { preventDefault: () => void }) => {
        event.preventDefault();

        if (isEdit && template) {
            form.put(`${routeBase}/${template.id}`);

            return;
        }

        form.post(routeBase);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? 'Edit Checklist Template' : 'Tambah Checklist Template'} />

            <form onSubmit={submit} className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold uppercase tracking-tight">
                            {isEdit ? 'Edit Checklist Template' : 'Tambah Checklist Template'}
                        </h1>
                        <p className="mt-1 text-sm font-medium text-muted-foreground">
                            Atur informasi template dan daftar item checklist.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={routeBase}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 rounded-xl border-2 border-black bg-background p-4 shadow-[4px_4px_0_rgba(0,0,0,1)] md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="kode">Kode</Label>
                        <Input
                            id="kode"
                            value={form.data.kode}
                            disabled={isEdit}
                            onChange={(event) => form.setData('kode', event.target.value)}
                        />
                        {form.errors.kode && <p className="text-sm font-medium text-destructive">{form.errors.kode}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="nama">Nama</Label>
                        <Input id="nama" value={form.data.nama} onChange={(event) => form.setData('nama', event.target.value)} />
                        {form.errors.nama && <p className="text-sm font-medium text-destructive">{form.errors.nama}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="jenis">Jenis</Label>
                        <Input id="jenis" value={form.data.jenis} onChange={(event) => form.setData('jenis', event.target.value)} />
                        {form.errors.jenis && <p className="text-sm font-medium text-destructive">{form.errors.jenis}</p>}
                    </div>
                    <div className="flex items-center gap-2 pt-7">
                        <Checkbox
                            id="aktif"
                            checked={form.data.aktif}
                            onCheckedChange={(checked) => form.setData('aktif', checked === true)}
                        />
                        <Label htmlFor="aktif">Aktif</Label>
                    </div>
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="deskripsi">Deskripsi</Label>
                        <Textarea
                            id="deskripsi"
                            value={form.data.deskripsi}
                            onChange={(event) => form.setData('deskripsi', event.target.value)}
                        />
                        {form.errors.deskripsi && <p className="text-sm font-medium text-destructive">{form.errors.deskripsi}</p>}
                    </div>
                </div>

                <div className="flex flex-col gap-4 rounded-xl border-2 border-black bg-background p-4 shadow-[4px_4px_0_rgba(0,0,0,1)]">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h2 className="font-black uppercase tracking-tight">Items Checklist</h2>
                            <p className="text-sm text-muted-foreground">Tambah, hapus, atau urutkan item template.</p>
                        </div>
                        <Button type="button" variant="outline" onClick={addItem}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Item
                        </Button>
                    </div>

                    {form.data.items.map((item, index) => (
                        <div key={item.id ?? index} className="grid gap-3 rounded-lg border border-black/20 p-3 md:grid-cols-[1fr_2fr_auto_auto]">
                            <div className="grid gap-2">
                                <Label>Kode</Label>
                                <Input value={item.kode} onChange={(event) => updateItem(index, 'kode', event.target.value)} />
                                {form.errors[`items.${index}.kode` as keyof typeof form.errors] && (
                                    <p className="text-sm font-medium text-destructive">{form.errors[`items.${index}.kode` as keyof typeof form.errors]}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label>Nama</Label>
                                <Input value={item.nama} onChange={(event) => updateItem(index, 'nama', event.target.value)} />
                                {form.errors[`items.${index}.nama` as keyof typeof form.errors] && (
                                    <p className="text-sm font-medium text-destructive">{form.errors[`items.${index}.nama` as keyof typeof form.errors]}</p>
                                )}
                            </div>
                            <div className="flex items-center gap-2 pt-7">
                                <Checkbox
                                    checked={item.wajib}
                                    onCheckedChange={(checked) => updateItem(index, 'wajib', checked === true)}
                                />
                                <Label>Wajib</Label>
                            </div>
                            <div className="flex items-center gap-1 pt-6">
                                <Button type="button" variant="ghost" size="icon" onClick={() => moveItem(index, -1)}>
                                    <ArrowUp className="h-4 w-4" />
                                </Button>
                                <Button type="button" variant="ghost" size="icon" onClick={() => moveItem(index, 1)}>
                                    <ArrowDown className="h-4 w-4" />
                                </Button>
                                <Button type="button" variant="ghost" size="icon" onClick={() => removeItem(index)}>
                                    <Trash2 className="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>
                    ))}
                    {form.errors.items && <p className="text-sm font-medium text-destructive">{form.errors.items}</p>}
                </div>
            </form>
        </AppLayout>
    );
}

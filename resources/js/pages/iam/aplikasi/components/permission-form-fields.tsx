import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConventionHelpPanel } from './convention-help-panel';

interface FormData {
    nama: string;
    slug: string;
    group: string;
    keterangan: string;
}

interface Props {
    data: FormData;
    setData: <K extends keyof FormData>(key: K, value: FormData[K]) => void;
    errors: Partial<Record<keyof FormData, string>>;
    disabled?: boolean;
    // Saat true, kunci field slug-related (untuk Edit form karena slug immutable)
    lockSlug?: boolean;
}

export function PermissionFormFields({ data, setData, errors, disabled, lockSlug }: Props) {
    const iam = (
        usePage().props as unknown as {
            iam: {
                slug_pattern: string;
                standard_actions: Record<string, string>;
            };
        }
    ).iam;
    const regex = useMemo(() => new RegExp(iam.slug_pattern), [iam.slug_pattern]);

    const [mode, setMode] = useState<'builder' | 'free'>('builder');
    const [resource, setResource] = useState('');
    const [subResource, setSubResource] = useState('');
    const [action, setAction] = useState('view');
    const [customAction, setCustomAction] = useState('');

    const builderSlug = useMemo(() => {
        const finalAction = action === '__custom__' ? customAction : action;
        return [resource, subResource, finalAction].filter(Boolean).join('.');
    }, [resource, subResource, action, customAction]);

    useEffect(() => {
        if (mode === 'builder' && !lockSlug) {
            setData('slug', builderSlug);
            setData('group', builderSlug.split('.')[0] ?? '');
        }
    }, [builderSlug, mode, lockSlug, setData]);

    const isValid = regex.test(data.slug);
    const group = data.slug.split('.')[0] ?? '';

    const slugInputsDisabled = disabled || lockSlug;

    return (
        <div className="grid gap-4 py-4">
            {!lockSlug && (
                <Tabs value={mode} onValueChange={(v) => {
                    if (v === 'free') {
                        // Pindah ke free: copy builder slug ke field free supaya tidak hilang
                        setData('slug', builderSlug);
                    }
                    setMode(v as 'builder' | 'free');
                }}>
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="builder">Builder (disarankan)</TabsTrigger>
                        <TabsTrigger value="free">Mode ahli</TabsTrigger>
                    </TabsList>

                    <TabsContent value="builder" className="mt-3">
                        <div className="grid gap-3 rounded-md border bg-muted/20 p-3">
                            <div className="grid gap-2">
                                <Label htmlFor="resource">Resource</Label>
                                <Input
                                    id="resource"
                                    value={resource}
                                    onChange={(e) => setResource(e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                                    placeholder="contoh: pegawai, cuti, barang"
                                    className="font-mono"
                                    disabled={slugInputsDisabled}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Resource utama. Lowercase, kebab-case. Auto-sanitize saat ketik.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sub-resource">
                                    Sub-resource <span className="text-muted-foreground">(opsional)</span>
                                </Label>
                                <Input
                                    id="sub-resource"
                                    value={subResource}
                                    onChange={(e) => setSubResource(e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                                    placeholder="contoh: pengajuan, usulan"
                                    className="font-mono"
                                    disabled={slugInputsDisabled}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Untuk modul kompleks (mis. <code className="font-mono">cuti.pengajuan</code>). Boleh kosong.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="action">Action</Label>
                                <Select value={action} onValueChange={setAction} disabled={slugInputsDisabled}>
                                    <SelectTrigger id="action">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(iam.standard_actions).map(([key, desc]) => (
                                            <SelectItem key={key} value={key}>
                                                <span className="font-mono">{key}</span>
                                                <span className="ml-2 text-xs text-muted-foreground">{desc}</span>
                                            </SelectItem>
                                        ))}
                                        <SelectItem value="__custom__">
                                            <span className="italic">Custom...</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {action === '__custom__' && (
                                    <Input
                                        value={customAction}
                                        onChange={(e) => setCustomAction(e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                                        placeholder="contoh: tanda-tangan-ketua"
                                        className="font-mono"
                                        disabled={slugInputsDisabled}
                                    />
                                )}
                            </div>

                            <div className="rounded-md bg-background p-2 text-sm">
                                <span className="text-muted-foreground">Preview slug:</span>{' '}
                                <code className="font-mono">{builderSlug || '—'}</code>{' '}
                                {builderSlug && (
                                    isValid ? (
                                        <CheckCircle2 className="ml-1 inline h-4 w-4 text-green-600" />
                                    ) : (
                                        <AlertCircle className="ml-1 inline h-4 w-4 text-amber-600" />
                                    )
                                )}
                                <div className="mt-1 text-xs text-muted-foreground">
                                    Group: <code className="font-mono">{group || '—'}</code> (auto)
                                </div>
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="free" className="mt-3">
                        <div className="grid gap-2 rounded-md border bg-muted/20 p-3">
                            <Label htmlFor="perm-slug-free">Slug</Label>
                            <Input
                                id="perm-slug-free"
                                value={data.slug}
                                onChange={(e) => {
                                    setData('slug', e.target.value);
                                    setData('group', e.target.value.split('.')[0] ?? '');
                                }}
                                placeholder="contoh: cuti.pengajuan.approve-langsung"
                                className="font-mono"
                                disabled={slugInputsDisabled}
                            />
                            {data.slug && (
                                <p className={`text-xs ${isValid ? 'text-green-600' : 'text-amber-700'}`}>
                                    {isValid ? (
                                        <>
                                            <CheckCircle2 className="inline h-3 w-3" /> Sesuai konvensi
                                        </>
                                    ) : (
                                        <>
                                            <AlertCircle className="inline h-3 w-3" /> Format tidak match regex konvensi
                                        </>
                                    )}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Format: <code className="font-mono">{`{resource}.{action}`}</code> atau{' '}
                                <code className="font-mono">{`{module}.{resource}.{action}`}</code>
                            </p>
                        </div>
                    </TabsContent>
                </Tabs>
            )}

            {lockSlug && (
                // Edit mode: tampilkan slug read-only untuk konteks tapi tidak bisa diubah
                <div className="grid gap-2">
                    <Label>Slug (immutable)</Label>
                    <Input value={data.slug} className="font-mono" disabled readOnly />
                    <p className="text-xs text-muted-foreground">
                        Slug tidak dapat diubah via Edit. Gunakan tombol Migrate jika perlu rename.
                    </p>
                </div>
            )}

            {errors.slug && <p className="text-sm text-destructive">{errors.slug}</p>}

            <div className="grid gap-2">
                <Label htmlFor="perm-nama">Nama Permission</Label>
                <Input
                    id="perm-nama"
                    value={data.nama}
                    onChange={(e) => setData('nama', e.target.value)}
                    placeholder="contoh: Lihat Daftar Pegawai"
                    disabled={disabled}
                    required
                />
                <p className="text-xs text-muted-foreground">
                    Nama deskriptif dalam Bahasa Indonesia.
                </p>
                {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="perm-keterangan">
                    Keterangan <span className="text-muted-foreground">(opsional)</span>
                </Label>
                <Input
                    id="perm-keterangan"
                    value={data.keterangan}
                    onChange={(e) => setData('keterangan', e.target.value)}
                    placeholder="Deskripsi singkat untuk audit/dokumentasi"
                    disabled={disabled}
                />
                {errors.keterangan && <p className="text-sm text-destructive">{errors.keterangan}</p>}
            </div>

            <ConventionHelpPanel />
        </div>
    );
}

PermissionFormFields.isSlugValid = (slug: string, pattern: string): boolean => {
    return new RegExp(pattern).test(slug);
};

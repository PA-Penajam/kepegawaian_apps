import { router } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

type Props = {
    pegawaiId: string;
    currentFotoUrl: string | null;
    initials: string;
    canUpdate: boolean;
};

export function FotoUpload({ pegawaiId, currentFotoUrl, initials, canUpdate }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];

        if (!file) {
return;
}

        setSelectedFile(file);
        setPreviewUrl(URL.createObjectURL(file));
    }

    function handleSubmit(e: React.SyntheticEvent<HTMLFormElement>) {
        e.preventDefault();

        if (!selectedFile) {
return;
}

        const formData = new FormData();
        formData.append('foto', selectedFile);

        setProcessing(true);
        router.post(`/kepegawaian/pegawai/${pegawaiId}/foto`, formData, {
            forceFormData: true,
            onSuccess: () => {
                setPreviewUrl(null);
                setSelectedFile(null);
            },
            onFinish: () => setProcessing(false),
        });
    }

    const displayUrl = previewUrl ?? currentFotoUrl;

    return (
        <form onSubmit={handleSubmit} className="flex flex-col items-center gap-3">
            <div className="relative">
                <Avatar className="h-20 w-20 border-2 border-border">
                    <AvatarImage src={displayUrl ?? undefined} alt={initials} />
                    <AvatarFallback className="text-2xl">{initials}</AvatarFallback>
                </Avatar>
                {canUpdate && (
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        className="absolute bottom-0 right-0 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm"
                        title="Ganti foto"
                    >
                        <Camera className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>
            <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={handleFileChange}
            />
            {selectedFile !== null && (
                <Button type="submit" size="sm" processing={processing}>
                    Simpan Foto
                </Button>
            )}
        </form>
    );
}

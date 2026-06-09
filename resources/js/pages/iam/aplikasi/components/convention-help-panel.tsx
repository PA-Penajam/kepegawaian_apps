import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';
import { ChevronDown, ExternalLink, Info } from 'lucide-react';
import { useState } from 'react';

export function ConventionHelpPanel() {
    const [open, setOpen] = useState(false);
    const iam = (usePage().props as unknown as {
        iam: {
            standard_actions: Record<string, string>;
            docs_url: string;
        };
    }).iam;

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="rounded-md border bg-muted/30 p-3">
            <CollapsibleTrigger asChild>
                <Button variant="ghost" size="sm" className="w-full justify-between p-2">
                    <span className="flex items-center gap-2 text-sm font-medium">
                        <Info className="h-4 w-4" />
                        Lihat konvensi lengkap
                    </span>
                    <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-3 space-y-3 text-sm">
                <div>
                    <p className="font-medium">Format slug:</p>
                    <ul className="ml-4 list-disc text-muted-foreground">
                        <li><code className="font-mono">{`{resource}.{action}`}</code> — mis. <code className="font-mono">pegawai.view</code></li>
                        <li><code className="font-mono">{`{module}.{resource}.{action}`}</code> — mis. <code className="font-mono">cuti.pengajuan.create</code></li>
                    </ul>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Lowercase, antar-segment titik, antar-kata strip. Tidak boleh underscore atau uppercase.
                    </p>
                </div>

                <div>
                    <p className="font-medium">Action standar:</p>
                    <div className="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-muted-foreground sm:grid-cols-3">
                        {Object.entries(iam.standard_actions).map(([key, desc]) => (
                            <div key={key}>
                                <code className="font-mono text-foreground">{key}</code>
                                <span className="ml-1 text-muted-foreground">— {desc}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="border-t pt-2">
                    <a
                        href={iam.docs_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        Buka dokumen konvensi lengkap
                        <ExternalLink className="h-3 w-3" />
                    </a>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

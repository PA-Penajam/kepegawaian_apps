import { Search, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const ALL_FILTERS_VALUE = '__all__';

export type DataTableToolbarFilter = {
    key: string;
    label: string;
    placeholder: string;
    value: string | null;
    options: Array<{
        value: string;
        label: string;
    }>;
    onChange: (value: string | null) => void;
};

type DataTableToolbarProps = {
    searchValue: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    filters: DataTableToolbarFilter[];
    showClear: boolean;
    onClear: () => void;
    className?: string;
};

export function DataTableToolbar({
    searchValue,
    onSearchChange,
    searchPlaceholder = 'Cari data...',
    filters,
    showClear,
    onClear,
    className,
}: DataTableToolbarProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-xl border-2 border-foreground bg-card p-4 drop-shadow-[4px_4px_0_rgba(0,0,0,1)] md:flex-row md:items-end md:justify-between',
                className,
            )}
        >
            <div className="grid w-full gap-3 md:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,1fr))]">
                <div className="grid gap-2 md:col-span-1">
                    <span className="text-sm font-medium">Pencarian</span>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={searchValue}
                            onChange={(event) =>
                                onSearchChange(event.target.value)
                            }
                            placeholder={searchPlaceholder}
                            className="pl-9"
                        />
                    </div>
                </div>

                {filters.map((filter) => (
                    <div key={filter.key} className="grid gap-2">
                        <span className="text-sm font-medium">
                            {filter.label}
                        </span>
                        <Select
                            value={filter.value ?? ALL_FILTERS_VALUE}
                            onValueChange={(value) =>
                                filter.onChange(
                                    value === ALL_FILTERS_VALUE ? null : value,
                                )
                            }
                        >
                            <SelectTrigger className="w-full bg-background">
                                <SelectValue placeholder={filter.placeholder} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_FILTERS_VALUE}>
                                    {filter.placeholder}
                                </SelectItem>
                                {filter.options.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                ))}
            </div>

            {showClear ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onClear}
                    className="md:self-end"
                >
                    <X className="h-4 w-4" />
                    Hapus Filter
                </Button>
            ) : null}
        </div>
    );
}

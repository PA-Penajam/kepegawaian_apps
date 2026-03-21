import React from 'react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface EnumOption {
    value: string;
    label?: string;
    name?: string;
}

interface EnumSelectProps {
    options: EnumOption[];
    value?: string;
    onChange: (value: string) => void;
    placeholder?: string;
    error?: string;
    disabled?: boolean;
    label?: string;
    id?: string;
}

export function EnumSelect({
    options,
    value,
    onChange,
    placeholder = 'Pilih opsi',
    error,
    disabled = false,
    label,
    id,
}: EnumSelectProps) {
    return (
        <div className="space-y-2">
            {label && <Label htmlFor={id}>{label}</Label>}
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger
                    id={id}
                    className={error ? 'border-destructive' : ''}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label || option.name || option.value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

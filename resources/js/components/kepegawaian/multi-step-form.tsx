import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import React from 'react';

interface MultiStepFormProps {
    steps: string[];
    currentStep: number;
    children: React.ReactNode;
    onNext?: () => void;
    onPrev?: () => void;
    onSubmit?: () => void;
    isLastStep: boolean;
    isFirstStep: boolean;
    processing?: boolean;
    title?: string;
}

export function MultiStepForm({
    steps,
    currentStep,
    children,
    onNext,
    onPrev,
    onSubmit,
    isLastStep,
    isFirstStep,
    processing = false,
    title,
}: MultiStepFormProps) {
    return (
        <Card className="w-full">
            {title && (
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                </CardHeader>
            )}
            <CardContent className="pt-6">
                <div className="mb-8">
                    <div className="relative flex items-center justify-between">
                        <div className="absolute top-1/2 left-0 h-1 w-full -translate-y-1/2 rounded-full bg-muted" />
                        <div
                            className="absolute top-1/2 left-0 h-1 -translate-y-1/2 rounded-full bg-primary transition-all duration-300"
                            style={{
                                width: `${((currentStep - 1) / (steps.length - 1)) * 100}%`,
                            }}
                        />
                        {steps.map((step, index) => {
                            const stepNumber = index + 1;
                            const isCompleted = stepNumber < currentStep;
                            const isCurrent = stepNumber === currentStep;

                            return (
                                <div
                                    key={step}
                                    className="group relative flex flex-col items-center"
                                >
                                    <div
                                        className={cn(
                                            'z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 bg-background text-sm font-semibold transition-colors',
                                            isCompleted
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : isCurrent
                                                  ? 'border-primary text-primary'
                                                  : 'border-muted text-muted-foreground',
                                        )}
                                    >
                                        {isCompleted ? (
                                            <Check className="h-5 w-5" />
                                        ) : (
                                            stepNumber
                                        )}
                                    </div>
                                    <span
                                        className={cn(
                                            'absolute -bottom-6 text-xs font-medium whitespace-nowrap',
                                            isCurrent || isCompleted
                                                ? 'text-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {step}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div className="mt-12">{children}</div>
            </CardContent>
            <CardFooter className="flex justify-between border-t p-6">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onPrev}
                    disabled={isFirstStep || processing}
                >
                    Kembali
                </Button>
                {isLastStep ? (
                    <Button
                        type="button"
                        onClick={onSubmit}
                        disabled={processing}
                    >
                        {processing ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                ) : (
                    <Button
                        type="button"
                        onClick={onNext}
                        disabled={processing}
                    >
                        Selanjutnya
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}

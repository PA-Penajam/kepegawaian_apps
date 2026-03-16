import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

/**
 * Interface untuk link pagination Laravel (Shape A)
 * Digunakan oleh halaman yang menggunakan links array
 */
interface LaravelLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Interface untuk meta pagination Laravel (Shape B)
 * Digunakan oleh halaman yang menggunakan meta object
 */
interface PaginationMeta {
    current_page: number;
    last_page: number;
}

interface PaginationWrapperProps {
    links?: LaravelLink[];
    lastPage?: number;
    meta?: PaginationMeta;
    buildHref?: (page: number) => string;
}

function defaultBuildHref(page: number): string {
    if (typeof window === 'undefined') return `?page=${page}`;
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    return `?${params.toString()}`;
}

export function PaginationWrapper({
    links,
    lastPage,
    meta,
    buildHref = defaultBuildHref,
}: PaginationWrapperProps) {
    if (links && lastPage && lastPage > 1) {
        return (
            <div className="mt-4">
                <Pagination>
                    <PaginationContent>
                        {links.map((link, i) => {
                            const isPrevious = link.label.includes('Previous');
                            const isNext = link.label.includes('Next');
                            const isEllipsis = link.label === '...';

                            if (isPrevious) {
                                return (
                                    <PaginationItem key={i}>
                                        <PaginationPrevious
                                            href={link.url ?? '#'}
                                            className={
                                                !link.url
                                                    ? 'pointer-events-none opacity-50'
                                                    : ''
                                            }
                                        />
                                    </PaginationItem>
                                );
                            }

                            if (isNext) {
                                return (
                                    <PaginationItem key={i}>
                                        <PaginationNext
                                            href={link.url ?? '#'}
                                            className={
                                                !link.url
                                                    ? 'pointer-events-none opacity-50'
                                                    : ''
                                            }
                                        />
                                    </PaginationItem>
                                );
                            }

                            if (isEllipsis) {
                                return (
                                    <PaginationItem key={i}>
                                        <PaginationEllipsis />
                                    </PaginationItem>
                                );
                            }

                            return (
                                <PaginationItem key={i}>
                                    <PaginationLink
                                        href={link.url ?? '#'}
                                        isActive={link.active}
                                    >
                                        {link.label}
                                    </PaginationLink>
                                </PaginationItem>
                            );
                        })}
                    </PaginationContent>
                </Pagination>
            </div>
        );
    }

    if (meta && meta.last_page > 1) {
        return (
            <Pagination>
                <PaginationContent>
                    {meta.current_page > 1 && (
                        <PaginationItem>
                            <PaginationPrevious
                                href={buildHref(meta.current_page - 1)}
                            />
                        </PaginationItem>
                    )}

                    {Array.from(
                        { length: meta.last_page },
                        (_, i) => i + 1,
                    ).map((page) => {
                        if (
                            page === 1 ||
                            page === meta.last_page ||
                            (page >= meta.current_page - 1 &&
                                page <= meta.current_page + 1)
                        ) {
                            return (
                                <PaginationItem key={page}>
                                    <PaginationLink
                                        href={buildHref(page)}
                                        isActive={page === meta.current_page}
                                    >
                                        {page}
                                    </PaginationLink>
                                </PaginationItem>
                            );
                        }

                        if (
                            page === meta.current_page - 2 ||
                            page === meta.current_page + 2
                        ) {
                            return (
                                <PaginationItem key={page}>
                                    <PaginationEllipsis />
                                </PaginationItem>
                            );
                        }

                        return null;
                    })}

                    {meta.current_page < meta.last_page && (
                        <PaginationItem>
                            <PaginationNext
                                href={buildHref(meta.current_page + 1)}
                            />
                        </PaginationItem>
                    )}
                </PaginationContent>
            </Pagination>
        );
    }

    return null;
}

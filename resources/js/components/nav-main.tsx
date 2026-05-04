import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    title,
}: {
    items: NavItem[];
    title?: string;
}) {
    const { isCurrentOrParentUrl, currentUrl } = useCurrentUrl();
    const activeItemRef = useRef<HTMLLIElement>(null);

    // Scroll otomatis ke menu aktif saat URL berubah
    useEffect(() => {
        if (activeItemRef.current) {
            activeItemRef.current.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        }
    }, [currentUrl]);

    return (
        <SidebarGroup className="px-2 py-0">
            {title && <SidebarGroupLabel>{title}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => {
                    const isActive = isCurrentOrParentUrl(item.href);
                    return (
                        <SidebarMenuItem
                            key={item.title}
                            ref={isActive ? activeItemRef : undefined}
                        >
                            <SidebarMenuButton
                                asChild
                                isActive={isActive}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                            {item.badge != null && (
                                <SidebarMenuBadge>{item.badge}</SidebarMenuBadge>
                            )}
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

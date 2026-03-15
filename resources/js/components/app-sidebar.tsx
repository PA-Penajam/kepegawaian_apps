import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    FileText,
    LayoutGrid,
    ScrollText,
    Settings,
    Shield,
    TrendingUp,
    User,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as selfServiceIndex } from '@/routes/self-service';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isViewer = auth.user.role === 'viewer';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Data Saya',
            href: selfServiceIndex(),
            icon: User,
        },
        {
            title: 'Settings',
            href: '/settings',
            icon: Settings,
        },
    ];

    const kepegawaianNavItems: NavItem[] = isViewer
        ? []
        : [
              {
                  title: 'Data Pegawai',
                  href: '/kepegawaian/pegawai',
                  icon: Users,
              },
          ];

    const monitoringNavItems: NavItem[] = isViewer
        ? []
        : [
              {
                  title: 'KGB',
                  href: '/kepegawaian/monitoring/kgb',
                  icon: Calendar,
              },
              {
                  title: 'Kenaikan Pangkat',
                  href: '/kepegawaian/monitoring/kenaikan-pangkat',
                  icon: TrendingUp,
              },
          ];

    const referensiNavItems: NavItem[] = isViewer
        ? []
        : [
              {
                  title: 'Jenis Dokumen',
                  href: '/referensi/jenis-dokumen',
                  icon: FileText,
              },
              {
                  title: 'Status Kepegawaian',
                  href: '/referensi/status-kepegawaian',
                  icon: ScrollText,
              },
              {
                  title: 'Status Pegawai',
                  href: '/referensi/status-pegawai',
                  icon: ScrollText,
              },
              {
                  title: 'Roles & Permissions',
                  href: '/referensi/roles',
                  icon: Shield,
              },
          ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {kepegawaianNavItems.length > 0 ? (
                    <NavMain items={kepegawaianNavItems} title="Kepegawaian" />
                ) : null}
                {monitoringNavItems.length > 0 ? (
                    <NavMain items={monitoringNavItems} title="Monitoring" />
                ) : null}
                {referensiNavItems.length > 0 ? (
                    <NavMain items={referensiNavItems} title="Referensi" />
                ) : null}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

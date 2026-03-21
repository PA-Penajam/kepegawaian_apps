import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    FileText,
    LayoutGrid,
    ScrollText,
    Settings,
    Shield,
    ShieldCheck,
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
import aplikasi from '@/routes/iam/aplikasi';
import users from '@/routes/iam/users';
import { index as selfServiceIndex } from '@/routes/self-service';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const permissions = auth.user.permissions ?? [];

    const hasPermission = (perm: string) => permissions.includes(perm);
    const hasAnyPermission = (...perms: string[]) =>
        perms.some((p) => permissions.includes(p));

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

    const kepegawaianNavItems: NavItem[] = hasPermission('pegawai.view')
        ? [
              {
                  title: 'Data Pegawai',
                  href: '/kepegawaian/pegawai',
                  icon: Users,
              },
          ]
        : [];

    const monitoringNavItems: NavItem[] = hasPermission('monitoring.view')
        ? [
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
          ]
        : [];

    const referensiNavItems: NavItem[] = hasAnyPermission(
        'referensi.view',
        'rbac.manage',
    )
        ? [
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
          ]
        : [];

    const iamNavItems: NavItem[] = isViewer
        ? []
        : [
              {
                  title: 'Aplikasi',
                  href: aplikasi.index.url(),
                  icon: ShieldCheck,
              },
              {
                  title: 'User Akses',
                  href: users.index.url(),
                  icon: Users,
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
                {iamNavItems.length > 0 ? (
                    <NavMain items={iamNavItems} title="IAM" />
                ) : null}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

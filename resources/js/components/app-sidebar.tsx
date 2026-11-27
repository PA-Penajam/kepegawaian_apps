import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    CalendarDays,
    ClipboardCheck,
    FileCheck,
    FileText,
    History,
    Inbox,
    LayoutGrid,
    RefreshCcw,
    ScrollText,
    Settings,
    Shield,
    ShieldCheck,
    TrendingUp,
    User,
    UserCheck,
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
import activityLog from '@/routes/activity-log';
import checklistTemplate from '@/routes/admin/checklist-template';
import aplikasi from '@/routes/iam/aplikasi';
import users from '@/routes/iam/users';
import { eligible } from '@/routes/kenaikan-pangkat';
import adminSk from '@/routes/kenaikan-pangkat/admin-sk';
import approval from '@/routes/kenaikan-pangkat/approval';
import usulan from '@/routes/kenaikan-pangkat/usulan';
import { index as selfServiceIndex } from '@/routes/self-service';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { auth, modules } = usePage<{
        auth: Auth;
        modules?: { under_development?: string[] };
    }>().props;
    const permissions = auth.user.permissions ?? [];
    const underDevelopmentModules = modules?.under_development ?? [];

    const hasPermission = (perm: string) => permissions.includes(perm);
    const hasAnyPermission = (...perms: string[]) =>
        perms.some((p) => permissions.includes(p));
    const isUnderDevelopment = (key: string) =>
        underDevelopmentModules.includes(key);

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Self Service',
            href: selfServiceIndex(),
            icon: User,
            underDevelopment: isUnderDevelopment('self_service'),
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

    const validatorNavItems: NavItem[] = hasPermission(
        'pengajuan-perubahan.validate',
    )
        ? [
              {
                  title: 'Validasi Pengajuan',
                  href: '/kepegawaian/pengajuan',
                  icon: ShieldCheck,
                  underDevelopment: isUnderDevelopment('validasi_pengajuan'),
                  badge: isUnderDevelopment('validasi_pengajuan')
                      ? null
                      : (auth.user.pending_pengajuan_count ?? null),
              },
          ]
        : [];

    const monitoringNavItems: NavItem[] = [
        ...(hasPermission('monitoring.view')
            ? [
                  {
                      title: 'KGB',
                      href: '/kepegawaian/monitoring/kgb',
                      icon: Calendar,
                      underDevelopment: isUnderDevelopment('monitoring'),
                  },
              ]
            : []),
        ...(hasAnyPermission(
            'monitoring.view',
            'kenaikan-pangkat.monitoring.view',
        )
            ? [
                  {
                      title: 'Kenaikan Pangkat',
                      href: '/kepegawaian/monitoring/kenaikan-pangkat',
                      icon: TrendingUp,
                      underDevelopment: isUnderDevelopment('monitoring'),
                  },
              ]
            : []),
    ];

    const kenaikanPangkatNavItems: NavItem[] = [
        ...(hasPermission('kenaikan-pangkat.usulan.view')
            ? [
                  {
                      title: 'Usulan KP',
                      href: usulan.index.url(),
                      icon: TrendingUp,
                      underDevelopment: isUnderDevelopment('kenaikan_pangkat'),
                  },
                  {
                      title: 'Pegawai Eligible KP',
                      href: eligible.url(),
                      icon: UserCheck,
                      underDevelopment: isUnderDevelopment('kenaikan_pangkat'),
                  },
              ]
            : []),
        ...(hasAnyPermission(
            'kenaikan-pangkat.usulan.verifikasi-kasubbag',
            'kenaikan-pangkat.usulan.verifikasi-sekretaris',
            'kenaikan-pangkat.usulan.tanda-tangan-ketua',
            'kenaikan-pangkat.usulan.kirim-biro',
        )
            ? [
                  {
                      title: 'Inbox Approval KP',
                      href: approval.inbox.url(),
                      icon: Inbox,
                      underDevelopment: isUnderDevelopment('kenaikan_pangkat'),
                  },
              ]
            : []),
        ...(hasPermission('kenaikan-pangkat.usulan.view') &&
        hasAnyPermission(
            'kenaikan-pangkat.usulan.upload-sk',
            'kenaikan-pangkat.usulan.finalize',
        )
            ? [
                  {
                      title: 'Admin SK KP',
                      href: adminSk.index.url(),
                      icon: FileCheck,
                      underDevelopment: isUnderDevelopment('kenaikan_pangkat'),
                  },
              ]
            : []),
        ...(hasPermission('checklist.template.view')
            ? [
                  {
                      title: 'Checklist Template',
                      href: checklistTemplate.index.url(),
                      icon: ClipboardCheck,
                      underDevelopment: isUnderDevelopment('kenaikan_pangkat'),
                  },
              ]
            : []),
    ];

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

    const cutiNavItems: NavItem[] = hasAnyPermission(
        'cuti.pengajuan.view-own',
        'cuti.saldo.view-own',
        'cuti.pengajuan.verify',
        'cuti.pengajuan.approve-langsung',
        'cuti.pengajuan.approve-pejabat',
        'cuti.saldo.view-all',
        'cuti.audit.view',
        'cuti.jenis.manage',
        'cuti.kewenangan.manage',
    )
        ? [
              ...(hasAnyPermission(
                  'cuti.pengajuan.view-own',
                  'cuti.saldo.view-own',
              )
                  ? [
                        {
                            title: 'Cuti Saya',
                            href: '/cuti/saya',
                            icon: CalendarDays,
                        },
                    ]
                  : []),
              ...(hasAnyPermission(
                  'cuti.pengajuan.verify',
                  'cuti.pengajuan.approve-langsung',
                  'cuti.pengajuan.approve-pejabat',
              )
                  ? [
                        {
                            title: 'Inbox Persetujuan Cuti',
                            href: '/cuti/inbox',
                            icon: Inbox,
                        },
                    ]
                  : []),
              ...(hasPermission('cuti.saldo.view-all')
                  ? [
                        {
                            title: 'Kelola Saldo Cuti',
                            href: '/admin/cuti/saldo',
                            icon: Settings,
                        },
                    ]
                  : []),
              ...(hasPermission('cuti.audit.view')
                  ? [
                        {
                            title: 'Audit Cuti',
                            href: '/admin/cuti/audit',
                            icon: ScrollText,
                        },
                    ]
                  : []),
              ...(hasPermission('cuti.jenis.manage')
                  ? [
                        {
                            title: 'Penandatangan Cuti',
                            href: '/admin/cuti/jenis',
                            icon: ScrollText,
                        },
                    ]
                  : []),
              ...(hasPermission('cuti.kewenangan.manage')
                  ? [
                        {
                            title: 'Kewenangan PTA/MA',
                            href: '/admin/cuti/kewenangan',
                            icon: ScrollText,
                        },
                    ]
                  : []),
          ]
        : [];

    const iamNavItems: NavItem[] = hasPermission('iam.manage')
        ? [
              {
                  title: 'Aplikasi',
                  href: aplikasi.index.url(),
                  icon: ShieldCheck,
              },
              {
                  title: 'Klien Sinkronisasi',
                  href: '/iam/sinkronisasi',
                  icon: RefreshCcw,
              },
              {
                  title: 'User Akses',
                  href: users.index.url(),
                  icon: Users,
              },
              {
                  title: 'Activity Log',
                  href: activityLog.index.url(),
                  icon: History,
              },
          ]
        : [];

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
                {validatorNavItems.length > 0 ? (
                    <NavMain items={validatorNavItems} title="Validator" />
                ) : null}
                {kepegawaianNavItems.length > 0 ? (
                    <NavMain items={kepegawaianNavItems} title="Kepegawaian" />
                ) : null}
                {monitoringNavItems.length > 0 ? (
                    <NavMain items={monitoringNavItems} title="Monitoring" />
                ) : null}
                {kenaikanPangkatNavItems.length > 0 ? (
                    <NavMain
                        items={kenaikanPangkatNavItems}
                        title="Kenaikan Pangkat"
                    />
                ) : null}
                {referensiNavItems.length > 0 ? (
                    <NavMain items={referensiNavItems} title="Referensi" />
                ) : null}
                {cutiNavItems.length > 0 ? (
                    <NavMain items={cutiNavItems} title="Cuti" />
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

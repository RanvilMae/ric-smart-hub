<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, Receipt, GraduationCap, Globe, IdCard, LayoutGrid, ShieldCheck, UserCog, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import type { NavItem } from '@/types';

const page = usePage();

const role = computed(() => page.props.auth.role);
const pending = computed(() => page.props.nav?.pendingMembers ?? 0);

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

// Plain URLs for the admin pages (see routes/web.php)
const urls = {
    overview: '/admin',
    admins: '/admin/admins',
    members: '/admin/members',
};

const homeUrl = computed(() =>
    role.value === 'super_admin' ? urls.overview : role.value === 'regional_admin' ? '/admin/region' : dashboardUrl.value,
);

type NavGroup = { label: string; items: NavItem[] };

const groups = computed<NavGroup[]>(() => {
    if (role.value === 'super_admin') {
        return [
            {
                label: 'Overview',
                items: [{ title: 'National overview', href: urls.overview, icon: Globe, exact: true }],
            },
            {
                label: 'Membership',
                items: [
                    { title: 'Members', href: urls.members, icon: Users, badge: pending.value },
                    { title: 'Payments', href: '/admin/payments', icon: Receipt },
                ],
            },
            {
                label: 'Administration',
                items: [{ title: 'Regional admins', href: urls.admins, icon: UserCog }],
            },
            {
                label: 'Data management',
                items: [{ title: 'ATI services', href: '/admin/ati-services', icon: GraduationCap }],
            },
            {
                label: 'Account',
                items: [{ title: 'My account', href: '/admin/my-account', icon: IdCard }],
            },
        ];
    }

    if (role.value === 'regional_admin') {
        return [
            {
                label: 'Overview',
                items: [{ title: 'Region dashboard', href: '/admin/region', icon: LayoutGrid, exact: true }],
            },
            {
                label: 'My region',
                items: [
                    { title: 'Members', href: urls.members, icon: Users, badge: pending.value },
                    { title: 'Payments', href: '/admin/payments', icon: Receipt },
                    { title: 'Organizations', href: '/admin/organizations', icon: Building2 },
                ],
            },
            {
                label: 'Account',
                items: [
                    { title: 'My membership & ID', href: '/my-account', icon: IdCard },
                    { title: 'Admin profile', href: '/admin/my-account', icon: UserCog },
                ],
            },
        ];
    }

    return [
        {
            label: 'Menu',
            items: [
                { title: 'Dashboard', href: dashboardUrl.value, icon: LayoutGrid },
                { title: 'My account', href: '/my-account', icon: IdCard },
            ],
        },
    ];
});

const roleLabel = computed(
    () =>
        ({
            super_admin: 'Super Admin',
            regional_admin: 'Regional Admin',
            member: 'Member',
        })[role.value ?? 'member'],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div
                class="mx-2 flex items-center gap-2 rounded-lg bg-sidebar-accent px-3 py-2 text-xs font-medium text-sidebar-accent-foreground group-data-[collapsible=icon]:hidden"
            >
                <ShieldCheck class="size-4 shrink-0" />
                <span class="truncate">{{ roleLabel }}</span>
            </div>
        </SidebarHeader>

        <SidebarContent class="gap-4 pt-2">
            <NavMain
                v-for="group in groups"
                :key="group.label"
                :label="group.label"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

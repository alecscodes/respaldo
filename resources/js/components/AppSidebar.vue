<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    FileText,
    Github,
    HardDrive,
    LayoutGrid,
} from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
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
import { GITHUB_DOCS_URL, GITHUB_REPO_URL } from '@/lib/constants';
import type { NavItem } from '@/types';
import { dashboard } from '@/routes';
import { index as appsIndex } from '@/routes/apps';
import { index as logsIndex } from '@/routes/logs';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Apps',
        href: appsIndex(),
        icon: HardDrive,
    },
    {
        title: 'Logs',
        href: logsIndex(),
        icon: FileText,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: GITHUB_REPO_URL,
        icon: Github,
    },
    {
        title: 'Documentation',
        href: GITHUB_DOCS_URL,
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

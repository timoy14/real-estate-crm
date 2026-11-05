<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CheckSquare,
    Handshake,
    Home,
    Inbox,
    Sparkles,
    Users,
} from '@lucide/vue';
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
import { route } from '@/lib/route';

const page = usePage();

const mainNavItems = computed(() => [
    { title: 'My Day', href: route('app.my-day'), icon: CalendarCheck },
    { title: 'Clients', href: route('app.clients.index'), icon: Users },
    { title: 'Properties', href: route('app.properties.index'), icon: Home },
    { title: 'Deals', href: route('app.deals.index'), icon: Handshake },
    { title: 'Tasks', href: route('app.tasks.index'), icon: CheckSquare },
    {
        title: 'Inquiries',
        href: route('app.inquiries.index'),
        icon: Inbox,
        badge: page.props.counts?.newInquiries ?? 0,
    },
    {
        title: 'AI Assistant',
        href: route('app.assistant.index'),
        icon: Sparkles,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('app.my-day')">
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

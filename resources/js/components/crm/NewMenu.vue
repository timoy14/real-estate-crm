<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { route } from '@/lib/route';

// Each item opens the matching index page with ?new=1, which opens its create Sheet.
const withNew = { new: 1 };
const items = [
    { label: 'New client', href: route('app.clients.index', withNew) },
    { label: 'New property', href: route('app.properties.index', withNew) },
    { label: 'New deal', href: route('app.deals.index', withNew) },
    { label: 'New task', href: route('app.tasks.index', withNew) },
];
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button size="sm" data-test="new-menu-trigger">
                <Plus />
                New
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem v-for="item in items" :key="item.href" as-child>
                <Link :href="item.href">{{ item.label }}</Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

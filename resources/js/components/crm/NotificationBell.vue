<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { relative } from '@/lib/format';
import { fetchJson } from '@/lib/http';
import { route } from '@/lib/route';

const page = usePage();
const open = ref(false);
const items = ref([]);
const loaded = ref(false);
// null until we have fetched; then the live count wins over the shared prop.
const unreadOverride = ref(null);
const unread = computed(
    () => unreadOverride.value ?? page.props.counts?.unreadNotifications ?? 0,
);

// A fresh shared prop (after any Inertia visit) supersedes the local count.
watch(
    () => page.props.counts?.unreadNotifications,
    () => (unreadOverride.value = null),
);

async function load() {
    try {
        const data = await fetchJson(route('app.notifications.index'));
        items.value = data.notifications;
        unreadOverride.value = data.unread;
    } catch {
        // Keep whatever we had; the bell is non-critical.
    } finally {
        loaded.value = true;
    }
}

function onOpen(v) {
    open.value = v;

    if (v) {
        load();
    }
}

async function markAll() {
    try {
        const data = await fetchJson(
            route('app.notifications.read-all'),
            'POST',
        );
        items.value = items.value.map((n) => ({ ...n, read: true }));
        unreadOverride.value = data.unread;
    } catch {
        // ignore
    }
}

async function openItem(n) {
    open.value = false;

    if (!n.read) {
        try {
            const data = await fetchJson(
                route('app.notifications.read', n.id),
                'POST',
            );
            n.read = true;
            unreadOverride.value = data.unread;
        } catch {
            // still navigate
        }
    }

    if (n.url) {
        router.visit(n.url);
    }
}
</script>

<template>
    <Popover :open="open" @update:open="onOpen">
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="
                    unread > 0
                        ? `Notifications, ${unread} unread`
                        : 'Notifications'
                "
                data-test="notification-bell"
            >
                <Bell />
                <span
                    v-if="unread > 0"
                    class="absolute top-1.5 right-1.5 size-2 rounded-full bg-primary"
                    data-test="notification-dot"
                />
            </Button>
        </PopoverTrigger>
        <PopoverContent align="end" class="w-80 p-0">
            <div class="flex items-center justify-between border-b px-4 py-2">
                <span class="text-sm font-medium">Notifications</span>
                <Button
                    variant="link"
                    size="sm"
                    :disabled="unread === 0"
                    data-test="mark-all-read"
                    @click="markAll"
                >
                    Mark all read
                </Button>
            </div>
            <p
                v-if="loaded && items.length === 0"
                class="px-4 py-6 text-center text-sm text-muted-foreground"
            >
                You're all caught up.
            </p>
            <ul v-else class="max-h-96 overflow-y-auto">
                <li v-for="n in items" :key="n.id">
                    <Button
                        variant="ghost"
                        class="h-auto w-full flex-col items-start gap-0.5 rounded-none px-4 py-3 text-left font-normal whitespace-normal"
                        data-test="notification-item"
                        @click="openItem(n)"
                    >
                        <span
                            class="flex items-center gap-2"
                            :class="
                                n.read ? 'text-muted-foreground' : 'font-medium'
                            "
                        >
                            <span
                                v-if="!n.read"
                                class="size-2 shrink-0 rounded-full bg-primary"
                            />
                            {{ n.title }}
                        </span>
                        <span class="text-muted-foreground">{{ n.body }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            relative(n.created_at)
                        }}</span>
                    </Button>
                </li>
            </ul>
        </PopoverContent>
    </Popover>
</template>

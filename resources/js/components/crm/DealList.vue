<script setup>
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { money } from '@/lib/format';
import { route } from '@/lib/route';

defineProps({
    deals: { type: Array, required: true },
    emptyText: String,
    showClient: Boolean,
});
</script>

<template>
    <p v-if="deals.length === 0" class="text-sm text-muted-foreground">
        {{ emptyText ?? 'No deals yet.' }}
    </p>
    <ul v-else class="flex flex-col gap-2">
        <li
            v-for="d in deals"
            :key="d.id"
            class="flex items-center justify-between gap-3 text-sm"
            data-test="deal-row"
        >
            <div class="min-w-0">
                <Link
                    :href="route('app.deals.show', d.id)"
                    class="block truncate font-medium hover:underline"
                    >{{ d.title }}</Link
                >
                <span v-if="showClient" class="text-xs text-muted-foreground">{{
                    d.contact?.name ?? 'Deleted client'
                }}</span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span>{{ money(d.value) }}</span>
                <Badge variant="outline">{{ d.stage?.name }}</Badge>
            </div>
        </li>
    </ul>
</template>

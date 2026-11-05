<script setup>
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { money } from '@/lib/format';

defineProps({
    items: { type: Array, required: true },
    emptyText: String,
});
</script>

<template>
    <p
        v-if="items.length === 0"
        class="text-sm text-muted-foreground"
        data-test="match-empty"
    >
        {{ emptyText ?? 'No matches.' }}
    </p>
    <ul v-else class="flex flex-col gap-2" data-test="match-list">
        <li
            v-for="item in items"
            :key="item.key"
            class="flex items-center gap-3"
        >
            <img
                v-if="item.cover_url"
                :src="item.cover_url"
                alt=""
                loading="lazy"
                class="size-12 shrink-0 rounded-md object-cover"
            />
            <div class="min-w-0 flex-1">
                <Link
                    :href="item.href"
                    class="block truncate text-sm font-medium hover:underline"
                >
                    {{ item.title }}
                </Link>
                <p
                    v-if="item.subtitle || item.price !== undefined"
                    class="truncate text-xs text-muted-foreground"
                >
                    {{ item.subtitle
                    }}<template
                        v-if="item.subtitle && item.price !== undefined"
                    >
                        · </template
                    ><template v-if="item.price !== undefined">{{
                        money(item.price)
                    }}</template>
                </p>
            </div>
            <Badge variant="secondary">{{ item.score }}%</Badge>
        </li>
    </ul>
</template>

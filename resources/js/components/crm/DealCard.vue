<script setup>
import { Link } from '@inertiajs/vue3';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import { date, money } from '@/lib/format';
import { route } from '@/lib/route';

defineProps({
    deal: { type: Object, required: true },
});
</script>

<template>
    <Card
        class="cursor-grab py-0 active:cursor-grabbing"
        :data-deal-id="deal.id"
        data-test="deal-card"
    >
        <CardContent class="flex flex-col gap-2 p-3">
            <div class="flex items-start gap-2">
                <img
                    v-if="deal.property"
                    :src="deal.property.cover_url"
                    alt=""
                    class="size-10 shrink-0 rounded-md object-cover"
                    draggable="false"
                />
                <div class="min-w-0 flex-1">
                    <Link
                        :href="route('app.deals.show', deal.id)"
                        class="block truncate text-sm font-medium hover:underline"
                        draggable="false"
                    >
                        {{ deal.title }}
                    </Link>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ deal.contact?.name ?? 'Deleted client' }}
                    </p>
                </div>
                <Avatar class="size-6" :title="deal.owner.name">
                    <AvatarFallback class="text-[10px]">{{
                        deal.owner.initials
                    }}</AvatarFallback>
                </Avatar>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="font-medium">{{ money(deal.value) }}</span>
                <span
                    v-if="deal.expected_close_date"
                    class="text-muted-foreground"
                >
                    {{ date(deal.expected_close_date) }}
                </span>
            </div>
        </CardContent>
    </Card>
</template>

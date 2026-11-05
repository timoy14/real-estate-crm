<script setup>
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

/** "Page x of y" with Previous / Next for a Laravel paginator; filters stay put (preserve-state). */
defineProps({
    page: { type: Object, required: true },
});
</script>

<template>
    <div v-if="page.last_page > 1" class="flex items-center justify-between">
        <span class="text-sm text-muted-foreground"
            >Page {{ page.current_page }} of {{ page.last_page }}</span
        >
        <div class="flex gap-2">
            <Button v-if="page.prev_page_url" variant="outline" as-child>
                <Link :href="page.prev_page_url" preserve-state>Previous</Link>
            </Button>
            <Button v-if="page.next_page_url" variant="outline" as-child>
                <Link :href="page.next_page_url" preserve-state>Next</Link>
            </Button>
        </div>
    </div>
</template>

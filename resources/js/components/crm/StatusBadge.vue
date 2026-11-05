<script setup>
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { humanize } from '@/lib/format';

const props = defineProps({
    status: { type: String, required: true },
    label: String,
});

const variants = {
    new_lead: 'default',
    active: 'secondary',
    closed: 'outline',
    lost: 'destructive',
};

const text = computed(() => props.label ?? humanize(props.status));
</script>

<template>
    <Badge :variant="variants[status] ?? 'outline'">{{ text }}</Badge>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { renderCitations } from '@/lib/citations';

const props = defineProps({
    message: { type: Object, required: true },
});

const segments = computed(() =>
    props.message.role === 'assistant'
        ? renderCitations(props.message.content, props.message.refs)
        : [{ kind: 'text', text: props.message.content }],
);
</script>

<template>
    <div
        class="flex"
        :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
        :data-test="`message-${message.role}`"
    >
        <div
            class="max-w-[85%] rounded-lg px-3 py-2 text-sm whitespace-pre-wrap"
            :class="
                message.role === 'user'
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted text-foreground'
            "
        >
            <template v-for="(s, i) in segments" :key="i">
                <span v-if="s.kind === 'text'">{{ s.text }}</span>
                <Badge
                    v-else
                    as-child
                    variant="secondary"
                    class="mx-0.5 align-baseline"
                >
                    <Link :href="s.url" :title="s.ref" data-test="citation">{{
                        s.label
                    }}</Link>
                </Badge>
            </template>
        </div>
    </div>
</template>

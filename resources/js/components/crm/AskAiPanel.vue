<script setup>
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AssistantChat from '@/components/crm/AssistantChat.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    scopeType: { type: String, required: true },
    scopeId: { type: Number, required: true },
    scopeLabel: { type: String, required: true },
});

const open = defineModel('open', { type: Boolean, default: false });
const page = usePage();
const aiEnabled = computed(() => Boolean(page.props.ai_enabled));

const suggestions = [
    'Summarize what we know about this record.',
    'What happened most recently?',
    'What should I follow up on?',
];
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="flex w-full flex-col gap-3 sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>Ask AI</SheetTitle>
                <SheetDescription>About {{ scopeLabel }}</SheetDescription>
            </SheetHeader>
            <div class="min-h-0 flex-1 px-4 pb-4">
                <AssistantChat
                    :ai-enabled="aiEnabled"
                    :scope-type="scopeType"
                    :scope-id="scopeId"
                    :suggestions="suggestions"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>

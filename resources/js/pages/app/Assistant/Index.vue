<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageSquarePlus, PanelLeft } from '@lucide/vue';
import { ref } from 'vue';
import AssistantChat from '@/components/crm/AssistantChat.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { relative } from '@/lib/format';

const props = defineProps({
    conversations: { type: Array, required: true },
    conversation: { type: [Object, null], required: true },
    messages: { type: Array, required: true },
    ai_enabled: { type: Boolean, required: true },
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AI Assistant', href: '/app/assistant' }],
    },
});

const listOpen = ref(false);

const suggestions = [
    'Which deals have gone quiet for 2 weeks?',
    'Which buyers want 3+ bedrooms under ₱20M?',
    'What did clients say after viewings this week?',
];

function onConversation() {
    // A new conversation was created by the first question: refresh the list only.
    router.reload({ only: ['conversations'] });
}
</script>

<template>
    <Head title="AI Assistant" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading
                title="AI Assistant"
                description="Ask questions about your clients, properties and deals"
            />
            <div class="flex gap-2">
                <Button
                    class="md:hidden"
                    variant="outline"
                    data-test="open-conversations"
                    @click="listOpen = true"
                >
                    <PanelLeft />
                    Conversations
                </Button>
                <Button variant="outline" as-child data-test="new-conversation">
                    <Link href="/app/assistant"><MessageSquarePlus /> New</Link>
                </Button>
            </div>
        </div>

        <div
            class="grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-[16rem_minmax(0,1fr)]"
        >
            <nav
                class="hidden flex-col gap-1 md:flex"
                aria-label="Conversations"
                data-test="conversation-list"
            >
                <p
                    v-if="props.conversations.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No conversations yet.
                </p>
                <Button
                    v-for="c in props.conversations"
                    :key="c.id"
                    as-child
                    :variant="
                        props.conversation?.id === c.id ? 'secondary' : 'ghost'
                    "
                    class="h-auto justify-start py-2 text-left"
                >
                    <Link :href="`/app/assistant/${c.id}`">
                        <span class="flex flex-col items-start">
                            <span class="line-clamp-2 whitespace-normal">{{
                                c.title
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                relative(c.updated_at)
                            }}</span>
                        </span>
                    </Link>
                </Button>
            </nav>

            <div class="h-[70vh] min-h-0">
                <AssistantChat
                    :key="props.conversation?.id ?? 'new'"
                    :ai-enabled="props.ai_enabled"
                    :conversation-id="props.conversation?.id ?? null"
                    :initial-messages="props.messages"
                    :suggestions="suggestions"
                    @conversation="onConversation"
                />
            </div>
        </div>

        <Sheet v-model:open="listOpen">
            <SheetContent side="left" class="w-80">
                <SheetHeader>
                    <SheetTitle>Conversations</SheetTitle>
                    <SheetDescription>Your past questions</SheetDescription>
                </SheetHeader>
                <nav class="flex flex-col gap-1 px-4 pb-4">
                    <p
                        v-if="props.conversations.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No conversations yet.
                    </p>
                    <Button
                        v-for="c in props.conversations"
                        :key="c.id"
                        as-child
                        variant="ghost"
                        class="h-auto justify-start py-2 text-left"
                    >
                        <Link
                            :href="`/app/assistant/${c.id}`"
                            @click="listOpen = false"
                        >
                            <span class="line-clamp-2 whitespace-normal">{{
                                c.title
                            }}</span>
                        </Link>
                    </Button>
                </nav>
            </SheetContent>
        </Sheet>
    </div>
</template>

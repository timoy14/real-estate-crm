<script setup>
import { Send } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import AssistantMessage from '@/components/crm/AssistantMessage.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { sendJson } from '@/lib/http';
import { route } from '@/lib/route';

const props = defineProps({
    aiEnabled: { type: Boolean, required: true },
    conversationId: { type: Number, default: null },
    initialMessages: { type: Array, default: () => [] },
    scopeType: { type: String, default: null },
    scopeId: { type: Number, default: null },
    suggestions: { type: Array, default: () => [] },
});

const emit = defineEmits(['conversation']);

const messages = ref([...props.initialMessages]);
const conversation = ref(props.conversationId);
const question = ref('');
const pending = ref(false);
const error = ref(null);
const scroller = ref(null);
let tempId = 0;

async function scrollDown() {
    await nextTick();
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight });
}

watch(() => [messages.value.length, pending.value], scrollDown);

async function send(text) {
    const q = (text ?? question.value).trim();

    if (!q || pending.value) {
        return;
    }

    error.value = null;
    pending.value = true;
    messages.value.push({ id: --tempId, role: 'user', content: q, refs: [] });
    question.value = '';

    try {
        const body = { question: q };

        if (conversation.value) {
            body.conversation_id = conversation.value;
        } else if (props.scopeType && props.scopeId) {
            body.scope_type = props.scopeType;
            body.scope_id = props.scopeId;
        }

        const res = await sendJson(route('app.assistant.ask'), 'POST', body);

        if (!res.ok) {
            messages.value.pop();
            question.value = q;
            error.value =
                res.status === 429
                    ? 'You are asking too quickly. Wait a moment and try again.'
                    : res.status === 422
                      ? 'That question could not be sent. Check the text and the record, then try again.'
                      : 'Something went wrong. Please try again.';

            return;
        }

        const data = await res.json();
        messages.value.push(data.message);

        if (!conversation.value) {
            conversation.value = data.conversation_id;
            emit('conversation', data.conversation_id);
        }
    } catch {
        messages.value.pop();
        question.value = q;
        error.value = 'Something went wrong. Please try again.';
    } finally {
        pending.value = false;
    }
}

function onKey(e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        send();
    }
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <Alert v-if="!aiEnabled" data-test="ai-disabled">
            <AlertTitle>AI is not configured</AlertTitle>
            <AlertDescription
                >Add ANTHROPIC_API_KEY and VOYAGE_API_KEY to .env to turn the
                assistant on.</AlertDescription
            >
        </Alert>

        <div
            ref="scroller"
            class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto"
            data-test="chat-scroll"
        >
            <div
                v-if="messages.length === 0"
                class="flex flex-col items-start gap-2"
                data-test="suggestions"
            >
                <p class="text-sm text-muted-foreground">
                    Ask about your clients, properties, deals and notes.
                </p>
                <Button
                    v-for="s in suggestions"
                    :key="s"
                    variant="outline"
                    size="sm"
                    class="h-auto text-left whitespace-normal"
                    :disabled="!aiEnabled || pending"
                    @click="send(s)"
                >
                    {{ s }}
                </Button>
            </div>
            <AssistantMessage v-for="m in messages" :key="m.id" :message="m" />
            <div
                v-if="pending"
                class="flex flex-col gap-2"
                data-test="thinking"
            >
                <span class="text-sm text-muted-foreground">Thinking…</span>
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-4 w-1/2" />
            </div>
        </div>

        <Alert v-if="error" variant="destructive" data-test="chat-error">
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <form class="flex items-end gap-2" @submit.prevent="send()">
            <Textarea
                v-model="question"
                rows="2"
                maxlength="2000"
                placeholder="Ask a question…"
                :disabled="!aiEnabled"
                data-test="question"
                @keydown="onKey"
            />
            <Button
                type="submit"
                :disabled="!aiEnabled || pending || question.trim() === ''"
                data-test="send"
            >
                <Send />
                Send
            </Button>
        </form>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Home, Mail, Phone, StickyNote, Users } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { date, humanize, relative } from '@/lib/format';
import { route } from '@/lib/route';

defineProps({
    activities: { type: Array, required: true },
    emptyText: String,
});

const icons = {
    call: Phone,
    email: Mail,
    meeting: Users,
    viewing: Home,
    note: StickyNote,
};

const statusVariant = {
    scheduled: 'secondary',
    completed: 'default',
    cancelled: 'outline',
};

const outcomeLabel = (o) => humanize(o, false);
const viewingLabel = {
    scheduled: 'Scheduled',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

const completing = ref(null);
const open = ref(false);
const feedback = ref('');
const processing = ref(false);
const errors = ref({});

function startComplete(a) {
    completing.value = a;
    feedback.value = '';
    errors.value = {};
    open.value = true;
}

function complete() {
    if (!completing.value || processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};
    router.patch(
        route('app.activities.update', completing.value.id),
        { viewing_status: 'completed', feedback: feedback.value || null },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onError: (e) => (errors.value = e),
            onFinish: () => (processing.value = false),
        },
    );
}

function cancel(a) {
    router.patch(
        route('app.activities.update', a.id),
        { viewing_status: 'cancelled' },
        { preserveScroll: true },
    );
}
</script>

<template>
    <p
        v-if="activities.length === 0"
        class="text-sm text-muted-foreground"
        data-test="activities-empty"
    >
        {{ emptyText ?? 'No activity yet.' }}
    </p>
    <ol v-else class="flex flex-col gap-4" data-test="activity-timeline">
        <li
            v-for="a in activities"
            :key="a.id"
            class="flex gap-3"
            data-test="activity-item"
        >
            <span
                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
            >
                <component :is="icons[a.type]" class="size-4" />
            </span>
            <div class="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{
                        a.subject ??
                        a.type.replace(/^./, (c) => c.toUpperCase())
                    }}</span>
                    <Badge
                        v-if="a.viewing_status"
                        :variant="statusVariant[a.viewing_status]"
                        data-test="viewing-status"
                    >
                        {{ viewingLabel[a.viewing_status] }}
                    </Badge>
                    <Badge v-if="a.call_outcome" variant="outline">{{
                        outcomeLabel(a.call_outcome)
                    }}</Badge>
                </div>
                <p v-if="a.body" class="whitespace-pre-line">{{ a.body }}</p>
                <p
                    v-if="a.feedback"
                    class="whitespace-pre-line text-muted-foreground"
                >
                    Feedback: {{ a.feedback }}
                </p>
                <p
                    v-if="a.viewing_status === 'scheduled' && a.scheduled_at"
                    class="text-muted-foreground"
                >
                    Scheduled for {{ date(a.scheduled_at, true) }}
                </p>
                <p class="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                    <span>{{
                        relative(
                            a.occurred_at ??
                                a.scheduled_at ??
                                a.created_at ??
                                '',
                        )
                    }}</span>
                    <span>by {{ a.author.name }}</span>
                    <Link
                        v-if="a.contact"
                        :href="route('app.clients.show', a.contact.id)"
                        class="hover:underline"
                        >{{ a.contact.name }}</Link
                    >
                    <Link
                        v-if="a.property"
                        :href="route('app.properties.show', a.property.slug)"
                        class="hover:underline"
                    >
                        {{ a.property.title }}
                    </Link>
                </p>
                <div
                    v-if="a.viewing_status === 'scheduled' && a.can.update"
                    class="flex gap-2"
                >
                    <Button
                        size="sm"
                        data-test="complete-viewing"
                        @click="startComplete(a)"
                        >Complete viewing</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        data-test="cancel-viewing"
                        @click="cancel(a)"
                        >Cancel viewing</Button
                    >
                </div>
            </div>
        </li>
    </ol>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Complete viewing</DialogTitle>
                <DialogDescription
                    >How did it go? Feedback is saved on the
                    timeline.</DialogDescription
                >
            </DialogHeader>
            <div class="grid gap-2">
                <Label for="viewing-feedback">Feedback</Label>
                <Textarea
                    id="viewing-feedback"
                    v-model="feedback"
                    rows="4"
                    data-test="viewing-feedback"
                />
                <InputError
                    :message="errors.feedback ?? errors.viewing_status"
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Close</Button>
                <Button
                    data-test="confirm-complete-viewing"
                    :disabled="processing"
                    @click="complete"
                    >Mark completed</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

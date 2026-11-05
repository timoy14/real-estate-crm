<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Sparkles, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AskAiPanel from '@/components/crm/AskAiPanel.vue';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue';
import QuickActions from '@/components/crm/QuickActions.vue';
import TaskList from '@/components/crm/TaskList.vue';
import DealForm from '@/components/crm/DealForm.vue';
import LostReasonDialog from '@/components/crm/LostReasonDialog.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useOpenOnNew } from '@/composables/useOpenOnNew';
import { date, money } from '@/lib/format';
import { route } from '@/lib/route';

const props = defineProps({
    deal: { type: Object, required: true },
    can: { type: Object, required: true },
    stages: { type: Array, required: true },
    form: { type: Object, required: true },
    agents: Array,
    activities: { type: Array, required: true },
    tasks: { type: Array, required: true },
    link_options: { type: Object, required: true },
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Deals', href: '/app/deals' }] },
});

const editOpen = ref(false);
const deleteOpen = ref(false);
const askOpen = ref(false);

useOpenOnNew(editOpen, {
    cleanUrl: () => route('app.deals.show', props.deal.id),
    when: () => props.can.update,
});

function remove() {
    router.delete(route('app.deals.destroy', props.deal.id));
}

const statusVariant = {
    open: 'secondary',
    won: 'default',
    lost: 'destructive',
};
const statusLabel = { open: 'Open', won: 'Won', lost: 'Lost' };

// --- stage picker: the keyboard / mobile alternative to dragging on the board ---
const lostOpen = ref(false);
const lostTarget = ref(null);

function moveTo(stageId, reason) {
    const target = props.stages.find((s) => s.id === stageId);
    router.patch(
        route('app.deals.move', props.deal.id),
        {
            stage_id: stageId,
            position: target?.count ?? 0,
            ...(reason ? { lost_reason: reason } : {}),
        },
        { preserveScroll: true },
    );
}

function changeStage(value) {
    const stageId = Number(value);
    const target = props.stages.find((s) => s.id === stageId);

    if (!target || stageId === props.deal.stage_id) {
        return;
    }

    if (target.is_lost) {
        lostTarget.value = stageId;
        lostOpen.value = true;

        return;
    }

    moveTo(stageId);
}

function confirmLost(reason) {
    if (lostTarget.value !== null) {
        moveTo(lostTarget.value, reason);
        lostTarget.value = null;
    }
}
</script>

<template>
    <Head :title="deal.title" />

    <AskAiPanel
        v-model:open="askOpen"
        scope-type="deal"
        :scope-id="deal.id"
        :scope-label="deal.title"
    />

    <div class="flex flex-col gap-4 p-4">
        <Card>
            <CardHeader class="flex flex-row items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <CardTitle class="text-xl">{{ deal.title }}</CardTitle>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge data-test="deal-stage">{{
                            deal.stage?.name
                        }}</Badge>
                        <Badge :variant="statusVariant[deal.status]">{{
                            statusLabel[deal.status]
                        }}</Badge>
                        <Badge variant="outline">{{
                            deal.pipeline.name
                        }}</Badge>
                    </div>
                    <CardDescription class="flex flex-col gap-1">
                        <span>
                            Value {{ money(deal.value) }} · Commission
                            {{ deal.commission_percent }}% ({{
                                money(deal.estimated_commission)
                            }})
                        </span>
                        <span v-if="deal.expected_close_date"
                            >Expected close:
                            {{ date(deal.expected_close_date) }}</span
                        >
                        <span v-if="deal.lost_reason"
                            >Lost reason: {{ deal.lost_reason }}</span
                        >
                    </CardDescription>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-if="can.update" class="flex items-center gap-2">
                        <Label for="deal-stage-select">Stage</Label>
                        <Select
                            :model-value="String(deal.stage_id)"
                            @update:model-value="changeStage"
                        >
                            <SelectTrigger
                                id="deal-stage-select"
                                class="w-40"
                                data-test="stage-select"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="s in stages"
                                    :key="s.id"
                                    :value="String(s.id)"
                                    >{{ s.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        variant="outline"
                        data-test="ask-ai"
                        @click="askOpen = true"
                    >
                        <Sparkles />
                        Ask AI
                    </Button>
                    <Button
                        v-if="can.update"
                        variant="outline"
                        data-test="edit-deal"
                        @click="editOpen = true"
                    >
                        <Pencil />
                        Edit
                    </Button>
                    <Button
                        v-if="can.delete"
                        variant="outline"
                        data-test="delete-deal"
                        @click="deleteOpen = true"
                    >
                        <Trash2 />
                        Delete
                    </Button>
                </div>
            </CardHeader>
            <CardContent
                class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm"
            >
                <span>
                    Client:
                    <Link
                        v-if="deal.contact"
                        :href="route('app.clients.show', deal.contact.id)"
                        class="font-medium hover:underline"
                    >
                        {{ deal.contact.name }}
                    </Link>
                    <span v-else class="text-muted-foreground"
                        >Deleted client</span
                    >
                </span>
                <span v-if="deal.property">
                    Property:
                    <Link
                        :href="route('app.properties.show', deal.property.slug)"
                        class="font-medium hover:underline"
                    >
                        {{ deal.property.title }}
                    </Link>
                </span>
                <span class="flex items-center gap-2">
                    Owner:
                    <Avatar class="size-6">
                        <AvatarFallback class="text-[10px]">{{
                            deal.owner.initials
                        }}</AvatarFallback>
                    </Avatar>
                    {{ deal.owner.name }}
                </span>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Timeline</CardTitle></CardHeader>
            <CardContent class="flex flex-col gap-4">
                <QuickActions
                    :contact-id="deal.contact?.id"
                    :property-id="deal.property?.id"
                    :deal-id="deal.id"
                    :link-options="link_options"
                    :agents="agents"
                />
                <ActivityTimeline
                    :activities="activities"
                    empty-text="Activity on this deal will appear here."
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Tasks</CardTitle></CardHeader>
            <CardContent
                ><TaskList :tasks="tasks" empty-text="No tasks yet."
            /></CardContent>
        </Card>
    </div>

    <Sheet v-if="can.update" v-model:open="editOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>Edit deal</SheetTitle>
                <SheetDescription>Update {{ deal.title }}.</SheetDescription>
            </SheetHeader>
            <DealForm
                v-if="editOpen"
                :options="form"
                :deal="deal"
                :agents="agents"
                @saved="editOpen = false"
            />
        </SheetContent>
    </Sheet>

    <LostReasonDialog
        v-model:open="lostOpen"
        @confirm="confirmLost"
        @cancel="lostTarget = null"
    />

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="`Delete ${deal.title}?`"
        description="The deal is removed from the board."
        confirm-test="confirm-delete-deal"
        @confirm="remove"
    />
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { LayoutGrid, List, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import DealCard from '@/components/crm/DealCard.vue';
import DealForm from '@/components/crm/DealForm.vue';
import LostReasonDialog from '@/components/crm/LostReasonDialog.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useOpenOnNew } from '@/composables/useOpenOnNew';
import { date, money } from '@/lib/format';
import { route } from '@/lib/route';

const props = defineProps({
    pipeline: { type: Object, required: true },
    pipelines: { type: Array, required: true },
    view: { type: String, required: true },
    stages: { type: Array, required: true },
    form: { type: Object, required: true },
    agents: Array,
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Deals', href: '/app/deals' }] },
});

// Local copy of the columns so drags update instantly (optimistic); the server
// response replaces it, and a failed move restores the pre-drag snapshot.
const clone = (s) => s.map((c) => ({ ...c, deals: [...c.deals] }));
const columns = ref(clone(props.stages));
let snapshot = clone(props.stages);

watch(
    () => props.stages,
    (s) => {
        columns.value = clone(s);
    },
);

const count = (c) => c.deals.length;
const total = (c) => c.deals.reduce((sum, d) => sum + (d.value ?? 0), 0);

function restore() {
    columns.value = snapshot.map((c) => ({ ...c, deals: [...c.deals] }));
}

function startDrag() {
    snapshot = columns.value.map((c) => ({ ...c, deals: [...c.deals] }));
}

const lostPending = ref(null);
const lostOpen = ref(false);

// Any failed move puts the cards back: validation errors, HTTP errors
// (403/419/500, whose error page Inertia still shows) and network errors.
function send(p, reason) {
    router.patch(
        route('app.deals.move', p.dealId),
        {
            stage_id: p.stageId,
            position: p.position,
            ...(reason ? { lost_reason: reason } : {}),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onError: restore,
            onHttpException: () => {
                restore();
            },
            onNetworkError: () => {
                restore();
            },
        },
    );
}

function dropped(stage, evt) {
    const dealId = Number(evt.item.dataset.dealId);
    const pending = { dealId, stageId: stage.id, position: evt.newIndex ?? 0 };

    const sameColumn = snapshot.some(
        (c) => c.id === stage.id && c.deals.some((d) => d.id === dealId),
    );

    // Reordering inside the Lost column must not reopen the dialog (it would erase the reason).
    if (stage.is_lost && !sameColumn) {
        lostPending.value = pending;
        lostOpen.value = true;

        return;
    }

    send(pending);
}

function cancelLost() {
    lostPending.value = null;
    restore();
}

function confirmLost(reason) {
    if (lostPending.value) {
        const p = lostPending.value;
        lostPending.value = null;
        send(p, reason);
    }
}

// --- navigation -----------------------------------------------------------
function go(extra) {
    router.get(
        route('app.deals.index'),
        {
            pipeline: extra.pipeline ?? props.pipeline.slug,
            view: extra.view ?? props.view,
        },
        { preserveState: true, replace: true },
    );
}

const allDeals = computed(() =>
    columns.value.flatMap((c) =>
        c.deals.map((d) => ({ ...d, stageName: c.name })),
    ),
);

// --- new deal sheet ---------------------------------------------------------
// ?new=1 opens it; contact_id / property_id (from a client or property page) pre-fill it.
const sheetOpen = ref(false);
const prefill = ref({});

useOpenOnNew(sheetOpen, {
    cleanUrl: () => route('app.deals.index'),
    open: (params) => {
        const id = (key) =>
            Number(params.get(key)) > 0 ? Number(params.get(key)) : undefined;
        prefill.value = {
            contactId: id('contact_id'),
            propertyId: id('property_id'),
        };
        sheetOpen.value = true;
    },
});

watch(sheetOpen, (open) => {
    if (!open) {
        prefill.value = {};
    }
});
</script>

<template>
    <Head title="Deals" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Tabs
                :model-value="pipeline.slug"
                @update:model-value="(v) => go({ pipeline: String(v) })"
            >
                <TabsList>
                    <TabsTrigger
                        v-for="p in pipelines"
                        :key="p.id"
                        :value="p.slug"
                        :data-test="`pipeline-${p.slug}`"
                    >
                        {{ p.name }}
                    </TabsTrigger>
                </TabsList>
            </Tabs>
            <div class="flex items-center gap-2">
                <Button
                    size="sm"
                    :variant="view === 'board' ? 'default' : 'outline'"
                    data-test="view-board"
                    @click="go({ view: 'board' })"
                >
                    <LayoutGrid />
                    Board
                </Button>
                <Button
                    size="sm"
                    :variant="view === 'list' ? 'default' : 'outline'"
                    data-test="view-list"
                    @click="go({ view: 'list' })"
                >
                    <List />
                    List
                </Button>
                <Button
                    size="sm"
                    data-test="new-deal"
                    @click="sheetOpen = true"
                >
                    <Plus />
                    New deal
                </Button>
            </div>
        </div>

        <div
            v-if="view === 'board'"
            class="flex gap-4 overflow-x-auto pb-4"
            data-test="board"
        >
            <Card
                v-for="col in columns"
                :key="col.id"
                class="w-72 shrink-0 gap-3 py-4"
                :data-test="`column-${col.id}`"
            >
                <CardHeader class="gap-1 px-4">
                    <CardTitle
                        class="flex items-center justify-between text-sm"
                    >
                        {{ col.name }}
                        <Badge variant="secondary" data-test="column-count">{{
                            count(col)
                        }}</Badge>
                    </CardTitle>
                    <p
                        class="text-xs text-muted-foreground"
                        data-test="column-total"
                    >
                        {{ money(total(col)) }}
                    </p>
                </CardHeader>
                <CardContent class="px-3">
                    <VueDraggable
                        v-model="col.deals"
                        group="deals"
                        :animation="150"
                        :delay="150"
                        :delay-on-touch-only="true"
                        class="flex min-h-16 flex-col gap-2"
                        @start="startDrag"
                        @add="(evt) => dropped(col, evt)"
                        @update="(evt) => dropped(col, evt)"
                    >
                        <DealCard
                            v-for="d in col.deals"
                            :key="d.id"
                            :deal="d"
                        />
                    </VueDraggable>
                </CardContent>
            </Card>
        </div>

        <Table v-else data-test="deal-table">
            <TableHeader>
                <TableRow>
                    <TableHead>Deal</TableHead>
                    <TableHead>Client</TableHead>
                    <TableHead>Property</TableHead>
                    <TableHead>Stage</TableHead>
                    <TableHead>Value</TableHead>
                    <TableHead>Close date</TableHead>
                    <TableHead>Owner</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableEmpty v-if="allDeals.length === 0" :colspan="7"
                    >No deals in this pipeline yet.</TableEmpty
                >
                <TableRow v-for="d in allDeals" :key="d.id">
                    <TableCell>
                        <Link
                            :href="route('app.deals.show', d.id)"
                            class="font-medium hover:underline"
                            >{{ d.title }}</Link
                        >
                    </TableCell>
                    <TableCell>{{ d.contact?.name ?? '—' }}</TableCell>
                    <TableCell>{{ d.property?.title ?? '—' }}</TableCell>
                    <TableCell
                        ><Badge variant="outline">{{
                            d.stageName
                        }}</Badge></TableCell
                    >
                    <TableCell>{{ money(d.value) }}</TableCell>
                    <TableCell>{{
                        d.expected_close_date
                            ? date(d.expected_close_date)
                            : '—'
                    }}</TableCell>
                    <TableCell>
                        <Avatar class="size-6" :title="d.owner.name">
                            <AvatarFallback class="text-[10px]">{{
                                d.owner.initials
                            }}</AvatarFallback>
                        </Avatar>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <Sheet v-model:open="sheetOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>New deal</SheetTitle>
                <SheetDescription>Start tracking a deal.</SheetDescription>
            </SheetHeader>
            <DealForm
                v-if="sheetOpen"
                :options="form"
                :agents="agents"
                :pipeline-slug="pipeline.slug"
                :contact-id="prefill.contactId"
                :property-id="prefill.propertyId"
                @saved="sheetOpen = false"
            />
        </SheetContent>
    </Sheet>

    <LostReasonDialog
        v-model:open="lostOpen"
        @confirm="confirmLost"
        @cancel="cancelLost"
    />
</template>

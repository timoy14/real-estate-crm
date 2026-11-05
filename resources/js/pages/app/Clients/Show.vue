<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Sparkles, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import AskAiPanel from '@/components/crm/AskAiPanel.vue';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue';
import QuickActions from '@/components/crm/QuickActions.vue';
import TaskList from '@/components/crm/TaskList.vue';
import ClientForm from '@/components/crm/ClientForm.vue';
import DealList from '@/components/crm/DealList.vue';
import MatchList from '@/components/crm/MatchList.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import WishlistForm from '@/components/crm/WishlistForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { date, money } from '@/lib/format';
import { optionLabel } from '@/lib/options';
import { route } from '@/lib/route';

const props = defineProps({
    client: { type: Object, required: true },
    wishlists: { type: Array, required: true },
    can: { type: Object, required: true },
    options: { type: Object, required: true },
    agents: Array,
    matches: { type: Array, required: true },
    deals: { type: Array, required: true },
    activities: { type: Array, required: true },
    tasks: { type: Array, required: true },
    link_options: { type: Object, required: true },
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/app/clients' }] },
});

const label = (list, v) => optionLabel(list, v) ?? v ?? '—';

const editOpen = ref(false);
const deleteOpen = ref(false);
const wishlistOpen = ref(false);
const editingWishlist = ref();
const wishlistKey = ref(0);

function openWishlist(w) {
    editingWishlist.value = w;
    wishlistKey.value++;
    wishlistOpen.value = true;
}

const askOpen = ref(false);
// The dialog closes before AlertDialogAction's @click runs, so the target
// must survive the close; it is cleared in onFinish.
const wishlistToDelete = ref(null);
const wishlistDeleteOpen = ref(false);

function askRemoveWishlist(w) {
    wishlistToDelete.value = w;
    wishlistDeleteOpen.value = true;
}

function removeClient() {
    router.delete(route('app.clients.destroy', props.client.id), {
        onError: (errors) => {
            toast.error(
                Object.values(errors)[0] ?? 'The client could not be deleted.',
            );
        },
    });
}

function removeWishlist() {
    if (wishlistToDelete.value) {
        router.delete(
            route('app.clients.wishlists.destroy', wishlistToDelete.value.id),
            {
                preserveScroll: true,
                onFinish: () => {
                    wishlistToDelete.value = null;
                },
            },
        );
    }
}

function budget(w) {
    if (w.min_budget === null && w.max_budget === null) {
        return 'Any budget';
    }

    return `${w.min_budget !== null ? money(w.min_budget) : 'Up'} – ${w.max_budget !== null ? money(w.max_budget) : 'any'}`;
}
</script>

<template>
    <Head :title="client.name" />

    <AskAiPanel
        v-model:open="askOpen"
        scope-type="contact"
        :scope-id="client.id"
        :scope-label="client.name"
    />

    <div class="flex flex-col gap-4 p-4">
        <Card>
            <CardHeader class="flex flex-row items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <CardTitle class="text-xl">{{ client.name }}</CardTitle>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-for="r in client.roles"
                            :key="r"
                            variant="outline"
                        >
                            {{ label(options.roles, r) }}
                        </Badge>
                        <StatusBadge
                            :status="client.status"
                            :label="label(options.statuses, client.status)"
                        />
                        <Badge
                            v-for="t in client.tags"
                            :key="t"
                            variant="secondary"
                            >{{ t }}</Badge
                        >
                    </div>
                    <CardDescription class="flex flex-col gap-1">
                        <span
                            >{{ client.email ?? 'No email' }} ·
                            {{ client.phone ?? 'No phone' }}</span
                        >
                        <span
                            >Owner: {{ client.owner.name }} · Source:
                            {{ label(options.sources, client.source) }}</span
                        >
                    </CardDescription>
                </div>
                <div class="flex gap-2">
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
                        data-test="edit-client"
                        @click="editOpen = true"
                    >
                        <Pencil />
                        Edit
                    </Button>
                    <Button
                        v-if="can.delete"
                        variant="outline"
                        data-test="delete-client"
                        @click="deleteOpen = true"
                    >
                        <Trash2 />
                        Delete
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <QuickActions
                    :contact-id="client.id"
                    :link-options="link_options"
                    :agents="agents"
                />
            </CardContent>
        </Card>

        <Tabs default-value="overview">
            <TabsList>
                <TabsTrigger value="overview">Overview</TabsTrigger>
                <TabsTrigger value="deals">Deals</TabsTrigger>
                <TabsTrigger value="activity">Activity</TabsTrigger>
                <TabsTrigger value="tasks">Tasks</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Wishlists"
                        description="What this client is looking for"
                    />
                    <Button
                        v-if="can.update"
                        size="sm"
                        data-test="add-wishlist"
                        @click="openWishlist()"
                    >
                        <Plus />
                        Add wishlist
                    </Button>
                </div>

                <p
                    v-if="wishlists.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No wishlists yet.
                </p>

                <Card
                    v-for="w in wishlists"
                    :key="w.id"
                    data-test="wishlist-card"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-4"
                    >
                        <div class="flex flex-col gap-1">
                            <CardTitle class="text-base">
                                {{
                                    label(options.listing_types, w.listing_type)
                                }}
                                · {{ budget(w) }}
                            </CardTitle>
                            <CardDescription>
                                <span v-if="w.min_bedrooms !== null"
                                    >{{ w.min_bedrooms }}+ bed ·
                                </span>
                                <span v-if="w.min_bathrooms !== null"
                                    >{{ w.min_bathrooms }}+ bath ·
                                </span>
                                <span v-if="w.property_types.length">
                                    {{
                                        w.property_types
                                            .map((t) =>
                                                label(
                                                    options.property_types,
                                                    t,
                                                ),
                                            )
                                            .join(', ')
                                    }}
                                </span>
                            </CardDescription>
                        </div>
                        <div v-if="can.update" class="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="openWishlist(w)"
                                >Edit</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                data-test="delete-wishlist"
                                @click="askRemoveWishlist(w)"
                                >Delete</Button
                            >
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2 text-sm">
                        <div
                            v-if="w.areas.length"
                            class="flex flex-wrap items-center gap-1"
                        >
                            <span class="text-muted-foreground">Areas:</span>
                            <Badge
                                v-for="a in w.areas"
                                :key="a"
                                variant="secondary"
                                >{{ a }}</Badge
                            >
                        </div>
                        <div
                            v-if="w.must_have_features.length"
                            class="flex flex-wrap items-center gap-1"
                        >
                            <span class="text-muted-foreground"
                                >Must have:</span
                            >
                            <Badge
                                v-for="f in w.must_have_features"
                                :key="f"
                                variant="outline"
                                >{{ f }}</Badge
                            >
                        </div>
                        <div v-if="w.move_in_date">
                            Move in: {{ date(w.move_in_date) }}
                        </div>
                        <div v-if="w.notes" class="text-muted-foreground">
                            {{ w.notes }}
                        </div>
                    </CardContent>
                </Card>

                <Heading
                    variant="small"
                    title="Matching properties"
                    description="Listings that fit this client's wishlists"
                />
                <p
                    v-if="matches.length === 0"
                    class="text-sm text-muted-foreground"
                    data-test="matches-empty"
                >
                    No matches to show yet.
                </p>
                <div
                    v-for="(m, i) in matches"
                    :key="m.wishlist_id"
                    class="flex flex-col gap-2"
                >
                    <p class="text-sm text-muted-foreground">
                        Wishlist {{ i + 1 }}
                    </p>
                    <MatchList
                        :items="
                            m.properties.map((p) => ({
                                key: p.id,
                                href: route('app.properties.show', p.slug),
                                title: p.title,
                                subtitle: p.area,
                                price: p.price,
                                cover_url: p.cover_url,
                                score: p.match_score,
                            }))
                        "
                        empty-text="No matching properties for this wishlist."
                    />
                </div>

                <div v-if="client.notes">
                    <Heading variant="small" title="Notes" />
                    <p class="text-sm whitespace-pre-line">
                        {{ client.notes }}
                    </p>
                </div>
            </TabsContent>

            <TabsContent value="deals" class="flex flex-col gap-3">
                <div v-if="can.update" class="flex justify-end">
                    <Button size="sm" as-child data-test="add-deal">
                        <Link :href="`/app/deals?new=1&contact_id=${client.id}`"
                            >New deal</Link
                        >
                    </Button>
                </div>
                <DealList :deals="deals" />
            </TabsContent>
            <TabsContent value="activity">
                <ActivityTimeline :activities="activities" />
            </TabsContent>
            <TabsContent value="tasks">
                <TaskList :tasks="tasks" empty-text="No tasks yet." />
            </TabsContent>
        </Tabs>
    </div>

    <Sheet v-model:open="editOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>Edit client</SheetTitle>
                <SheetDescription>Update {{ client.name }}.</SheetDescription>
            </SheetHeader>
            <ClientForm
                v-if="editOpen"
                :options="options"
                :client="client"
                :agents="agents"
                @saved="editOpen = false"
            />
        </SheetContent>
    </Sheet>

    <Dialog v-model:open="wishlistOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{
                    editingWishlist ? 'Edit wishlist' : 'New wishlist'
                }}</DialogTitle>
                <DialogDescription
                    >What {{ client.name }} wants in a
                    property.</DialogDescription
                >
            </DialogHeader>
            <WishlistForm
                :key="wishlistKey"
                :client-id="client.id"
                :options="options"
                :wishlist="editingWishlist"
                @saved="wishlistOpen = false"
            />
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="`Delete ${client.name}?`"
        description="The client is removed from your lists."
        confirm-test="confirm-delete-client"
        @confirm="removeClient"
    />

    <ConfirmDialog
        v-model:open="wishlistDeleteOpen"
        title="Delete this wishlist?"
        description="This cannot be undone from the app."
        confirm-test="confirm-delete-wishlist"
        @confirm="removeWishlist"
    />
</template>

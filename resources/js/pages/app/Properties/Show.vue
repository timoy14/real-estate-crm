<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Sparkles, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AskAiPanel from '@/components/crm/AskAiPanel.vue';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue';
import QuickActions from '@/components/crm/QuickActions.vue';
import TaskList from '@/components/crm/TaskList.vue';
import Heading from '@/components/Heading.vue';
import PhotoUploader from '@/components/crm/PhotoUploader.vue';
import PropertyForm from '@/components/crm/PropertyForm.vue';
import MatchList from '@/components/crm/MatchList.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import DealList from '@/components/crm/DealList.vue';
import { priceText } from '@/lib/format';
import { optionLabel } from '@/lib/options';
import { route } from '@/lib/route';

const props = defineProps({
    property: { type: Object, required: true },
    photos: { type: Array, required: true },
    owner: { type: [Object, null], required: true },
    can: { type: Object, required: true },
    options: { type: Object, required: true },
    feature_options: { type: Array, required: true },
    contacts: Array,
    agents: Array,
    interestedClients: { type: Array, required: true },
    deals: { type: Array, required: true },
    activities: { type: Array, required: true },
    tasks: { type: Array, required: true },
    link_options: { type: Object, required: true },
    inquiries: { type: Array, required: true },
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Properties', href: '/app/properties' }] },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const editOpen = ref(false);
const deleteOpen = ref(false);
const askOpen = ref(false);

const label = (opts, v) => optionLabel(opts, v) ?? v;
const featureLabel = (f) => label(props.options.features, f);

const facts = computed(() => {
    const p = props.property;
    const n = (v, unit = '') =>
        v === null || v === undefined
            ? null
            : `${v.toLocaleString('en-US')}${unit}`;
    return [
        ['Type', label(props.options.property_types, p.property_type)],
        ['Bedrooms', n(p.bedrooms)],
        ['Bathrooms', n(p.bathrooms)],
        ['Floor area', n(p.floor_area_sqft, ' sqft')],
        ['Lot area', n(p.lot_area_sqft, ' sqft')],
        ['Year built', p.year_built ? String(p.year_built) : null],
        ['Listing agent', p.listing_agent?.name ?? null],
        ['Public site', p.is_published ? 'Published' : 'Not published'],
    ].filter(([, v]) => v !== null);
});

function remove() {
    router.delete(route('app.properties.destroy', props.property.slug));
}
</script>

<template>
    <Head :title="property.title" />

    <AskAiPanel
        v-model:open="askOpen"
        scope-type="property"
        :scope-id="property.id"
        :scope-label="property.title"
    />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-2">
                <Heading
                    :title="property.title"
                    :description="`${property.address}, ${property.area}, ${property.city}`"
                />
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="text-2xl font-semibold"
                        data-test="property-price"
                    >
                        {{ priceText(property) }}
                    </span>
                    <Badge variant="outline">{{
                        property.listing_type === 'rent' ? 'Rent' : 'Sale'
                    }}</Badge>
                    <StatusBadge
                        :status="property.status"
                        :label="label(options.statuses, property.status)"
                    />
                </div>
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
                <template v-if="can.update">
                    <Button
                        variant="outline"
                        data-test="edit-property"
                        @click="editOpen = true"
                    >
                        <Pencil />
                        Edit
                    </Button>
                    <Button
                        variant="outline"
                        data-test="delete-property"
                        @click="deleteOpen = true"
                    >
                        <Trash2 />
                        Delete
                    </Button>
                </template>
            </div>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div class="px-12">
                    <Carousel v-if="photos.length" data-test="photo-carousel">
                        <CarouselContent>
                            <CarouselItem
                                v-for="(ph, i) in photos"
                                :key="ph.id"
                            >
                                <img
                                    :src="ph.url"
                                    :alt="`${property.title}, photo ${i + 1}`"
                                    :loading="i === 0 ? 'eager' : 'lazy'"
                                    class="aspect-[4/3] w-full rounded-lg object-cover"
                                />
                            </CarouselItem>
                        </CarouselContent>
                        <template v-if="photos.length > 1">
                            <CarouselPrevious />
                            <CarouselNext />
                        </template>
                    </Carousel>
                    <img
                        v-else
                        :src="property.cover_url"
                        alt="No photos yet"
                        class="aspect-[4/3] w-full rounded-lg object-cover"
                    />
                </div>

                <Card>
                    <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <dl
                            class="grid grid-cols-2 gap-4 sm:grid-cols-4"
                            data-test="property-facts"
                        >
                            <div v-for="[k, v] in facts" :key="k">
                                <dt class="text-xs text-muted-foreground">
                                    {{ k }}
                                </dt>
                                <dd class="font-medium">{{ v }}</dd>
                            </div>
                        </dl>
                        <div
                            v-if="property.features.length"
                            class="flex flex-wrap gap-2"
                        >
                            <Badge
                                v-for="f in property.features"
                                :key="f"
                                variant="secondary"
                                >{{ featureLabel(f) }}</Badge
                            >
                        </div>
                        <p
                            v-if="property.description"
                            class="text-sm whitespace-pre-line"
                        >
                            {{ property.description }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            No description yet.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Activity</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <QuickActions
                            :property-id="property.id"
                            :link-options="link_options"
                            :agents="agents"
                        />
                        <ActivityTimeline :activities="activities" />
                    </CardContent>
                </Card>

                <Card v-if="can.update">
                    <CardHeader><CardTitle>Photos</CardTitle></CardHeader>
                    <CardContent>
                        <PhotoUploader
                            :slug="property.slug"
                            :photos="photos"
                            :errors="errors"
                        />
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-6">
                <Card v-if="owner" data-test="owner-card">
                    <CardHeader><CardTitle>Owner</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-1 text-sm">
                        <span class="font-medium">{{ owner.name }}</span>
                        <span
                            v-if="owner.email"
                            class="text-muted-foreground"
                            >{{ owner.email }}</span
                        >
                        <span
                            v-if="owner.phone"
                            class="text-muted-foreground"
                            >{{ owner.phone }}</span
                        >
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        ><CardTitle>Interested clients</CardTitle></CardHeader
                    >
                    <CardContent>
                        <MatchList
                            :items="
                                interestedClients.map((c) => ({
                                    key: c.wishlist_id,
                                    href: route(
                                        'app.clients.show',
                                        c.contact.id,
                                    ),
                                    title: c.contact.name,
                                    score: c.match_score,
                                }))
                            "
                            empty-text="No matching clients yet."
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Deals</CardTitle></CardHeader>
                    <CardContent
                        ><DealList :deals="deals" show-client
                    /></CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Inquiries</CardTitle></CardHeader>
                    <CardContent>
                        <p
                            v-if="inquiries.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No inquiries yet.
                        </p>
                        <ul v-else class="flex flex-col gap-2 text-sm">
                            <li
                                v-for="i in inquiries"
                                :key="i.id"
                                class="flex items-center justify-between gap-2"
                            >
                                <Link
                                    v-if="i.contact_id"
                                    :href="`/app/clients/${i.contact_id}`"
                                    class="truncate text-primary hover:underline"
                                    >{{ i.name }}</Link
                                >
                                <span v-else class="truncate">{{
                                    i.name
                                }}</span>
                                <span class="shrink-0 text-muted-foreground">{{
                                    i.status_label
                                }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Tasks</CardTitle></CardHeader>
                    <CardContent
                        ><TaskList :tasks="tasks" empty-text="No tasks yet."
                    /></CardContent>
                </Card>
            </div>
        </div>
    </div>

    <Sheet v-if="can.update" v-model:open="editOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>Edit property</SheetTitle>
                <SheetDescription>Update this listing.</SheetDescription>
            </SheetHeader>
            <PropertyForm
                v-if="editOpen"
                :options="options"
                :property="property"
                :contacts="contacts"
                :agents="agents"
                @saved="editOpen = false"
            />
        </SheetContent>
    </Sheet>

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Delete this property?"
        description="It will be removed from the CRM and the public site."
        confirm-test="confirm-delete-property"
        @confirm="remove"
    />
</template>

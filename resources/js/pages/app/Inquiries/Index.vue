<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { relative } from '@/lib/format';
import { route } from '@/lib/route';

const props = defineProps({
    inquiries: { type: Object, required: true },
    status: { type: String, required: true },
    statusCounts: { type: Object, required: true },
    options: { type: Object, required: true },
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inquiries', href: '/app/inquiries' }] },
});

function show(status) {
    router.get(
        route('app.inquiries.index', { status: String(status) }),
        {},
        { preserveState: true },
    );
}

function setStatus(row, status) {
    router.patch(
        route('app.inquiries.update', row.id),
        { status: String(status) },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Inquiries" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Inquiries"
            description="Website inquiries from the public listings."
        />

        <Tabs :model-value="props.status" @update:model-value="show">
            <TabsList>
                <TabsTrigger
                    v-for="s in options.statuses"
                    :key="s.value"
                    :value="s.value"
                >
                    {{ s.label }} ({{ statusCounts[s.value] ?? 0 }})
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Client</TableHead>
                    <TableHead>Property</TableHead>
                    <TableHead>Message</TableHead>
                    <TableHead>Received</TableHead>
                    <TableHead>Deal</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="row in inquiries.data" :key="row.id">
                    <TableCell>
                        <Link
                            v-if="row.contact"
                            :href="`/app/clients/${row.contact.id}`"
                            class="font-medium text-primary hover:underline"
                        >
                            {{ row.name }}
                        </Link>
                        <span v-else class="font-medium">{{ row.name }}</span>
                        <p class="text-xs text-muted-foreground">
                            {{ row.email ?? row.phone }}
                        </p>
                    </TableCell>
                    <TableCell>
                        <Link
                            v-if="row.property"
                            :href="`/app/properties/${row.property.id}`"
                            class="hover:underline"
                        >
                            {{ row.property.title }}
                        </Link>
                    </TableCell>
                    <TableCell class="max-w-xs">
                        <p class="line-clamp-2 break-words">
                            {{ row.message }}
                        </p>
                        <StatusBadge
                            v-if="row.wants_viewing"
                            status="viewing"
                            label="Wants viewing"
                        />
                    </TableCell>
                    <TableCell>{{
                        row.created_at ? relative(row.created_at) : ''
                    }}</TableCell>
                    <TableCell>
                        <Link
                            v-if="row.deal"
                            :href="`/app/deals/${row.deal.id}`"
                            class="text-primary hover:underline"
                        >
                            Open deal
                        </Link>
                    </TableCell>
                    <TableCell>
                        <Select
                            :model-value="row.status"
                            @update:model-value="setStatus(row, $event)"
                        >
                            <SelectTrigger
                                class="w-36"
                                :aria-label="`Status of the inquiry from ${row.name}`"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="s in options.statuses"
                                    :key="s.value"
                                    :value="s.value"
                                    >{{ s.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </TableCell>
                </TableRow>
                <TableEmpty v-if="inquiries.data.length === 0" :colspan="6"
                    >No inquiries here.</TableEmpty
                >
            </TableBody>
        </Table>

        <div
            v-if="inquiries.prev_page_url || inquiries.next_page_url"
            class="flex justify-end gap-2"
        >
            <Button v-if="inquiries.prev_page_url" variant="outline" as-child
                ><Link :href="inquiries.prev_page_url">Previous</Link></Button
            >
            <Button v-if="inquiries.next_page_url" variant="outline" as-child
                ><Link :href="inquiries.next_page_url">Next</Link></Button
            >
        </div>
    </div>
</template>

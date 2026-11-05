<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ChevronsUpDown,
    Columns3,
    Plus,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import ClientForm from '@/components/crm/ClientForm.vue';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import Pager from '@/components/crm/Pager.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useOpenOnNew } from '@/composables/useOpenOnNew';
import { relative } from '@/lib/format';
import { optionLabel } from '@/lib/options';
import { route } from '@/lib/route';

const props = defineProps({
    clients: { type: Object, required: true },
    filters: { type: Object, required: true },
    agents: Array,
    options: { type: Object, required: true },
    tags: { type: Array, required: true },
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/app/clients' }] },
});

const ALL = '__all';

// --- filters ---------------------------------------------------------
const search = ref(props.filters.search);

function visit(extra = {}) {
    const merged = {
        ...props.filters,
        search: search.value,
        ...extra,
    };
    const query = Object.fromEntries(
        Object.entries(merged).filter(
            ([, v]) => v !== null && v !== '' && v !== ALL,
        ),
    );
    router.get(route('app.clients.index'), query, {
        preserveState: true,
        replace: true,
    });
}

watchDebounced(search, () => visit(), { debounce: 300 });

function sortBy(key) {
    const dir =
        props.filters.sort === key && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc';
    visit({ sort: key, direction: dir });
}

// --- columns (persisted per browser; storage may be unavailable) ---------
const COLUMNS = [
    { key: 'contact', label: 'Contact' },
    { key: 'roles', label: 'Roles' },
    { key: 'status', label: 'Status' },
    { key: 'source', label: 'Source' },
    { key: 'tags', label: 'Tags' },
    { key: 'owner', label: 'Owner' },
    { key: 'updated_at', label: 'Updated' },
];
const STORAGE_KEY = 'crm.clients.columns';

function loadColumns() {
    const all = COLUMNS.map((c) => c.key);
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : null;

        if (Array.isArray(parsed)) {
            return parsed.filter((k) => all.includes(k));
        }
    } catch {
        // storage unavailable or corrupt: use defaults
    }

    return all;
}

const visibleColumns = ref(loadColumns());

function toggleColumn(key, on) {
    visibleColumns.value = on
        ? [...visibleColumns.value, key]
        : visibleColumns.value.filter((k) => k !== key);

    try {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(visibleColumns.value),
        );
    } catch {
        // ignore: preference just won't persist
    }
}

const isVisible = (key) => visibleColumns.value.includes(key);

// --- selection and bulk actions ------------------------------------------
const selected = ref([]);
const allSelected = computed(
    () =>
        props.clients.data.length > 0 &&
        props.clients.data.every((c) => selected.value.includes(c.id)),
);

function toggleAll(on) {
    selected.value = on === true ? props.clients.data.map((c) => c.id) : [];
}

function toggleRow(id, on) {
    selected.value =
        on === true
            ? [...new Set([...selected.value, id])]
            : selected.value.filter((x) => x !== id);
}

watch(
    () => props.clients.data,
    () => {
        const ids = props.clients.data.map((c) => c.id);
        selected.value = selected.value.filter((id) => ids.includes(id));
    },
);

const bulkStatus = ref('active');
const bulkOwner = ref('');
// The dialog closes (AlertDialogAction is a DialogClose) before its @click runs,
// so `pending` must outlive the close; it is cleared in onFinish.
const pending = ref(null);
const confirmOpen = ref(false);

function ask(action) {
    const n = selected.value.length;
    const noun = `${n} client${n === 1 ? '' : 's'}`;

    if (action === 'delete') {
        pending.value = {
            action,
            title: `Delete ${noun}?`,
            text: 'They will be removed from your lists.',
        };
    } else if (action === 'set_status') {
        const label = optionLabel(props.options.statuses, bulkStatus.value);
        pending.value = {
            action,
            title: `Set status to ${label}?`,
            text: `Applies to ${noun}.`,
        };
    } else {
        const name = props.agents?.find(
            (a) => String(a.id) === bulkOwner.value,
        )?.name;
        pending.value = {
            action,
            title: `Reassign to ${name}?`,
            text: `${noun} will move to ${name}.`,
        };
    }

    confirmOpen.value = true;
}

function confirmBulk() {
    if (!pending.value) {
        return;
    }

    const payload = {
        ids: selected.value,
        action: pending.value.action,
    };

    if (pending.value.action === 'set_status') {
        payload.status = bulkStatus.value;
    }

    if (pending.value.action === 'reassign') {
        payload.owner_id = Number(bulkOwner.value);
    }

    router.post(route('app.clients.bulk'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
        },
        onError: (errors) => {
            toast.error(
                Object.values(errors)[0] ??
                    'That did not work. Please try again.',
            );
        },
        onFinish: () => {
            pending.value = null;
        },
    });
}

// --- new client sheet ---------------------------------------------------
const sheetOpen = ref(false);
useOpenOnNew(sheetOpen, { cleanUrl: () => route('app.clients.index') });

const roleLabel = (v) => optionLabel(props.options.roles, v) ?? v;
const sourceLabel = (v) => optionLabel(props.options.sources, v) ?? '—';
const colSpan = computed(() => 2 + visibleColumns.value.length);
const sorted = reactive({ is: (k) => props.filters.sort === k });
</script>

<template>
    <Head title="Clients" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading title="Clients" :description="`${clients.total} total`" />
            <Button data-test="new-client" @click="sheetOpen = true">
                <Plus />
                New client
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Input
                v-model="search"
                class="w-64"
                aria-label="Search clients"
                placeholder="Search name, email or phone"
                data-test="client-search"
            />

            <Select
                :model-value="filters.role ?? ALL"
                @update:model-value="(v) => visit({ role: String(v) })"
            >
                <SelectTrigger class="w-36" aria-label="Filter by role"
                    ><SelectValue placeholder="Role"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">All roles</SelectItem>
                    <SelectItem
                        v-for="r in options.roles"
                        :key="r.value"
                        :value="r.value"
                        >{{ r.label }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.status ?? ALL"
                @update:model-value="(v) => visit({ status: String(v) })"
            >
                <SelectTrigger class="w-36" aria-label="Filter by status"
                    ><SelectValue placeholder="Status"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">All statuses</SelectItem>
                    <SelectItem
                        v-for="s in options.statuses"
                        :key="s.value"
                        :value="s.value"
                        >{{ s.label }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.tag ?? ALL"
                @update:model-value="(v) => visit({ tag: String(v) })"
            >
                <SelectTrigger class="w-36" aria-label="Filter by tag"
                    ><SelectValue placeholder="Tag"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">All tags</SelectItem>
                    <SelectItem v-for="t in tags" :key="t" :value="t">{{
                        t
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <Select
                v-if="agents"
                :model-value="filters.owner_id ? String(filters.owner_id) : ALL"
                @update:model-value="(v) => visit({ owner_id: String(v) })"
            >
                <SelectTrigger class="w-40" aria-label="Filter by owner"
                    ><SelectValue placeholder="Owner"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">All owners</SelectItem>
                    <SelectItem
                        v-for="a in agents"
                        :key="a.id"
                        :value="String(a.id)"
                        >{{ a.name }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="outline"
                        class="ml-auto"
                        data-test="column-picker"
                    >
                        <Columns3 />
                        Columns
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Visible columns</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                        v-for="c in COLUMNS"
                        :key="c.key"
                        :model-value="isVisible(c.key)"
                        @update:model-value="
                            (v) => toggleColumn(c.key, v === true)
                        "
                    >
                        {{ c.label }}
                    </DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div
            v-if="selected.length"
            class="flex flex-wrap items-center gap-2 rounded-md border bg-muted p-2"
            data-test="bulk-bar"
        >
            <span class="px-2 text-sm">{{ selected.length }} selected</span>

            <Select v-model="bulkStatus">
                <SelectTrigger class="w-36" aria-label="Status to set"
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
            <Button
                variant="outline"
                data-test="bulk-set-status"
                @click="ask('set_status')"
                >Set status</Button
            >

            <template v-if="agents">
                <Select v-model="bulkOwner">
                    <SelectTrigger
                        class="w-40"
                        aria-label="Agent to reassign to"
                        ><SelectValue placeholder="Reassign to…"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="a in agents"
                            :key="a.id"
                            :value="String(a.id)"
                            >{{ a.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Button
                    variant="outline"
                    :disabled="!bulkOwner"
                    data-test="bulk-reassign"
                    @click="ask('reassign')"
                >
                    Reassign
                </Button>
            </template>

            <Button
                variant="destructive"
                data-test="bulk-delete"
                @click="ask('delete')"
                >Delete</Button
            >
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-10">
                        <Checkbox
                            :model-value="allSelected"
                            aria-label="Select all"
                            @update:model-value="toggleAll"
                        />
                    </TableHead>
                    <TableHead>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="sortBy('name')"
                        >
                            Name
                            <ArrowUp
                                v-if="
                                    sorted.is('name') &&
                                    filters.direction === 'asc'
                                "
                            />
                            <ArrowDown v-else-if="sorted.is('name')" />
                            <ChevronsUpDown v-else />
                        </Button>
                    </TableHead>
                    <TableHead v-if="isVisible('contact')">Contact</TableHead>
                    <TableHead v-if="isVisible('roles')">Roles</TableHead>
                    <TableHead v-if="isVisible('status')">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="sortBy('status')"
                        >
                            Status
                            <ArrowUp
                                v-if="
                                    sorted.is('status') &&
                                    filters.direction === 'asc'
                                "
                            />
                            <ArrowDown v-else-if="sorted.is('status')" />
                            <ChevronsUpDown v-else />
                        </Button>
                    </TableHead>
                    <TableHead v-if="isVisible('source')">Source</TableHead>
                    <TableHead v-if="isVisible('tags')">Tags</TableHead>
                    <TableHead v-if="isVisible('owner')">Owner</TableHead>
                    <TableHead v-if="isVisible('updated_at')">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="sortBy('updated_at')"
                        >
                            Updated
                            <ArrowUp
                                v-if="
                                    sorted.is('updated_at') &&
                                    filters.direction === 'asc'
                                "
                            />
                            <ArrowDown v-else-if="sorted.is('updated_at')" />
                            <ChevronsUpDown v-else />
                        </Button>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableEmpty v-if="clients.data.length === 0" :colspan="colSpan">
                    No clients found.
                </TableEmpty>
                <TableRow
                    v-for="c in clients.data"
                    :key="c.id"
                    :data-state="
                        selected.includes(c.id) ? 'selected' : undefined
                    "
                >
                    <TableCell>
                        <Checkbox
                            :model-value="selected.includes(c.id)"
                            :aria-label="`Select ${c.name}`"
                            @update:model-value="(v) => toggleRow(c.id, v)"
                        />
                    </TableCell>
                    <TableCell>
                        <Link
                            :href="route('app.clients.show', c.id)"
                            class="font-medium hover:underline"
                            >{{ c.name }}</Link
                        >
                    </TableCell>
                    <TableCell v-if="isVisible('contact')">
                        <div class="text-sm">{{ c.email ?? '—' }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ c.phone ?? '' }}
                        </div>
                    </TableCell>
                    <TableCell v-if="isVisible('roles')">
                        <div class="flex flex-wrap gap-1">
                            <Badge
                                v-for="r in c.roles"
                                :key="r"
                                variant="outline"
                                >{{ roleLabel(r) }}</Badge
                            >
                        </div>
                    </TableCell>
                    <TableCell v-if="isVisible('status')"
                        ><StatusBadge :status="c.status"
                    /></TableCell>
                    <TableCell v-if="isVisible('source')">{{
                        sourceLabel(c.source)
                    }}</TableCell>
                    <TableCell v-if="isVisible('tags')">
                        <div class="flex flex-wrap gap-1">
                            <Badge
                                v-for="t in c.tags"
                                :key="t"
                                variant="secondary"
                                >{{ t }}</Badge
                            >
                        </div>
                    </TableCell>
                    <TableCell v-if="isVisible('owner')">{{
                        c.owner.name
                    }}</TableCell>
                    <TableCell v-if="isVisible('updated_at')">{{
                        c.updated_at ? relative(c.updated_at) : '—'
                    }}</TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <Pager :page="clients" />
    </div>

    <Sheet v-model:open="sheetOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>New client</SheetTitle>
                <SheetDescription
                    >Add a person you are working with.</SheetDescription
                >
            </SheetHeader>
            <ClientForm
                v-if="sheetOpen"
                :options="options"
                :agents="agents"
                @saved="sheetOpen = false"
            />
        </SheetContent>
    </Sheet>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="pending?.title"
        :description="pending?.text"
        confirm-label="Confirm"
        confirm-test="bulk-confirm"
        @confirm="confirmBulk"
    />
</template>

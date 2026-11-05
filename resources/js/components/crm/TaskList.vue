<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { date } from '@/lib/format';
import { route } from '@/lib/route';

defineProps({
    tasks: { type: Array, required: true },
    emptyText: String,
    editable: Boolean,
});
const emit = defineEmits(['edit']);

const priorityVariant = {
    high: 'default',
    normal: 'secondary',
    low: 'outline',
};

const priorityLabel = { high: 'High', normal: 'Normal', low: 'Low' };

const isOverdue = (t) =>
    !t.completed_at && !!t.due_at && new Date(t.due_at).getTime() < Date.now();

function toggle(t, checked) {
    router.patch(
        route('app.tasks.update', t.id),
        { completed: checked === true },
        { preserveScroll: true },
    );
}

function remove(t) {
    router.delete(route('app.tasks.destroy', t.id), { preserveScroll: true });
}
</script>

<template>
    <p v-if="tasks.length === 0" class="text-sm text-muted-foreground">
        {{ emptyText ?? 'No tasks.' }}
    </p>
    <ul v-else class="flex flex-col gap-2">
        <li
            v-for="t in tasks"
            :key="t.id"
            class="flex items-center gap-3 text-sm"
            data-test="task-row"
        >
            <Checkbox
                :model-value="!!t.completed_at"
                :aria-label="`Complete ${t.title}`"
                data-test="task-toggle"
                @update:model-value="(v) => toggle(t, v)"
            />
            <div class="flex min-w-0 flex-1 flex-col">
                <span
                    class="truncate"
                    :class="{
                        'text-muted-foreground line-through': t.completed_at,
                    }"
                    >{{ t.title }}</span
                >
                <span
                    class="flex flex-wrap gap-x-2 text-xs text-muted-foreground"
                >
                    <Link
                        v-if="t.contact"
                        :href="route('app.clients.show', t.contact.id)"
                        class="hover:underline"
                        >{{ t.contact.name }}</Link
                    >
                    <Link
                        v-if="t.property"
                        :href="route('app.properties.show', t.property.slug)"
                        class="hover:underline"
                        >{{ t.property.title }}</Link
                    >
                    <Link
                        v-if="t.deal"
                        :href="route('app.deals.show', t.deal.id)"
                        class="hover:underline"
                        >{{ t.deal.title }}</Link
                    >
                    <span>Assignee: {{ t.owner.name }}</span>
                </span>
            </div>
            <span
                v-if="t.due_at"
                class="shrink-0 text-xs"
                :class="
                    isOverdue(t)
                        ? 'font-medium text-destructive'
                        : 'text-muted-foreground'
                "
                data-test="task-due"
            >
                {{ date(t.due_at, true) }}
            </span>
            <Badge :variant="priorityVariant[t.priority]">{{
                priorityLabel[t.priority]
            }}</Badge>
            <Button
                v-if="editable"
                size="icon"
                variant="ghost"
                aria-label="Edit task"
                @click="emit('edit', t)"
            >
                <Pencil />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                aria-label="Delete task"
                data-test="task-delete"
                @click="remove(t)"
            >
                <Trash2 />
            </Button>
        </li>
    </ul>
</template>

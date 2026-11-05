<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TaskForm from '@/components/crm/TaskForm.vue';
import TaskList from '@/components/crm/TaskList.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useOpenOnNew } from '@/composables/useOpenOnNew';
import { route } from '@/lib/route';

defineProps({
    groups: { type: Object, required: true },
    options: { type: Object, required: true },
    link_options: { type: Object, required: true },
    agents: Array,
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tasks', href: '/app/tasks' }] },
});

const sections = [
    { key: 'overdue', title: 'Overdue', empty: 'Nothing overdue.' },
    { key: 'today', title: 'Today', empty: 'Nothing else due today.' },
    { key: 'upcoming', title: 'Upcoming', empty: 'Nothing coming up.' },
    { key: 'no_date', title: 'No date', empty: 'No undated tasks.' },
    {
        key: 'completed',
        title: 'Completed (last 7 days)',
        empty: 'Nothing completed this week.',
    },
];

const sheetOpen = ref(false);
const editing = ref();
const formKey = ref(0);

function openForm(task) {
    editing.value = task;
    formKey.value++;
    sheetOpen.value = true;
}

useOpenOnNew(sheetOpen, {
    cleanUrl: () => route('app.tasks.index'),
    open: () => openForm(),
});

const title = ref('');
const due = ref('');
const adding = ref(false);

function quickAdd() {
    if (!title.value.trim()) {
        return;
    }

    adding.value = true;
    router.post(
        route('app.tasks.store'),
        { title: title.value.trim(), due_at: due.value || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                title.value = '';
                due.value = '';
            },
            onFinish: () => (adding.value = false),
        },
    );
}
</script>

<template>
    <Head title="Tasks" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                title="Tasks"
                description="Follow-ups for you and your team"
            />
            <Button data-test="new-task" @click="openForm()">
                <Plus />
                New task
            </Button>
        </div>

        <form
            class="flex flex-wrap gap-2"
            data-test="quick-add"
            @submit.prevent="quickAdd"
        >
            <Input
                v-model="title"
                class="min-w-60 flex-1"
                placeholder="Add a task and press Enter"
                aria-label="Task title"
                data-test="quick-add-title"
            />
            <Input
                v-model="due"
                type="date"
                class="w-44"
                aria-label="Due date"
                data-test="quick-add-due"
            />
            <Button
                type="submit"
                :disabled="adding || !title.trim()"
                data-test="quick-add-submit"
                >Add</Button
            >
        </form>

        <Card v-for="s in sections" :key="s.key" :data-test="`group-${s.key}`">
            <CardHeader>
                <CardTitle
                    class="text-base"
                    :class="{
                        'text-destructive':
                            s.key === 'overdue' && groups.overdue.length,
                    }"
                >
                    {{ s.title }} ({{ groups[s.key].length }})
                </CardTitle>
            </CardHeader>
            <CardContent>
                <TaskList
                    :tasks="groups[s.key]"
                    :empty-text="s.empty"
                    editable
                    @edit="openForm"
                />
            </CardContent>
        </Card>
    </div>

    <Sheet v-model:open="sheetOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>{{
                    editing ? 'Edit task' : 'New task'
                }}</SheetTitle>
                <SheetDescription
                    >Optionally link it to a client, property or
                    deal.</SheetDescription
                >
            </SheetHeader>
            <TaskForm
                v-if="sheetOpen"
                :key="formKey"
                :task="editing"
                :link-options="link_options"
                :agents="agents"
                show-links
                @saved="sheetOpen = false"
            />
        </SheetContent>
    </Sheet>
</template>

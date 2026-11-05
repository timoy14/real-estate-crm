<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue';
import TaskList from '@/components/crm/TaskList.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { date, TZ } from '@/lib/format';
import { route } from '@/lib/route';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Day', href: '/app/my-day' }],
    },
});

defineProps({
    viewingsToday: { type: Array, required: true },
    overdueTasks: { type: Array, required: true },
    todayTasks: { type: Array, required: true },
    recentActivity: { type: Array, required: true },
    stats: { type: Object, required: true },
});

const page = usePage();
const firstName = computed(
    () => (page.props.auth.user?.name ?? '').split(' ')[0],
);

const greeting = computed(() => {
    const hour = +new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        hour12: false,
        timeZone: TZ,
    }).format(new Date());

    return hour < 12
        ? 'Good morning'
        : hour < 18
          ? 'Good afternoon'
          : 'Good evening';
});

const time = (iso) =>
    new Intl.DateTimeFormat('en-US', {
        timeStyle: 'short',
        timeZone: TZ,
    }).format(new Date(iso));

const title = ref('');
const adding = ref(false);

function quickAdd() {
    if (!title.value.trim()) {
        return;
    }

    adding.value = true;
    // No due date given: default to today so it shows up on this page.
    router.post(
        route('app.tasks.store'),
        {
            title: title.value.trim(),
            due_at: new Date().toLocaleDateString('en-CA', { timeZone: TZ }),
        },
        {
            preserveScroll: true,
            onSuccess: () => (title.value = ''),
            onFinish: () => (adding.value = false),
        },
    );
}
</script>

<template>
    <Head title="My Day" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold" data-test="greeting">
                {{ greeting }}, {{ firstName }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ date(new Date().toISOString()) }}
            </p>
        </div>

        <div
            class="grid grid-cols-[minmax(0,1fr)] gap-4 sm:grid-cols-3"
            data-test="stats"
        >
            <Card>
                <CardHeader>
                    <CardDescription>Open deals</CardDescription>
                    <CardTitle class="text-3xl" data-test="stat-open-deals">{{
                        stats.openDeals
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Viewings this week</CardDescription>
                    <CardTitle class="text-3xl" data-test="stat-viewings">{{
                        stats.viewingsThisWeek
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Tasks due today</CardDescription>
                    <CardTitle class="text-3xl" data-test="stat-tasks">{{
                        stats.tasksDueToday
                    }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 2xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Today's viewings</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="viewingsToday.length === 0"
                        class="text-sm text-muted-foreground"
                        data-test="viewings-empty"
                    >
                        No viewings scheduled today.
                    </p>
                    <ol
                        v-else
                        class="flex flex-col gap-4"
                        data-test="viewings-today"
                    >
                        <li
                            v-for="v in viewingsToday"
                            :key="v.id"
                            class="flex items-center gap-3"
                            data-test="viewing-item"
                        >
                            <span class="w-20 shrink-0 text-sm font-medium">{{
                                time(v.scheduled_at)
                            }}</span>
                            <img
                                v-if="v.property"
                                :src="v.property.cover"
                                alt=""
                                class="size-12 shrink-0 rounded-md object-cover"
                            />
                            <div class="flex min-w-0 flex-col text-sm">
                                <Link
                                    v-if="v.property"
                                    :href="
                                        route(
                                            'app.properties.show',
                                            v.property.slug,
                                        )
                                    "
                                    class="truncate font-medium hover:underline"
                                >
                                    {{ v.property.title }}
                                </Link>
                                <Link
                                    v-if="v.contact"
                                    :href="
                                        route('app.clients.show', v.contact.id)
                                    "
                                    class="truncate text-muted-foreground hover:underline"
                                >
                                    {{ v.contact.name }}
                                </Link>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Tasks</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <form
                        class="flex gap-2"
                        data-test="quick-add"
                        @submit.prevent="quickAdd"
                    >
                        <Input
                            v-model="title"
                            class="flex-1"
                            placeholder="Add a task for today and press Enter"
                            aria-label="Task title"
                            data-test="quick-add-title"
                        />
                        <Button
                            type="submit"
                            :disabled="adding || !title.trim()"
                            data-test="quick-add-submit"
                            >Add</Button
                        >
                    </form>
                    <section
                        v-if="overdueTasks.length"
                        data-test="overdue-tasks"
                    >
                        <h3 class="mb-2 text-sm font-medium text-destructive">
                            Overdue
                        </h3>
                        <TaskList :tasks="overdueTasks" />
                    </section>
                    <section>
                        <h3 class="mb-2 text-sm font-medium">Today</h3>
                        <TaskList
                            :tasks="todayTasks"
                            empty-text="Nothing else due today."
                        />
                    </section>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Recent activity</CardTitle>
                <CardDescription
                    >What others on the team logged on your
                    records.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <ActivityTimeline
                    :activities="recentActivity"
                    empty-text="Nothing new from the team."
                />
            </CardContent>
        </Card>
    </div>
</template>

<script setup>
import { CalendarClock, ListPlus, MessageSquarePlus } from '@lucide/vue';
import { ref } from 'vue';
import LogActivityForm from '@/components/crm/LogActivityForm.vue';
import ScheduleViewingForm from '@/components/crm/ScheduleViewingForm.vue';
import TaskForm from '@/components/crm/TaskForm.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

defineProps({
    contactId: Number,
    propertyId: Number,
    dealId: Number,
    linkOptions: { type: Object, required: true },
    agents: Array,
});

const logOpen = ref(false);
const viewingOpen = ref(false);
const taskOpen = ref(false);

// Log activity: start typing right away (the sheet would otherwise focus its first control).
function focusBody(e) {
    e.preventDefault();
    document.getElementById('activity-body')?.focus();
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button
            variant="secondary"
            data-test="log-activity"
            @click="logOpen = true"
            ><MessageSquarePlus />Log activity</Button
        >
        <Button
            variant="secondary"
            data-test="schedule-viewing"
            @click="viewingOpen = true"
            ><CalendarClock />Schedule viewing</Button
        >
        <Button
            variant="secondary"
            data-test="add-task"
            @click="taskOpen = true"
            ><ListPlus />Add task</Button
        >
    </div>

    <Sheet v-model:open="logOpen">
        <SheetContent class="overflow-y-auto" @open-auto-focus="focusBody">
            <SheetHeader>
                <SheetTitle>Log activity</SheetTitle>
                <SheetDescription
                    >A call, email, meeting or note.</SheetDescription
                >
            </SheetHeader>
            <LogActivityForm
                v-if="logOpen"
                :contact-id="contactId"
                :property-id="propertyId"
                :deal-id="dealId"
                :link-options="linkOptions"
                @saved="logOpen = false"
            />
        </SheetContent>
    </Sheet>

    <Sheet v-model:open="viewingOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>Schedule viewing</SheetTitle>
                <SheetDescription
                    >Book a property viewing for a client.</SheetDescription
                >
            </SheetHeader>
            <ScheduleViewingForm
                v-if="viewingOpen"
                :contact-id="contactId"
                :property-id="propertyId"
                :deal-id="dealId"
                :link-options="linkOptions"
                @saved="viewingOpen = false"
            />
        </SheetContent>
    </Sheet>

    <Sheet v-model:open="taskOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>Add task</SheetTitle>
                <SheetDescription
                    >A follow-up linked to this record.</SheetDescription
                >
            </SheetHeader>
            <TaskForm
                v-if="taskOpen"
                :contact-id="contactId"
                :property-id="propertyId"
                :deal-id="dealId"
                :link-options="linkOptions"
                :agents="agents"
                @saved="taskOpen = false"
            />
        </SheetContent>
    </Sheet>
</template>

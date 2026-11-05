<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { route } from '@/lib/route';

const props = defineProps({
    propertySlug: { type: String, required: true },
    propertyTitle: { type: String, required: true },
    agentName: String,
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    message: `Hi, I'm interested in ${props.propertyTitle}. Please contact me.`,
    wants_viewing: false,
    preferred_contact: 'email',
    consent: false,
    // Honeypot: real visitors never see or fill this.
    website: '',
});

const done = ref(false);
const thanksName = ref('');
const localErrors = ref({});

const err = (key) => localErrors.value[key] ?? form.errors[key];

const thanks = computed(
    () =>
        `Thanks, ${thanksName.value}! ${props.agentName ?? 'Our team'} will contact you soon.`,
);

function submit() {
    const errors = {};

    if (!form.name.trim()) {
        errors.name = 'Please enter your name.';
    }

    if (!form.email.trim() && !form.phone.trim()) {
        errors.email = 'Enter an email or a phone number so we can reach you.';
    }

    if (!form.message.trim()) {
        errors.message = 'Please write a message.';
    }

    if (!form.consent) {
        errors.consent = 'Please agree to be contacted.';
    }

    localErrors.value = errors;

    if (Object.keys(errors).length > 0) {
        return;
    }

    form.post(route('listings.inquire', props.propertySlug), {
        preserveScroll: true,
        onSuccess: () => {
            thanksName.value = form.name.trim().split(' ')[0];
            done.value = true;
            form.reset();
        },
        // The submit route is throttled; a public visitor should not get Inertia's error modal.
        onHttpException: (response) => {
            if (response.status === 429) {
                localErrors.value = {
                    property:
                        'Too many messages. Please wait a minute and try again.',
                };

                return false;
            }
        },
    });
}
</script>

<template>
    <Card data-test="inquiry-form">
        <template v-if="done">
            <CardHeader>
                <CardTitle>Message sent</CardTitle>
            </CardHeader>
            <CardContent>
                <p data-test="inquiry-thanks">{{ thanks }}</p>
            </CardContent>
        </template>

        <template v-else>
            <CardHeader>
                <CardTitle>Interested in this property?</CardTitle>
                <CardDescription
                    >Send a message and we will get back to
                    you.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <form
                    class="flex flex-col gap-4"
                    novalidate
                    @submit.prevent="submit"
                >
                    <p
                        v-if="err('property')"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ err('property') }}
                    </p>

                    <div class="flex flex-col gap-2">
                        <Label for="inq-name">Name</Label>
                        <Input
                            id="inq-name"
                            v-model="form.name"
                            autocomplete="name"
                            maxlength="120"
                            required
                        />
                        <p v-if="err('name')" class="text-sm text-destructive">
                            {{ err('name') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="inq-email">Email</Label>
                        <Input
                            id="inq-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            maxlength="254"
                        />
                        <p
                            v-if="err('email')"
                            class="text-sm text-destructive"
                            data-test="contact-error"
                        >
                            {{ err('email') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="inq-phone">Phone</Label>
                        <Input
                            id="inq-phone"
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            maxlength="30"
                        />
                        <p
                            v-if="form.errors.phone"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="inq-message">Message</Label>
                        <Textarea
                            id="inq-message"
                            v-model="form.message"
                            rows="4"
                            maxlength="2000"
                        />
                        <p
                            v-if="err('message')"
                            class="text-sm text-destructive"
                        >
                            {{ err('message') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="inq-viewing"
                            :model-value="form.wants_viewing"
                            @update:model-value="
                                form.wants_viewing = $event === true
                            "
                        />
                        <Label for="inq-viewing"
                            >I'd like to book a viewing</Label
                        >
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label>Preferred contact</Label>
                        <RadioGroup
                            v-model="form.preferred_contact"
                            class="flex flex-wrap gap-4"
                        >
                            <div
                                v-for="o in [
                                    ['email', 'Email'],
                                    ['phone', 'Phone'],
                                    ['whatsapp', 'WhatsApp'],
                                ]"
                                :key="o[0]"
                                class="flex items-center gap-2"
                            >
                                <RadioGroupItem
                                    :id="`inq-pref-${o[0]}`"
                                    :value="o[0]"
                                />
                                <Label :for="`inq-pref-${o[0]}`">{{
                                    o[1]
                                }}</Label>
                            </div>
                        </RadioGroup>
                    </div>

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="inq-consent"
                                :model-value="form.consent"
                                @update:model-value="
                                    form.consent = $event === true
                                "
                            />
                            <Label for="inq-consent"
                                >I agree to be contacted about this
                                property.</Label
                            >
                        </div>
                        <p
                            v-if="err('consent')"
                            class="text-sm text-destructive"
                        >
                            {{ err('consent') }}
                        </p>
                    </div>

                    <!-- Honeypot, hidden from people and assistive tech. -->
                    <div
                        class="absolute h-0 w-0 overflow-hidden"
                        aria-hidden="true"
                    >
                        <label>
                            Website
                            <input
                                v-model="form.website"
                                type="text"
                                name="website"
                                tabindex="-1"
                                autocomplete="off"
                            />
                        </label>
                    </div>

                    <Button type="submit" :disabled="form.processing"
                        >Send inquiry</Button
                    >
                </form>
            </CardContent>
        </template>
    </Card>
</template>

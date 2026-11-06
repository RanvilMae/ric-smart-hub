<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Camera, KeyRound, Save, ShieldCheck, User } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Account = {
    name: string;
    email: string;
    contact_number: string | null;
    position: string | null;
    office: string | null;
    role: string;
    region: string;
    photo_url: string | null;
    last_updated: string | null;
};

const props = defineProps<{ account: Account }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My account', href: '/admin/my-account' }],
    },
});

type Tab = 'details' | 'photo';
const tab = ref<Tab>('details');
const tabs: { value: Tab; label: string; icon: typeof User }[] = [
    { value: 'details', label: 'Account details', icon: User },
    { value: 'photo', label: 'Photo', icon: Camera },
];

// ---- Details
const form = useForm({
    name: props.account.name,
    contact_number: props.account.contact_number ?? '',
    position: props.account.position ?? '',
    office: props.account.office ?? '',
});

const save = () => form.put('/admin/my-account', { preserveScroll: true });

// ---- Photo
const photoForm = useForm<{ photo: File | null }>({ photo: null });
const preview = ref<string | null>(null);

const pickPhoto = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    photoForm.photo = file;
    preview.value = file ? URL.createObjectURL(file) : null;
};

const uploadPhoto = () =>
    photoForm.post('/admin/my-account/photo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoForm.reset();
            preview.value = null;
        },
    });

const initials = computed(() =>
    props.account.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <Head title="My account" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex size-16 items-center justify-center overflow-hidden rounded-full border-2 border-primary/30 bg-accent">
                <img v-if="account.photo_url" :src="account.photo_url" alt="" class="size-full object-cover" />
                <span v-else class="text-lg font-semibold text-accent-foreground">{{ initials }}</span>
            </div>
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold">{{ account.name }}</h1>
                <p class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span class="inline-flex items-center gap-1 rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">
                        <ShieldCheck class="size-3" /> {{ account.role }}
                    </span>
                    {{ account.region }}
                </p>
            </div>
        </div>

        <!-- Tabs -->
        <nav class="flex w-fit gap-1 rounded-lg bg-muted p-1 text-sm">
            <button
                v-for="t in tabs"
                :key="t.value"
                type="button"
                class="flex items-center gap-2 rounded-md px-3 py-1.5 transition"
                :class="tab === t.value ? 'bg-background font-medium shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                @click="tab = t.value"
            >
                <component :is="t.icon" class="size-4" />
                {{ t.label }}
            </button>
        </nav>

        <!-- 1. Account details -->
        <div v-if="tab === 'details'" class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Account details</CardTitle>
                    <CardDescription v-if="account.last_updated">Last updated {{ account.last_updated }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="name">Full name *</Label>
                            <Input id="name" v-model="form.name" autocomplete="name" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact_number">Contact number</Label>
                            <Input id="contact_number" v-model="form.contact_number" type="tel" placeholder="09171234567" />
                            <InputError :message="form.errors.contact_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="position">Position / designation</Label>
                            <Input id="position" v-model="form.position" placeholder="e.g. Regional RIC Coordinator" />
                            <InputError :message="form.errors.position" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="office">Office</Label>
                            <Input id="office" v-model="form.office" placeholder="e.g. ATI Regional Training Center II" />
                            <InputError :message="form.errors.office" />
                        </div>
                        <div class="sm:col-span-2">
                            <Button type="submit" :disabled="form.processing || !form.isDirty">
                                <Spinner v-if="form.processing" />
                                <Save v-else />
                                Save changes
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="text-base">Login and role</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <div>
                        <p class="text-muted-foreground">Email (login)</p>
                        <p class="font-medium break-all">{{ account.email }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Role</p>
                        <p class="font-medium">{{ account.role }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Region</p>
                        <p class="font-medium">{{ account.region }}</p>
                    </div>
                    <div class="flex flex-col gap-2 border-t pt-3">
                        <Link href="/settings/profile" class="text-primary hover:underline">Change email →</Link>
                        <Link href="/settings/security" class="inline-flex items-center gap-1 text-primary hover:underline">
                            <KeyRound class="size-3.5" /> Change password →
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- 2. Photo -->
        <Card v-if="tab === 'photo'" class="max-w-xl">
            <CardHeader>
                <CardTitle class="text-base">Profile photo</CardTitle>
                <CardDescription>JPG, PNG or WEBP, up to 2 MB.</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="flex flex-col gap-5 sm:flex-row sm:items-start" @submit.prevent="uploadPhoto">
                    <div class="flex size-36 shrink-0 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed bg-muted">
                        <img v-if="preview || account.photo_url" :src="preview ?? account.photo_url ?? ''" alt="Photo preview" class="size-full object-cover" />
                        <Camera v-else class="size-8 text-muted-foreground" />
                    </div>
                    <div class="grid flex-1 gap-3">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-2 file:text-sm file:font-medium file:text-accent-foreground hover:file:bg-accent/80"
                            @change="pickPhoto"
                        />
                        <InputError :message="photoForm.errors.photo" />
                        <Button type="submit" class="w-fit" :disabled="!photoForm.photo || photoForm.processing">
                            <Spinner v-if="photoForm.processing" />
                            <Camera v-else />
                            {{ account.photo_url ? 'Replace photo' : 'Upload photo' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>

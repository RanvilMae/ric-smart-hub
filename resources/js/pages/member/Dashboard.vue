<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, CalendarPlus, Camera, Globe, Home, IdCard, Lock, MapPin, Receipt, Users } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    member: {
        first_name: string;
        member_code: string | null;
        ric_organization: string | null;
        region: string | null;
        region_label: string | null;
        province: string | null;
        city: string | null;
        photo: boolean;
    };
    national: { members: number; organizations: number; regions: number; thisYear: number };
    region: {
        members: number;
        organizations: number;
        province: number | null;
        city: number | null;
        myOrganization: number | null;
        thisYear: number;
    };
    payment: { status: 'pending' | 'confirmed' | 'rejected' | null; confirmed: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: '' }],
    },
});

const n = (v: number) => v.toLocaleString();
const year = new Date().getFullYear();

const regionTitle = computed(() =>
    props.member.region_label && props.member.region_label !== props.member.region
        ? `${props.member.region} – ${props.member.region_label}`
        : props.member.region,
);

// Share of the national database that comes from the member's region
const regionShare = computed(() =>
    props.national.members ? Math.round((props.region.members / props.national.members) * 100) : 0,
);

// What the member still needs to do for their digital ID
const nextStep = computed(() => {
    if (props.payment.confirmed) {
        return { icon: IdCard, title: 'Your digital ID is ready', text: 'View, print or save it as PDF.', link: 'Open my ID', tone: 'ok' };
    }
    if (props.payment.status === 'pending') {
        return {
            icon: Receipt,
            title: 'Payment waiting for confirmation',
            text: 'Your regional admin will check your proof of payment.',
            link: 'View my payment',
            tone: 'wait',
        };
    }
    return {
        icon: Lock,
        title: props.payment.status === 'rejected' ? 'Please upload a new proof of payment' : 'Unlock your digital ID',
        text: 'Upload your proof of membership payment.',
        link: 'Upload proof of payment',
        tone: 'todo',
    };
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Welcome -->
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Mabuhay, {{ member.first_name }}!</h1>
            <p class="text-sm text-muted-foreground">
                <span class="font-mono font-medium text-foreground">{{ member.member_code }}</span>
                <span v-if="member.ric_organization"> · {{ member.ric_organization }}</span>
            </p>
        </div>

        <!-- Next step for the ID -->
        <Link
            href="/my-account"
            class="flex items-center gap-4 rounded-xl border p-4 transition hover:shadow-md"
            :class="{
                'border-emerald-300 bg-emerald-50': nextStep.tone === 'ok',
                'border-amber-300 bg-amber-50': nextStep.tone === 'wait',
                'border-primary/30 bg-accent/60': nextStep.tone === 'todo',
            }"
        >
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white shadow-sm">
                <component :is="nextStep.icon" class="size-5 text-primary" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-medium">{{ nextStep.title }}</p>
                <p class="text-sm text-muted-foreground">{{ nextStep.text }}</p>
            </div>
            <span class="hidden shrink-0 text-sm font-medium text-primary sm:block">{{ nextStep.link }} →</span>
        </Link>
        <Link
            v-if="!member.photo"
            href="/my-account"
            class="-mt-3 flex items-center gap-2 text-sm text-amber-700 hover:underline"
        >
            <Camera class="size-4" /> Add your photo so it appears on your digital ID
        </Link>

        <!-- National database -->
        <section class="grid gap-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                <Globe class="size-4" /> RIC national database
            </h2>
            <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-primary to-teal-700 p-6 text-primary-foreground shadow-lg shadow-primary/20">
                <div class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-yellow-300/20 blur-2xl" />
                <div class="relative grid gap-6 sm:grid-cols-[1.4fr_1fr_1fr_1fr] sm:items-end">
                    <div>
                        <p class="text-sm text-primary-foreground/80">Members nationwide</p>
                        <p class="text-4xl font-bold tabular-nums sm:text-5xl">{{ n(national.members) }}</p>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tabular-nums">{{ n(national.organizations) }}</p>
                        <p class="text-xs text-primary-foreground/80">RIC organizations</p>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tabular-nums">{{ national.regions }}</p>
                        <p class="text-xs text-primary-foreground/80">Regions</p>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tabular-nums">{{ n(national.thisYear) }}</p>
                        <p class="text-xs text-primary-foreground/80">New in {{ year }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Own region -->
        <section class="grid gap-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                <MapPin class="size-4" /> {{ regionTitle }}
            </h2>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <div class="col-span-2 rounded-xl border bg-card p-5 lg:col-span-1">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        Members in the region <Users class="size-4 text-primary" />
                    </div>
                    <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(region.members) }}</p>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary" :style="{ width: `${Math.max(regionShare, 1)}%` }" />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">{{ regionShare }}% of all members nationwide</p>
                </div>
                <div class="rounded-xl border bg-card p-5">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        RIC organizations <Building2 class="size-4 text-primary" />
                    </div>
                    <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(region.organizations) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-5">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        New in {{ year }} <CalendarPlus class="size-4 text-primary" />
                    </div>
                    <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(region.thisYear) }}</p>
                </div>
                <div v-if="region.province !== null" class="rounded-xl border bg-card p-5">
                    <div class="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                        <span class="truncate">In {{ member.province }}</span> <MapPin class="size-4 shrink-0 text-primary" />
                    </div>
                    <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(region.province) }}</p>
                </div>
                <div v-if="region.city !== null" class="rounded-xl border bg-card p-5">
                    <div class="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                        <span class="truncate">In {{ member.city }}</span> <Home class="size-4 shrink-0 text-primary" />
                    </div>
                    <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(region.city) }}</p>
                </div>
                <div v-if="region.myOrganization !== null" class="rounded-xl border bg-card p-5">
                    <div class="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                        <span class="truncate">In my RIC organization</span> <Users class="size-4 shrink-0 text-primary" />
                    </div>
                    <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(region.myOrganization) }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ member.ric_organization }}</p>
                </div>
            </div>
        </section>

        <p class="text-xs text-muted-foreground">Counts include approved members only.</p>
    </div>
</template>
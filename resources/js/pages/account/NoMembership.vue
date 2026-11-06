<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CircleX, Clock, IdCard } from '@lucide/vue';

defineProps<{
    status: 'pending' | 'approved' | 'rejected' | null;
    memberCode: string | null;
    rejectionReason: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My membership & ID', href: '/my-account' }],
    },
});
</script>

<template>
    <Head title="My membership & ID" />

    <div class="flex flex-1 items-start justify-center p-4 md:p-10">
        <!-- Not registered yet -->
        <div
            v-if="!status"
            class="relative w-full max-w-xl overflow-hidden rounded-2xl border-2 border-primary bg-card p-8 text-center shadow-lg shadow-primary/10"
        >
            <div class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-yellow-300/30 blur-2xl" />
            <div class="relative flex flex-col items-center gap-4">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-accent text-accent-foreground">
                    <IdCard class="size-7" />
                </div>
                <h1 class="text-2xl font-semibold">You're not registered as a member yet</h1>
                <p class="text-muted-foreground">
                    Register your RIC membership to get your member ID and digital ID. You'll use the account
                    you're logged in with, so there's no new email or password to create.
                </p>
                <Link
                    href="/my-account/register"
                    class="group mt-2 inline-flex animate-pulse items-center gap-2 rounded-full bg-primary px-8 py-3.5 text-base font-semibold text-primary-foreground shadow-lg shadow-primary/30 ring-4 ring-yellow-300/60 transition hover:animate-none hover:bg-primary/90"
                >
                    Register as a member
                    <ArrowRight class="size-5 transition group-hover:translate-x-0.5" />
                </Link>
            </div>
        </div>

        <!-- Waiting for approval -->
        <div v-else-if="status === 'pending'" class="w-full max-w-xl rounded-2xl border bg-card p-8 text-center">
            <Clock class="mx-auto mb-4 size-12 text-amber-500" />
            <h1 class="text-xl font-semibold">Your membership is waiting for approval</h1>
            <p v-if="memberCode" class="mt-3 inline-block rounded-lg bg-accent px-4 py-2 text-sm text-accent-foreground">
                Your RIC member ID: <span class="font-mono font-semibold">{{ memberCode }}</span>
            </p>
            <p class="mt-3 text-sm text-muted-foreground">
                Your digital ID will be available here once your registration is approved.
            </p>
        </div>

        <!-- Rejected -->
        <div v-else class="w-full max-w-xl rounded-2xl border bg-card p-8 text-center">
            <CircleX class="mx-auto mb-4 size-12 text-destructive" />
            <h1 class="text-xl font-semibold">Your membership was not approved</h1>
            <p v-if="rejectionReason" class="mt-3 rounded-md border bg-muted p-3 text-left text-sm">
                <span class="font-medium">Reason:</span> {{ rejectionReason }}
            </p>
            <p class="mt-3 text-sm text-muted-foreground">Please contact the RIC national office.</p>
        </div>
    </div>
</template>

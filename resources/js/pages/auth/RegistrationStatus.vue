<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleX, Clock } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { logout } from '@/routes';

defineProps<{
    status: 'pending' | 'approved' | 'rejected' | null;
    region: string | null;
    submittedAt: string | null;
    rejectionReason: string | null;
    memberCode: string | null;
}>();

defineOptions({
    layout: {
        title: 'Registration status',
        description: 'RIC Smart Hub national database',
    },
});
</script>

<template>
    <Head title="Registration status" />

    <div class="flex flex-col items-center gap-4 text-center">
        <template v-if="status === 'rejected'">
            <CircleX class="size-12 text-destructive" />
            <p class="text-lg font-semibold">Registration not approved</p>
            <p class="text-sm text-muted-foreground">
                The administrator of {{ region }} could not approve your registration.
            </p>
            <p v-if="rejectionReason" class="w-full rounded-md border bg-muted p-3 text-left text-sm">
                <span class="font-medium">Reason:</span> {{ rejectionReason }}
            </p>
            <p class="text-sm text-muted-foreground">
                Please coordinate with your regional RIC office or ATI regional training center.
            </p>
        </template>

        <template v-else-if="status">
            <Clock class="size-12 text-amber-500" />
            <p class="text-lg font-semibold">Waiting for approval</p>
            <p v-if="memberCode" class="rounded-lg border bg-accent px-4 py-2 text-sm text-accent-foreground">Your RIC member ID: <span class="font-mono font-semibold">{{ memberCode }}</span></p>
            <p class="text-sm text-muted-foreground">
                Thank you for registering<template v-if="submittedAt"> on {{ submittedAt }}</template>.
                Your registration is with the administrator of
                <span class="font-medium text-foreground">{{ region }}</span>.
                You can use the RIC Smart Hub once it is approved.
            </p>
        </template>

        <p v-else class="text-sm text-muted-foreground">
            No member profile is linked to this account. Please contact the RIC national office.
        </p>

        <TextLink :href="logout()" as="button" class="text-sm">Log out</TextLink>
    </div>
</template>
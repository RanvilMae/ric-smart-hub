<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Info, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Member = Record<string, unknown> & {
    id: number;
    full_name: string;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    submitted: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    region: string | null;
};

const props = defineProps<{ member: Member; canReview: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: '/admin/members' }],
    },
});

const sections: { title: string; fields: [string, string][] }[] = [
    {
        title: 'Personal information',
        fields: [
            ['Sex', 'sex'],
            ['Civil status', 'civil_status'],
            ['Date of birth', 'birth_date'],
            ['Highest educational attainment', 'education_attainment'],
            ['Degree', 'degree'],
        ],
    },
    {
        title: 'Address',
        fields: [
            ['Region', 'region'],
            ['Province', 'province'],
            ['City / Municipality', 'city'],
            ['Barangay', 'barangay'],
            ['District', 'district'],
            ['Municipality class', 'municipality_class'],
            ['ZIP code', 'zip_code'],
        ],
    },
    {
        title: 'RIC membership',
        fields: [
            ['Type of member', 'member_type'],
            ['Occupation / position', 'occupation'],
            ['Contact number', 'contact_number'],
            ['Email (login)', 'email'],
            ['Email address 2', 'alternate_email'],
            ['RIC organization', 'ric_organization'],
            ['Organization address', 'organization_address'],
        ],
    },
    {
        title: 'Specialization and ATI services',
        fields: [
            ['Specialization', 'specialization'],
            ['HVCDP commodity', 'hvcdp_commodity'],
            ['ATI services availed', 'ati_services'],
            ['LSA level', 'lsa_level'],
            ['LSA type', 'lsa_type'],
            ['Scholarship', 'ati_scholarship'],
            ['Trainings', 'ati_trainings'],
        ],
    },
];

const show = (key: string) => {
    const v = props.member[key];

    if (Array.isArray(v)) {
        return v.length ? v.join(', ') : '—';
    }

    return v === null || v === undefined || v === '' ? '—' : String(v);
};

const statusClass = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    approved: 'bg-accent text-accent-foreground',
    rejected: 'bg-destructive/10 text-destructive',
};

const approveForm = useForm({});
const rejectForm = useForm({ reason: '' });
const rejectOpen = ref(false);

const doApprove = () => approveForm.post(`/admin/members/${props.member.id}/approve`);
const doReject = () =>
    rejectForm.post(`/admin/members/${props.member.id}/reject`, {
        onSuccess: () => (rejectOpen.value = false),
    });
</script>

<template>
    <Head :title="member.full_name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Link
            :href="`/admin/members?status=${member.status}`"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Back to list
        </Link>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ member.full_name }}</h1>
                    <span v-if="member.member_code" class="rounded-md bg-accent px-2 py-0.5 font-mono text-sm font-medium text-accent-foreground">{{ member.member_code }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass[member.status]">
                        {{ member.status }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">{{ member.region }} · Registered {{ member.submitted }}</p>
                <p v-if="member.reviewer" class="text-sm text-muted-foreground">
                    Reviewed by {{ member.reviewer }} · {{ member.reviewed_at }}
                </p>
            </div>

            <div v-if="canReview && member.status !== 'approved'" class="flex gap-2">
                <Button v-if="member.status === 'pending'" variant="outline" @click="rejectOpen = true">
                    <X /> Reject
                </Button>
                <Button :disabled="approveForm.processing" @click="doApprove">
                    <Spinner v-if="approveForm.processing" />
                    <Check v-else /> Approve
                </Button>
            </div>
        </div>

        <div
            v-if="!canReview && member.status === 'pending'"
            class="flex items-center gap-3 rounded-lg border bg-muted/60 p-4 text-sm text-muted-foreground"
        >
            <Info class="size-4 shrink-0" />
            Only the regional admin of {{ member.region }} can approve or reject this registration.
        </div>

        <div v-if="member.status === 'rejected' && member.rejection_reason" class="rounded-lg border bg-muted p-4 text-sm">
            <span class="font-medium">Rejection reason:</span> {{ member.rejection_reason }}
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-for="section in sections" :key="section.title">
                <CardHeader>
                    <CardTitle class="text-base">{{ section.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-2.5 text-sm">
                        <div v-for="[label, key] in section.fields" :key="key" class="grid gap-0.5 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                            <dt class="text-muted-foreground">{{ label }}</dt>
                            <dd>{{ show(key) }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="rejectOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject registration</DialogTitle>
                    <DialogDescription>{{ member.full_name }} will see this reason when they log in.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <textarea
                        v-model="rejectForm.reason"
                        rows="4"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="e.g. Not a registered member of the stated RIC organization."
                        aria-label="Reason for rejection"
                    />
                    <InputError :message="rejectForm.errors.reason" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rejectOpen = false">Cancel</Button>
                    <Button variant="destructive" :disabled="rejectForm.processing" @click="doReject">
                        <Spinner v-if="rejectForm.processing" />
                        Reject registration
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

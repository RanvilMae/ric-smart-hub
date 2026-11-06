<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Check, ExternalLink, FileText, Image as ImageIcon, Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type Status = 'pending' | 'confirmed' | 'rejected';

type Payment = {
    id: number;
    member_id: number;
    member_name: string | null;
    member_code: string | null;
    location: string;
    method: string;
    reference_no: string | null;
    amount: string;
    paid_on: string | null;
    notes: string | null;
    status: Status;
    proof_url: string;
    is_pdf: boolean;
    submitted_at: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    can_review: boolean;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
    last_page: number;
};

const props = defineProps<{
    payments: Paginated<Payment>;
    counts: Record<Status, number>;
    filters: { status: Status | 'all'; region: number | null; search: string };
    regions: { id: number; name: string }[] | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payments', href: '/admin/payments' }],
    },
});

const tabs: { value: Status | 'all'; label: string }[] = [
    { value: 'pending', label: 'To confirm' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'rejected', label: 'Not accepted' },
    { value: 'all', label: 'All' },
];

const statusLabel: Record<Status, string> = { pending: 'To confirm', confirmed: 'Confirmed', rejected: 'Not accepted' };
const statusClass: Record<Status, string> = {
    pending: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-700',
};

const peso = (v: string | number) => `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

// ---- Filters
const search = ref(props.filters.search);
const region = ref<number>(props.filters.region ?? 0);

const reload = (extra: Record<string, unknown> = {}) =>
    router.get(
        '/admin/payments',
        {
            status: props.filters.status === 'pending' ? undefined : props.filters.status,
            search: search.value || undefined,
            region: region.value || undefined,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(search, useDebounceFn(() => reload(), 350));
watch(region, () => reload());

// ---- Confirm / reject
const busyId = ref<number | null>(null);
const confirm = (p: Payment) => {
    busyId.value = p.id;
    router.post(`/admin/payments/${p.id}/confirm`, {}, { preserveScroll: true, onFinish: () => (busyId.value = null) });
};

const rejectTarget = ref<Payment | null>(null);
const rejectForm = useForm({ reason: '' });
const openReject = (p: Payment) => {
    rejectTarget.value = p;
    rejectForm.reset();
    rejectForm.clearErrors();
};
const submitReject = () =>
    rejectTarget.value &&
    rejectForm.post(`/admin/payments/${rejectTarget.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => (rejectTarget.value = null),
    });

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>option]:bg-background';
</script>

<template>
    <Head title="Payments" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <Heading
            title="Membership payments"
            description="Check each proof of payment, then confirm it so the member can view and print their digital ID."
        />

        <!-- Tabs and filters -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <nav class="flex w-fit flex-wrap gap-1 rounded-lg bg-muted p-1 text-sm">
                <button
                    v-for="t in tabs"
                    :key="t.value"
                    type="button"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 transition"
                    :class="filters.status === t.value ? 'bg-background font-medium shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="reload({ status: t.value === 'pending' ? undefined : t.value, page: undefined })"
                >
                    {{ t.label }}
                    <span
                        v-if="t.value !== 'all'"
                        class="rounded-full px-1.5 text-xs tabular-nums"
                        :class="t.value === 'pending' && counts.pending ? 'bg-amber-400 text-amber-950' : 'bg-background/70'"
                    >
                        {{ counts[t.value].toLocaleString() }}
                    </span>
                </button>
            </nav>

            <div class="flex flex-1 flex-col gap-3 sm:flex-row lg:justify-end">
                <select v-if="regions" v-model="region" :class="selectClass" class="sm:w-44" aria-label="Filter by region">
                    <option :value="0">All regions</option>
                    <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
                <div class="relative w-full lg:max-w-xs">
                    <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search name, member ID, reference no.…" class="pl-9" aria-label="Search payments" />
                </div>
            </div>
        </div>

        <!-- List (cards work on phones and desktops) -->
        <div class="grid gap-3">
            <div
                v-for="p in payments.data"
                :key="p.id"
                class="grid gap-4 rounded-xl border bg-card p-4 md:grid-cols-[1fr_auto] md:items-center"
                :class="{ 'border-amber-300 bg-amber-50/50': p.status === 'pending' }"
            >
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <!-- Member -->
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="`/admin/members/${p.member_id}`" class="font-medium text-primary hover:underline">{{ p.member_name }}</Link>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[p.status]">{{ statusLabel[p.status] }}</span>
                        </div>
                        <p class="font-mono text-xs text-muted-foreground">{{ p.member_code }}</p>
                        <p class="text-sm text-muted-foreground">{{ p.location }}</p>
                        <p class="text-xs text-muted-foreground">Submitted {{ p.submitted_at }}</p>
                    </div>

                    <!-- Payment -->
                    <div class="min-w-0 text-sm">
                        <p class="text-lg font-semibold tabular-nums">{{ peso(p.amount) }}</p>
                        <p>{{ p.method }}<span v-if="p.reference_no" class="text-muted-foreground"> · Ref {{ p.reference_no }}</span></p>
                        <p class="text-muted-foreground">Paid {{ p.paid_on }}</p>
                        <p v-if="p.notes" class="text-muted-foreground italic">“{{ p.notes }}”</p>
                        <p v-if="p.status === 'confirmed'" class="text-xs text-emerald-700">Confirmed by {{ p.reviewer }} · {{ p.reviewed_at }}</p>
                        <p v-if="p.status === 'rejected'" class="text-xs text-red-700">
                            Not accepted by {{ p.reviewer }}: {{ p.rejection_reason }}
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="grid grid-cols-2 gap-2 md:flex md:flex-col md:items-stretch">
                    <Button as-child variant="outline" size="sm" class="col-span-2">
                        <a :href="p.proof_url" target="_blank" rel="noopener">
                            <component :is="p.is_pdf ? FileText : ImageIcon" /> View proof <ExternalLink class="size-3" />
                        </a>
                    </Button>
                    <template v-if="p.can_review">
                        <Button size="sm" :disabled="busyId === p.id" @click="confirm(p)">
                            <Spinner v-if="busyId === p.id" />
                            <Check v-else /> Confirm
                        </Button>
                        <Button size="sm" variant="outline" class="text-destructive" @click="openReject(p)"><X /> Reject</Button>
                    </template>
                    <p v-else-if="p.status === 'pending'" class="col-span-2 text-center text-xs text-muted-foreground md:max-w-40">
                        Confirmed by the member's regional admin
                    </p>
                </div>
            </div>

            <p v-if="!payments.data.length" class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground">
                {{ filters.status === 'pending' ? 'No payments waiting for confirmation. 🎉' : 'No payments found.' }}
            </p>
        </div>

        <!-- Pagination -->
        <div v-if="payments.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <span class="text-muted-foreground">{{ payments.from }}–{{ payments.to }} of {{ payments.total.toLocaleString() }}</span>
            <div class="flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in payments.links"
                    :key="i"
                    :href="link.url ?? undefined"
                    preserve-scroll
                    class="rounded-md border px-3 py-1"
                    :class="[link.active ? 'bg-primary text-primary-foreground' : '', !link.url ? 'opacity-40' : 'hover:bg-muted']"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- Reject dialog -->
        <Dialog :open="!!rejectTarget" @update:open="(v) => !v && (rejectTarget = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject proof of payment</DialogTitle>
                    <DialogDescription>
                        {{ rejectTarget?.member_name }} will see this reason and can upload a new proof.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <textarea
                        v-model="rejectForm.reason"
                        rows="3"
                        maxlength="255"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="e.g. The receipt is blurry, or the amount does not match the membership fee."
                        aria-label="Reason"
                    />
                    <InputError :message="rejectForm.errors.reason" />
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="rejectTarget = null">Cancel</Button>
                    <Button variant="destructive" :disabled="rejectForm.processing || !rejectForm.reason.trim()" @click="submitReject">
                        <Spinner v-if="rejectForm.processing" /> Reject
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
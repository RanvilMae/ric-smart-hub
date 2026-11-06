<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Check, Search, ShieldCheck, ShieldPlus, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type Status = 'pending' | 'approved' | 'rejected';

type Row = {
    id: number;
    member_code: string | null;
    full_name: string;
    status: Status;
    region: string | null;
    location: string;
    ric_organization: string | null;
    member_type: string | null;
    submitted: string | null;
    can_review: boolean;
    is_admin: boolean;
    can_promote: boolean;
};

const props = defineProps<{
    members: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
        last_page: number;
    };
    counts: { all: number; pending: number; approved: number; rejected: number };
    filters: { status: 'all' | Status; region: number | null; search: string };
    regions: { id: number; name: string }[] | null;
    scope: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: '/admin/members' }],
    },
});

const tabs: { value: 'all' | Status; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Waiting' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const statusClass: Record<Status, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    approved: 'bg-accent text-accent-foreground',
    rejected: 'bg-destructive/10 text-destructive',
};
const statusLabel: Record<Status, string> = { pending: 'Waiting', approved: 'Approved', rejected: 'Rejected' };

// ---- Filters
const search = ref(props.filters.search);
const region = ref<number>(props.filters.region ?? 0);

const reload = (extra: Record<string, unknown> = {}) =>
    router.get(
        '/admin/members',
        {
            status: props.filters.status === 'all' ? undefined : props.filters.status,
            search: search.value || undefined,
            region: region.value || undefined,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(search, useDebounceFn(() => reload(), 350));
watch(region, () => reload());

// ---- Approve / disapprove
const busyId = ref<number | null>(null);

const approve = (m: Row) => {
    busyId.value = m.id;
    router.post(`/admin/members/${m.id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => (busyId.value = null),
    });
};

// ---- Make regional admin (super admin)
const promote = (m: Row) => {
    if (confirm(`Make ${m.full_name} the regional admin of ${m.region}?`)) {
        busyId.value = m.id;
        router.post(`/admin/admins/promote/${m.id}`, {}, {
            preserveScroll: true,
            onFinish: () => (busyId.value = null),
        });
    }
};

const rejectTarget = ref<Row | null>(null);
const rejectForm = useForm({ reason: '' });

const openReject = (m: Row) => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectTarget.value = m;
};

const submitReject = () => {
    if (!rejectTarget.value) {
        return;
    }

    rejectForm.post(`/admin/members/${rejectTarget.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => (rejectTarget.value = null),
    });
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30 [&>option]:bg-background';
</script>

<template>
    <Head title="Members" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <Heading title="Members" :description="scope ?? undefined" />

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <nav class="flex w-fit flex-wrap gap-1 rounded-lg bg-muted p-1 text-sm">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 transition"
                    :class="filters.status === tab.value ? 'bg-background font-medium shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="reload({ status: tab.value === 'all' ? undefined : tab.value, page: undefined })"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 text-xs tabular-nums"
                        :class="tab.value === 'pending' && counts.pending ? 'bg-amber-200 text-amber-900' : 'bg-muted-foreground/10'"
                    >
                        {{ counts[tab.value].toLocaleString() }}
                    </span>
                </button>
            </nav>

            <div class="flex flex-1 flex-col gap-3 sm:flex-row lg:justify-end">
                <select v-if="regions" v-model="region" :class="selectClass" aria-label="Filter by region">
                    <option :value="0">All regions</option>
                    <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
                <div class="relative w-full lg:max-w-xs">
                    <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search name, member ID or RIC…" class="pl-9" aria-label="Search members" />
                </div>
            </div>
        </div>

        <!-- Phones: one card per member -->
        <div class="grid gap-3 md:hidden">
            <div
                v-for="m in members.data"
                :key="`card-${m.id}`"
                class="rounded-xl border bg-card p-4"
                :class="{ 'border-amber-300 bg-amber-50/60': m.status === 'pending' }"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="`/admin/members/${m.id}`" class="font-medium text-primary hover:underline">{{ m.full_name }}</Link>
                        <p class="font-mono text-xs text-muted-foreground">{{ m.member_code }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[m.status]">
                        {{ statusLabel[m.status] }}
                    </span>
                </div>
                <p class="mt-2 text-sm">
                    {{ m.location }}<span v-if="regions" class="text-muted-foreground"> · {{ m.region }}</span>
                </p>
                <p v-if="m.ric_organization" class="text-sm text-muted-foreground">{{ m.ric_organization }}</p>
                <span v-if="m.is_admin" class="mt-2 inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">
                    <ShieldCheck class="size-3" /> Regional admin
                </span>
                <div v-if="m.status === 'pending' && m.can_review" class="mt-3 grid grid-cols-2 gap-2">
                    <Button :disabled="busyId === m.id" @click="approve(m)">
                        <Spinner v-if="busyId === m.id" />
                        <Check v-else /> Approve
                    </Button>
                    <Button variant="outline" class="text-destructive" @click="openReject(m)"><X /> Disapprove</Button>
                </div>
                <Button v-else-if="m.can_promote" variant="outline" class="mt-3 w-full" :disabled="busyId === m.id" @click="promote(m)">
                    <ShieldPlus /> Make regional admin
                </Button>
            </div>
            <p v-if="!members.data.length" class="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground">
                No members found.
            </p>
        </div>

        <div class="hidden overflow-x-auto rounded-xl border bg-card md:block">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Location</th>
                        <th class="px-4 py-3 font-medium">RIC organization</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="m in members.data" :key="m.id" class="hover:bg-muted/40" :class="{ 'bg-amber-50/50 dark:bg-amber-500/5': m.status === 'pending' }">
                        <td class="px-4 py-3">
                            <Link :href="`/admin/members/${m.id}`" class="font-medium text-primary hover:underline">
                                {{ m.full_name }}
                            </Link>
                            <span class="block font-mono text-xs text-muted-foreground">{{ m.member_code }}</span>
                        </td>
                        <td class="px-4 py-3">
                            {{ m.location }}
                            <span v-if="regions" class="text-muted-foreground"> · {{ m.region }}</span>
                        </td>
                        <td class="px-4 py-3">{{ m.ric_organization }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[m.status]">
                                {{ statusLabel[m.status] }}
                            </span>
                            <span v-if="m.is_admin" class="ml-1 inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">
                                <ShieldCheck class="size-3" /> Regional admin
                            </span>
                            <span class="block text-xs text-muted-foreground">{{ m.submitted }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="m.status === 'pending' && m.can_review" class="flex justify-end gap-2">
                                <Button size="sm" :disabled="busyId === m.id" @click="approve(m)">
                                    <Spinner v-if="busyId === m.id" />
                                    <Check v-else /> Approve
                                </Button>
                                <Button size="sm" variant="outline" class="text-destructive" @click="openReject(m)">
                                    <X /> Disapprove
                                </Button>
                            </div>
                            <div v-else-if="m.can_promote" class="flex items-center justify-end gap-3">
                                <Link :href="`/admin/members/${m.id}`" class="text-xs text-muted-foreground hover:text-foreground hover:underline">View</Link>
                                <Button size="sm" variant="outline" :disabled="busyId === m.id" @click="promote(m)">
                                    <Spinner v-if="busyId === m.id" />
                                    <ShieldPlus v-else /> Make regional admin
                                </Button>
                            </div>
                            <div v-else class="text-right">
                                <Link :href="`/admin/members/${m.id}`" class="text-xs text-muted-foreground hover:text-foreground hover:underline">
                                    View
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!members.data.length">
                        <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No members found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <span class="text-muted-foreground">
                <template v-if="members.total">{{ members.from }}–{{ members.to }} of </template>
                {{ members.total.toLocaleString() }} members
            </span>
            <div v-if="members.last_page > 1" class="flex flex-wrap gap-1">
                <template v-for="(link, i) in members.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded-md border px-3 py-1 hover:bg-accent"
                        :class="{ 'bg-primary text-primary-foreground hover:bg-primary': link.active }"
                        v-html="link.label"
                    />
                    <span v-else class="rounded-md border px-3 py-1 opacity-40" v-html="link.label" />
                </template>
            </div>
        </div>

        <!-- Disapprove dialog -->
        <Dialog :open="!!rejectTarget" @update:open="(open) => { if (!open) rejectTarget = null; }">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disapprove registration</DialogTitle>
                    <DialogDescription>
                        {{ rejectTarget?.full_name }} will see this reason when they log in.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <textarea
                        v-model="rejectForm.reason"
                        rows="4"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="e.g. Not a registered member of the stated RIC organization."
                        aria-label="Reason"
                    />
                    <InputError :message="rejectForm.errors.reason" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rejectTarget = null">Cancel</Button>
                    <Button variant="destructive" :disabled="rejectForm.processing" @click="submitReject">
                        <Spinner v-if="rejectForm.processing" />
                        Disapprove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

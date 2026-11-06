<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Camera, CircleCheck, Clock, ExternalLink, FileText, IdCard, Lock, Printer, Receipt, TriangleAlert, Upload, User } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Member = Record<string, unknown> & {
    member_code: string | null;
    full_name: string;
    first_name: string;
    last_name: string;
    middle_name: string | null;
    suffix: string | null;
    region: string | null;
    region_name: string | null;
    province: string | null;
    city: string | null;
    barangay: string | null;
    ric_organization: string | null;
    member_type: string | null;
    contact_number: string | null;
    photo_url: string | null;
    member_since: string | null;
    issued_on: string;
};

type Payment = {
    id: number;
    method: string;
    reference_no: string | null;
    amount: string;
    paid_on: string | null;
    paid_on_value: string | null;
    notes: string | null;
    status: 'pending' | 'confirmed' | 'rejected';
    proof_url: string;
    proof_name: string | null;
    is_pdf: boolean;
    submitted_at: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
};

const props = defineProps<{
    member: Member;
    payment: Payment | null;
    paymentConfirmed: boolean;
    paymentMethods: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My account', href: '/my-account' }],
    },
});

type Tab = 'details' | 'photo' | 'payment' | 'id';
const tab = ref<Tab>('details');
const tabs = computed<{ value: Tab; label: string; icon: typeof User }[]>(() => [
    { value: 'details', label: 'My details', icon: User },
    { value: 'photo', label: 'Photo', icon: Camera },
    { value: 'payment', label: 'Payment', icon: Receipt },
    { value: 'id', label: 'Digital ID', icon: props.paymentConfirmed ? IdCard : Lock },
]);

// ---- Details
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

// ---- Photo
const photoForm = useForm<{ photo: File | null }>({ photo: null });
const preview = ref<string | null>(null);

const pickPhoto = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    photoForm.photo = file;
    preview.value = file ? URL.createObjectURL(file) : null;
};

const uploadPhoto = () =>
    photoForm.post('/my-account/photo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoForm.reset();
            preview.value = null;
        },
    });

const initials = computed(() => `${props.member.first_name?.[0] ?? ''}${props.member.last_name?.[0] ?? ''}`.toUpperCase());

// ---- Digital ID
const cardName = computed(() => {
    const m = props.member;
    const middle = m.middle_name ? ` ${m.middle_name.charAt(0)}.` : '';
    const suffix = m.suffix ? ` ${m.suffix}` : '';

    return `${m.first_name}${middle} ${m.last_name}${suffix}`.toUpperCase();
});

const address = computed(() =>
    [props.member.barangay ? `Brgy. ${props.member.barangay}` : null, props.member.city, props.member.province]
        .filter(Boolean)
        .join(', '),
);

const printId = () => window.print();

// ---- Payment (the digital ID unlocks once the regional admin confirms it)
const today = new Date().toISOString().slice(0, 10);
const paymentForm = useForm<{
    method: string;
    reference_no: string;
    amount: string;
    paid_on: string;
    notes: string;
    proof: File | null;
}>({
    method: props.payment?.status === 'pending' ? props.payment.method : '',
    reference_no: props.payment?.status === 'pending' ? (props.payment.reference_no ?? '') : '',
    amount: props.payment?.status === 'pending' ? props.payment.amount : '',
    paid_on: props.payment?.status === 'pending' ? (props.payment.paid_on_value ?? '') : '',
    notes: props.payment?.status === 'pending' ? (props.payment.notes ?? '') : '',
    proof: null,
});
const isCash = computed(() => paymentForm.method.startsWith('Cash'));
const fileInput = ref<HTMLInputElement | null>(null);

const pickProof = (event: Event) => {
    paymentForm.proof = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const uploadPayment = () =>
    paymentForm.post('/my-account/payment', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            paymentForm.proof = null;
            if (fileInput.value) fileInput.value.value = '';
        },
    });

const peso = (v: string | number) => `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>option]:bg-background';
</script>

<template>
    <Head title="My account" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center gap-4 print:hidden">
            <div class="flex size-16 items-center justify-center overflow-hidden rounded-full border-2 border-primary/30 bg-accent">
                <img v-if="member.photo_url" :src="member.photo_url" alt="" class="size-full object-cover" />
                <span v-else class="text-lg font-semibold text-accent-foreground">{{ initials }}</span>
            </div>
            <div>
                <h1 class="text-2xl font-semibold">{{ member.full_name }}</h1>
                <p class="text-sm text-muted-foreground">
                    <span class="font-mono font-medium text-foreground">{{ member.member_code }}</span>
                    · {{ member.region }}
                </p>
            </div>
        </div>

        <!-- Tabs -->
        <nav class="flex w-fit flex-wrap gap-1 rounded-lg bg-muted p-1 text-sm print:hidden">
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

        <!-- 1. Details -->
        <div v-if="tab === 'details'" class="grid gap-6 lg:grid-cols-2 print:hidden">
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
            <p class="text-sm text-muted-foreground lg:col-span-2">
                Need to correct something? Please contact your regional RIC administrator.
            </p>
        </div>

        <!-- 2. Photo -->
        <Card v-if="tab === 'photo'" class="max-w-xl print:hidden">
            <CardHeader>
                <CardTitle class="text-base">Profile photo</CardTitle>
                <CardDescription>
                    Used on your digital ID. Use a clear, front-facing photo (JPG, PNG or WEBP, up to 2 MB).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="flex flex-col gap-5 sm:flex-row sm:items-start" @submit.prevent="uploadPhoto">
                    <div class="flex size-36 shrink-0 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed bg-muted">
                        <img v-if="preview || member.photo_url" :src="preview ?? member.photo_url ?? ''" alt="Photo preview" class="size-full object-cover" />
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
                            {{ member.photo_url ? 'Replace photo' : 'Upload photo' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- 3. Payment -->
        <div v-if="tab === 'payment'" class="grid max-w-3xl gap-6 print:hidden">
            <!-- Status -->
            <div
                v-if="paymentConfirmed"
                class="flex items-start gap-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900"
            >
                <CircleCheck class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-medium">Payment confirmed{{ payment?.reviewed_at ? ` on ${payment.reviewed_at}` : '' }}.</p>
                    <p>Your digital ID is ready.</p>
                    <Button size="sm" class="mt-2" @click="tab = 'id'"><IdCard /> View my digital ID</Button>
                </div>
            </div>
            <div
                v-else-if="payment?.status === 'pending'"
                class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
            >
                <Clock class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-medium">Waiting for confirmation</p>
                    <p>Your regional admin will check your proof of payment. You can still replace it below if you made a mistake.</p>
                </div>
            </div>
            <div
                v-else-if="payment?.status === 'rejected'"
                class="flex items-start gap-3 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900"
            >
                <TriangleAlert class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-medium">Your last proof of payment was not accepted</p>
                    <p v-if="payment.rejection_reason">Reason: {{ payment.rejection_reason }}</p>
                    <p>Please upload a new one below.</p>
                </div>
            </div>
            <div v-else class="flex items-start gap-3 rounded-xl border bg-accent/50 p-4 text-sm">
                <Receipt class="mt-0.5 size-5 shrink-0 text-primary" />
                <div>
                    <p class="font-medium">Pay your membership fee to get your digital ID</p>
                    <p class="text-muted-foreground">
                        After paying, upload a photo or PDF of your receipt here. Your regional admin will confirm it, and your
                        digital ID will unlock.
                    </p>
                </div>
            </div>

            <!-- Latest upload -->
            <Card v-if="payment">
                <CardHeader>
                    <CardTitle class="text-base">{{ paymentConfirmed ? 'Confirmed payment' : 'Last upload' }}</CardTitle>
                    <CardDescription>Submitted {{ payment.submitted_at }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-2.5 text-sm">
                        <div class="grid gap-0.5 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                            <dt class="text-muted-foreground">Mode of payment</dt>
                            <dd>{{ payment.method }}</dd>
                        </div>
                        <div class="grid gap-0.5 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                            <dt class="text-muted-foreground">Reference no.</dt>
                            <dd>{{ payment.reference_no || '—' }}</dd>
                        </div>
                        <div class="grid gap-0.5 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                            <dt class="text-muted-foreground">Amount</dt>
                            <dd>{{ peso(payment.amount) }}</dd>
                        </div>
                        <div class="grid gap-0.5 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                            <dt class="text-muted-foreground">Date of payment</dt>
                            <dd>{{ payment.paid_on }}</dd>
                        </div>
                        <div v-if="payment.notes" class="grid gap-0.5 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                            <dt class="text-muted-foreground">Notes</dt>
                            <dd>{{ payment.notes }}</dd>
                        </div>
                        <div class="grid gap-0.5 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                            <dt class="text-muted-foreground">Proof of payment</dt>
                            <dd>
                                <a :href="payment.proof_url" target="_blank" class="inline-flex items-center gap-1 font-medium text-primary hover:underline">
                                    <FileText class="size-4" /> {{ payment.proof_name || 'View file' }}
                                    <ExternalLink class="size-3" />
                                </a>
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <!-- Upload form -->
            <Card v-if="!paymentConfirmed">
                <CardHeader>
                    <CardTitle class="text-base">{{ payment?.status === 'pending' ? 'Replace proof of payment' : 'Upload proof of payment' }}</CardTitle>
                    <CardDescription>Fields marked * are required.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="uploadPayment">
                        <div class="grid gap-1.5">
                            <Label for="pay-method">Mode of payment *</Label>
                            <select id="pay-method" v-model="paymentForm.method" :class="selectClass">
                                <option value="" disabled>Choose…</option>
                                <option v-for="m in paymentMethods" :key="m">{{ m }}</option>
                            </select>
                            <InputError :message="paymentForm.errors.method" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pay-ref">Reference / transaction no. {{ isCash ? '(optional)' : '*' }}</Label>
                            <Input id="pay-ref" v-model="paymentForm.reference_no" maxlength="100" :placeholder="isCash ? 'O.R. number, if any' : 'e.g. 1023 456 789012'" />
                            <InputError :message="paymentForm.errors.reference_no" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pay-amount">Amount paid (₱) *</Label>
                            <Input id="pay-amount" v-model="paymentForm.amount" type="number" min="1" step="0.01" inputmode="decimal" />
                            <InputError :message="paymentForm.errors.amount" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pay-date">Date of payment *</Label>
                            <Input id="pay-date" v-model="paymentForm.paid_on" type="date" :max="today" />
                            <InputError :message="paymentForm.errors.paid_on" />
                        </div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label for="pay-notes">Notes (optional)</Label>
                            <Input id="pay-notes" v-model="paymentForm.notes" maxlength="255" placeholder="e.g. Paid by our RIC treasurer for 5 members" />
                            <InputError :message="paymentForm.errors.notes" />
                        </div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label for="pay-proof">Receipt or screenshot * <span class="font-normal text-muted-foreground">(JPG, PNG, WEBP or PDF, up to 5 MB)</span></Label>
                            <input
                                id="pay-proof"
                                ref="fileInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-2 file:text-sm file:font-medium file:text-accent-foreground hover:file:bg-accent/80"
                                @change="pickProof"
                            />
                            <InputError :message="paymentForm.errors.proof" />
                        </div>
                        <div class="sm:col-span-2">
                            <Button type="submit" :disabled="paymentForm.processing || !paymentForm.proof">
                                <Spinner v-if="paymentForm.processing" />
                                <Upload v-else />
                                {{ payment?.status === 'pending' ? 'Replace upload' : 'Submit proof of payment' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- 4. Digital ID: locked until the payment is confirmed -->
        <Card v-if="tab === 'id' && !paymentConfirmed" class="max-w-xl print:hidden">
            <CardContent class="flex flex-col items-center gap-3 py-10 text-center">
                <div class="flex size-14 items-center justify-center rounded-full bg-muted">
                    <Lock class="size-6 text-muted-foreground" />
                </div>
                <p class="text-lg font-semibold">Your digital ID is locked</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    <template v-if="payment?.status === 'pending'">
                        Your proof of payment is waiting for confirmation by your regional admin. Your ID unlocks once it is confirmed.
                    </template>
                    <template v-else>Upload your proof of membership payment. Your ID unlocks once your regional admin confirms it.</template>
                </p>
                <Button @click="tab = 'payment'">
                    <Receipt /> {{ payment?.status === 'pending' ? 'View my payment' : 'Upload proof of payment' }}
                </Button>
            </CardContent>
        </Card>

        <div v-if="tab === 'id' && paymentConfirmed" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-3 print:hidden">
                <Button @click="printId"><Printer /> Print / Save as PDF</Button>
                <p class="text-sm text-muted-foreground">
                    In the print window choose <strong>Save as PDF</strong> to download, and turn on <strong>Background graphics</strong>.
                </p>
            </div>
            <p v-if="!member.photo_url" class="text-sm text-amber-600 print:hidden">
                Tip: upload a photo first so it appears on your ID.
            </p>

            <div id="digital-id" class="flex flex-wrap gap-6">
                <!-- Front -->
                <div class="id-card relative flex flex-col overflow-hidden rounded-2xl border bg-white text-[#14301f] shadow-lg">
                    <div class="flex items-center gap-2.5 bg-[#047C2E] px-4 py-2.5 text-white">
                        <img src="/images/ric-logo.png" alt="" class="size-10 rounded-full bg-white p-0.5" />
                        <div class="leading-tight">
                            <p class="text-[13px] font-bold tracking-wide">RURAL IMPROVEMENT CLUBS</p>
                            <p class="text-[10px] tracking-[0.2em] text-yellow-300">OF THE PHILIPPINES</p>
                        </div>
                    </div>
                    <div class="h-1 bg-yellow-300" />

                    <div class="flex flex-1 gap-4 px-4 py-3">
                        <div class="flex h-[1.25in] w-[1in] shrink-0 items-center justify-center overflow-hidden rounded-md border-2 border-[#047C2E] bg-[#eef6f0]">
                            <img v-if="member.photo_url" :src="member.photo_url" alt="" class="size-full object-cover" />
                            <span v-else class="text-2xl font-semibold text-[#047C2E]">{{ initials }}</span>
                        </div>
                        <div class="flex min-w-0 flex-col justify-between">
                            <div>
                                <p class="text-[9px] tracking-wider text-gray-500 uppercase">Name</p>
                                <p class="text-[14px] leading-tight font-bold">{{ cardName }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] tracking-wider text-gray-500 uppercase">Member ID</p>
                                <p class="font-mono text-[13px] font-bold text-[#047C2E]">{{ member.member_code }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] tracking-wider text-gray-500 uppercase">RIC organization</p>
                                <p class="truncate text-[11px] font-medium">{{ member.ric_organization }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-[#eef6f0] px-4 py-1.5 text-[10px] font-semibold text-[#047C2E]">
                        <span>{{ member.region }}</span>
                        <span>{{ member.member_type }}</span>
                    </div>
                </div>

                <!-- Back -->
                <div class="id-card relative flex flex-col overflow-hidden rounded-2xl border bg-white text-[#14301f] shadow-lg">
                    <div class="flex-1 space-y-2 px-4 pt-3 text-[11px]">
                        <div>
                            <p class="text-[9px] tracking-wider text-gray-500 uppercase">Address</p>
                            <p class="font-medium">{{ address || '—' }}</p>
                        </div>
                        <div class="flex gap-6">
                            <div>
                                <p class="text-[9px] tracking-wider text-gray-500 uppercase">Contact no.</p>
                                <p class="font-medium">{{ member.contact_number || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] tracking-wider text-gray-500 uppercase">Member since</p>
                                <p class="font-medium">{{ member.member_since }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] tracking-wider text-gray-500 uppercase">Issued</p>
                                <p class="font-medium">{{ member.issued_on }}</p>
                            </div>
                        </div>
                        <div class="flex items-end justify-between gap-4 pt-3">
                            <div class="w-1/2 border-t border-gray-400 pt-0.5 text-center text-[9px] text-gray-500">
                                Member's signature
                            </div>
                            <p class="text-right text-[8.5px] leading-snug text-gray-500">
                                If found, please return to the<br />Agricultural Training Institute,<br />Diliman, Quezon City
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-center gap-2 bg-[#047C2E] py-1.5 text-[10px] font-semibold tracking-[0.25em] text-yellow-300">
                        HOME · FAMILY · COMMUNITY
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Standard ID card size: 3.375in x 2.125in (CR80) */
.id-card {
    width: 3.375in;
    height: 2.125in;
}

@media print {
    @page {
        margin: 0.5in;
    }

    body * {
        visibility: hidden;
    }

    #digital-id,
    #digital-id * {
        visibility: visible;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    #digital-id {
        position: absolute;
        top: 0;
        left: 0;
        gap: 0.3in;
    }

    #digital-id .id-card {
        box-shadow: none;
        border: 1px solid #ccc;
    }
}
</style>
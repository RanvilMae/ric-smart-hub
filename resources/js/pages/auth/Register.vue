<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Option = { id: number; name: string };

const props = defineProps<{
    regions: { id: number; name: string; label: string }[];
    options: Record<string, string[]>;
    // Set when a logged-in account (e.g. a regional admin) registers its membership
    existingAccount?: boolean;
    account?: { name: string; email: string; role: string };
    lockedRegionId?: number | null;
}>();

defineOptions({
    layout: {
        title: 'RIC Smart Hub member registration',
        description:
            'Rural Improvement Clubs of the Philippines national database. Your registration will be reviewed before it is approved.',
    },
});

const form = useForm({
    email: '',
    password: '',
    password_confirmation: '',
    last_name: '',
    first_name: '',
    middle_name: '',
    suffix: '',
    sex: '',
    civil_status: '',
    birth_date: '',
    education_attainment: '',
    degree: '',
    region_id: (props.lockedRegionId ?? null) as number | null,
    province_id: null as number | null,
    city_id: null as number | null,
    barangay_id: null as number | null,
    district: '',
    municipality_class: '',
    zip_code: '',
    member_type: '',
    occupation: '',
    contact_number: '',
    alternate_email: '',
    organization_address: '',
    ric_organization: '',
    specialization: '',
    hvcdp_commodity: '',
    ati_services: [] as string[],
    lsa_level: '',
    lsa_type: '',
    ati_scholarship: '',
    ati_trainings: [] as string[],
});

const otherTraining = ref('');

// ---- Cascading address dropdowns ----
const provinces = ref<Option[]>([]);
const cities = ref<Option[]>([]);
const barangays = ref<Option[]>([]);
const loading = ref('');

async function fetchList(url: string, what: string): Promise<Option[]> {
    loading.value = what;

    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        return response.ok ? await response.json() : [];
    } finally {
        loading.value = '';
    }
}

watch(
    () => form.region_id,
    async (id) => {
        form.province_id = form.city_id = form.barangay_id = null;
        provinces.value = [];
        cities.value = [];
        barangays.value = [];

        if (id) {
            provinces.value = await fetchList(`/locations/regions/${id}/provinces`, 'provinces');
        }
    },
    { immediate: true }, // loads provinces right away when the region is locked
);

watch(
    () => form.province_id,
    async (id) => {
        form.city_id = form.barangay_id = null;
        cities.value = [];
        barangays.value = [];

        if (id) {
            cities.value = await fetchList(`/locations/provinces/${id}/cities`, 'cities');
        }
    },
);

watch(
    () => form.city_id,
    async (id) => {
        form.barangay_id = null;
        barangays.value = [];

        if (id) {
            barangays.value = await fetchList(`/locations/cities/${id}/barangays`, 'barangays');
        }
    },
);

// ---- RIC organizations of the chosen region / city (listed by the regional admins)
type Org = { id: number; name: string; address: string };
const OTHER = '__other__';
const organizations = ref<Org[]>([]);
const orgChoice = ref('');
const loadingOrgs = ref(false);
let orgRequest = 0;

watch(
    [() => form.region_id, () => form.city_id],
    async ([regionId, cityId]) => {
        const request = ++orgRequest;
        organizations.value = [];

        if (!regionId) return;

        loadingOrgs.value = true;
        const params = new URLSearchParams({ region: String(regionId) });
        if (cityId) params.set('city', String(cityId));

        try {
            const response = await fetch(`/locations/organizations?${params}`, { headers: { Accept: 'application/json' } });
            const list: Org[] = response.ok ? await response.json() : [];
            if (request !== orgRequest) return; // a newer search started

            organizations.value = list;
        } finally {
            if (request === orgRequest) loadingOrgs.value = false;
        }

        // Keep the chosen organization only if it is in the new list
        const listed = organizations.value.some((o) => o.name === form.ric_organization);
        if (listed) {
            orgChoice.value = form.ric_organization;
        } else if (organizations.value.length && orgChoice.value !== OTHER) {
            orgChoice.value = '';
            form.ric_organization = '';
        }
    },
    { immediate: true },
);

// Picking an organization also fills in its address
watch(orgChoice, (choice) => {
    if (choice === OTHER) {
        form.ric_organization = '';
        form.organization_address = '';
        return;
    }

    const org = organizations.value.find((o) => o.name === choice);
    if (org) {
        form.ric_organization = org.name;
        form.organization_address = org.address;
    }
});

const has = (service: string) => form.ati_services.includes(service);

const submit = () => {
    form.transform((data) => {
        const payload: Record<string, unknown> = {
            ...data,
            ati_trainings: has('Training')
                ? [...data.ati_trainings, otherTraining.value.trim()].filter(Boolean)
                : [],
        };

        if (props.existingAccount) {
            // The login email belongs to the current account and is never sent from here
            delete payload.email;
        }

        return payload;
    }).submit(props.existingAccount ? { url: '/my-account/register', method: 'post' } : store(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        // scroll to the first field with an error
        onError: () =>
            setTimeout(
                () =>
                    document
                        .querySelector('[data-error="true"]')
                        ?.scrollIntoView({ behavior: 'smooth', block: 'center' }),
                50,
            ),
    });
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 md:text-sm dark:bg-input/30 [&>option]:bg-background';
const checkClass = 'size-4 accent-primary';
</script>

<template>
    <Head title="Register" />

    <form class="flex flex-col gap-6" novalidate @submit.prevent="submit">
        <!-- 1. Personal information -->
        <Card>
            <CardHeader>
                <CardTitle>Personal information</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2" :data-error="!!form.errors.last_name">
                    <Label for="last_name">Last name *</Label>
                    <Input id="last_name" v-model="form.last_name" autocomplete="family-name" />
                    <InputError :message="form.errors.last_name" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.first_name">
                    <Label for="first_name">First name *</Label>
                    <Input id="first_name" v-model="form.first_name" autocomplete="given-name" />
                    <InputError :message="form.errors.first_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="middle_name">Middle name / initial</Label>
                    <Input id="middle_name" v-model="form.middle_name" />
                    <InputError :message="form.errors.middle_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="suffix">Suffix (Jr., Sr., I, III…)</Label>
                    <select id="suffix" v-model="form.suffix" :class="selectClass">
                        <option value="">None</option>
                        <option v-for="o in options.suffixes" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.suffix" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.sex">
                    <Label for="sex">Sex *</Label>
                    <select id="sex" v-model="form.sex" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.sexes" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.sex" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.civil_status">
                    <Label for="civil_status">Civil status *</Label>
                    <select id="civil_status" v-model="form.civil_status" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.civil_statuses" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.civil_status" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.birth_date">
                    <Label for="birth_date">Date of birth *</Label>
                    <Input id="birth_date" v-model="form.birth_date" type="date" />
                    <InputError :message="form.errors.birth_date" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.education_attainment">
                    <Label for="education_attainment">Highest educational attainment *</Label>
                    <select id="education_attainment" v-model="form.education_attainment" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.education_levels" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.education_attainment" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="degree">Degree (write in full; leave blank if not applicable)</Label>
                    <Input id="degree" v-model="form.degree" placeholder="e.g. Bachelor of Science in Agriculture" />
                    <InputError :message="form.errors.degree" />
                </div>
            </CardContent>
        </Card>

        <!-- 2. Address -->
        <Card>
            <CardHeader>
                <CardTitle>Address</CardTitle>
                <CardDescription>
                    Your region decides which regional administrator reviews your registration.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2" :data-error="!!form.errors.region_id">
                    <Label for="region_id">Region *</Label>
                    <select id="region_id" v-model="form.region_id" :class="selectClass" :disabled="!!lockedRegionId">
                        <option :value="null" disabled>Select region…</option>
                        <option v-for="r in regions" :key="r.id" :value="r.id">
                            {{ r.name === r.label ? r.name : `${r.name} – ${r.label}` }}
                        </option>
                    </select>
                    <p v-if="lockedRegionId" class="text-xs text-muted-foreground">
                        Regional admins register in the region they manage.
                    </p>
                    <InputError :message="form.errors.region_id" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.province_id">
                    <Label for="province_id">Province *</Label>
                    <select id="province_id" v-model="form.province_id" :class="selectClass" :disabled="!provinces.length">
                        <option :value="null" disabled>
                            {{ loading === 'provinces' ? 'Loading…' : 'Select province…' }}
                        </option>
                        <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <InputError :message="form.errors.province_id" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.city_id">
                    <Label for="city_id">City / Municipality *</Label>
                    <select id="city_id" v-model="form.city_id" :class="selectClass" :disabled="!cities.length">
                        <option :value="null" disabled>
                            {{ loading === 'cities' ? 'Loading…' : 'Select city / municipality…' }}
                        </option>
                        <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="form.errors.city_id" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.barangay_id">
                    <Label for="barangay_id">Barangay *</Label>
                    <select id="barangay_id" v-model="form.barangay_id" :class="selectClass" :disabled="!barangays.length">
                        <option :value="null" disabled>
                            {{ loading === 'barangays' ? 'Loading…' : 'Select barangay…' }}
                        </option>
                        <option v-for="b in barangays" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <InputError :message="form.errors.barangay_id" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.district">
                    <Label for="district">District *</Label>
                    <select id="district" v-model="form.district" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.districts" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.district" />
                </div>
                <div class="grid gap-2">
                    <Label for="municipality_class">City / Municipality class</Label>
                    <select id="municipality_class" v-model="form.municipality_class" :class="selectClass">
                        <option value="">Not sure</option>
                        <option v-for="o in options.municipality_classes" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.municipality_class" />
                </div>
                <div class="grid gap-2">
                    <Label for="zip_code">ZIP code</Label>
                    <Input id="zip_code" v-model="form.zip_code" inputmode="numeric" maxlength="4" />
                    <InputError :message="form.errors.zip_code" />
                </div>
            </CardContent>
        </Card>

        <!-- 3. Membership -->
        <Card>
            <CardHeader>
                <CardTitle>RIC membership</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2" :data-error="!!form.errors.member_type">
                    <Label for="member_type">Type of member *</Label>
                    <select id="member_type" v-model="form.member_type" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.member_types" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.member_type" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.occupation">
                    <Label for="occupation">Occupation / position / designation *</Label>
                    <Input id="occupation" v-model="form.occupation" />
                    <InputError :message="form.errors.occupation" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.contact_number">
                    <Label for="contact_number">Contact number *</Label>
                    <Input id="contact_number" v-model="form.contact_number" type="tel" placeholder="09171234567" />
                    <InputError :message="form.errors.contact_number" />
                </div>
                <div class="grid gap-2">
                    <Label for="alternate_email">Email address 2 (optional)</Label>
                    <Input id="alternate_email" v-model="form.alternate_email" type="email" />
                    <InputError :message="form.errors.alternate_email" />
                </div>
                                <div class="grid gap-2" :data-error="!!form.errors.ric_organization">
                    <Label for="ric_organization">Name of RIC organization *</Label>
                    <select v-if="organizations.length" id="ric_organization" v-model="orgChoice" :class="selectClass">
                        <option value="" disabled>
                            {{ form.city_id ? 'Choose your RIC organization' : 'Choose your RIC organization (pick your city / municipality to narrow the list)' }}
                        </option>
                        <option v-for="o in organizations" :key="o.id" :value="o.name">{{ o.name }}</option>
                        <option :value="OTHER">My organization is not listed</option>
                    </select>
                    <Input
                        v-if="!organizations.length || orgChoice === OTHER"
                        :id="organizations.length ? 'ric_organization_other' : 'ric_organization'"
                        v-model="form.ric_organization"
                        :placeholder="organizations.length ? 'Type the name of your organization' : ''"
                    />
                    <p v-if="loadingOrgs" class="text-xs text-muted-foreground">Loading organizations…</p>
                    <p v-else-if="!organizations.length" class="text-xs text-muted-foreground">
                        {{
                            form.region_id
                                ? 'No organizations are listed for this area yet. Type the name of your organization.'
                                : 'Choose your region and city / municipality first to see the organizations.'
                        }}
                    </p>
                    <InputError :message="form.errors.ric_organization" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.organization_address">
                    <Label for="organization_address">Organization address *</Label>
                    <Input id="organization_address" v-model="form.organization_address" />
                    <InputError :message="form.errors.organization_address" />
                </div>
            </CardContent>
        </Card>

        <!-- 4. Specialization -->
        <Card>
            <CardHeader>
                <CardTitle>Specialization</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2" :data-error="!!form.errors.specialization">
                    <Label for="specialization">As an RIC member, what is your specialization? *</Label>
                    <select id="specialization" v-model="form.specialization" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.specializations" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.specialization" />
                </div>
                <div v-if="form.specialization === 'HVCDP'" class="grid gap-2" :data-error="!!form.errors.hvcdp_commodity">
                    <Label for="hvcdp_commodity">Which HVCDP commodity do you specialize in? *</Label>
                    <select id="hvcdp_commodity" v-model="form.hvcdp_commodity" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.hvcdp_commodities" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.hvcdp_commodity" />
                </div>
            </CardContent>
        </Card>

        <!-- 5. ATI services -->
        <Card>
            <CardHeader>
                <CardTitle>ATI services</CardTitle>
                <CardDescription>
                    Which services from the Agricultural Training Institute have you availed? Leave all unticked if none.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-5">
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label v-for="o in options.ati_services" :key="o" class="flex items-center gap-2 text-sm">
                        <input v-model="form.ati_services" type="checkbox" :value="o" :class="checkClass" />
                        {{ o }}
                    </label>
                </div>

                <div v-if="has('LSA')" class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2" :data-error="!!form.errors.lsa_level">
                        <Label for="lsa_level">If you are an LSA owner, which level are you in? *</Label>
                        <select id="lsa_level" v-model="form.lsa_level" :class="selectClass">
                            <option value="" disabled>Select…</option>
                            <option v-for="o in options.lsa_levels" :key="o">{{ o }}</option>
                        </select>
                        <InputError :message="form.errors.lsa_level" />
                    </div>
                    <div class="grid gap-2" :data-error="!!form.errors.lsa_type">
                        <Label for="lsa_type">Which type of LSA do you belong to? *</Label>
                        <select id="lsa_type" v-model="form.lsa_type" :class="selectClass">
                            <option value="" disabled>Select…</option>
                            <option v-for="o in options.lsa_types" :key="o">{{ o }}</option>
                        </select>
                        <InputError :message="form.errors.lsa_type" />
                    </div>
                </div>

                <div v-if="has('Scholarship')" class="grid gap-2" :data-error="!!form.errors.ati_scholarship">
                    <Label for="ati_scholarship">Which ATI scholarship have you availed? *</Label>
                    <select id="ati_scholarship" v-model="form.ati_scholarship" :class="selectClass">
                        <option value="" disabled>Select…</option>
                        <option v-for="o in options.ati_scholarships" :key="o">{{ o }}</option>
                    </select>
                    <InputError :message="form.errors.ati_scholarship" />
                </div>

                <fieldset v-if="has('Training')" class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Which ATI trainings have you availed?</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="o in options.ati_trainings" :key="o" class="flex items-center gap-2 text-sm">
                            <input v-model="form.ati_trainings" type="checkbox" :value="o" :class="checkClass" />
                            {{ o }}
                        </label>
                    </div>
                    <Input v-model="otherTraining" class="mt-1" placeholder="Other training (optional)" />
                    <InputError :message="form.errors.ati_trainings" />
                </fieldset>
            </CardContent>
        </Card>

        <!-- 6a. Login account of the CURRENT account: read-only, only the password can be changed -->
        <Card v-if="existingAccount">
            <CardHeader>
                <CardTitle>Login account</CardTitle>
                <CardDescription>Your membership will be linked to the account you are logged in with.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Name</Label>
                    <Input :model-value="account?.name" disabled readonly />
                </div>
                <div class="grid gap-2">
                    <Label>Email address (login)</Label>
                    <Input :model-value="account?.email" disabled readonly />
                </div>
                <p class="text-xs text-muted-foreground sm:col-span-2">
                    Account type: {{ account?.role }}. These details cannot be changed here. To update your password,
                    fill in the fields below (optional).
                </p>
                <div class="grid gap-2" :data-error="!!form.errors.password">
                    <Label for="new_password">New password (optional)</Label>
                    <PasswordInput id="new_password" v-model="form.password" autocomplete="new-password" />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="new_password_confirmation">Confirm new password</Label>
                    <PasswordInput id="new_password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" />
                    <InputError :message="form.errors.password_confirmation" />
                </div>
            </CardContent>
        </Card>

        <!-- 6b. Login account for a NEW member -->
        <Card v-else>
            <CardHeader>
                <CardTitle>Login account</CardTitle>
                <CardDescription>Use your own email address. You will use it to log in.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2" :data-error="!!form.errors.email">
                    <Label for="email">Email address *</Label>
                    <Input id="email" v-model="form.email" type="email" autocomplete="email" placeholder="email@example.com" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2" :data-error="!!form.errors.password">
                    <Label for="password">Password *</Label>
                    <PasswordInput id="password" v-model="form.password" autocomplete="new-password" />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password *</Label>
                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" />
                    <InputError :message="form.errors.password_confirmation" />
                </div>
            </CardContent>
        </Card>

        <Button type="submit" size="lg" class="w-full" :disabled="form.processing" data-test="register-user-button">
            <Spinner v-if="form.processing" />
            Submit registration
        </Button>

        <div v-if="!existingAccount" class="text-center text-sm text-muted-foreground">
            Already registered?
            <TextLink :href="login()" class="underline underline-offset-4">Log in</TextLink>
        </div>
    </form>
</template>

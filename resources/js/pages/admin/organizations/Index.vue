<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Building2, MapPin, Pencil, Plus, Search, Trash2, Users, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Organization = {
    id: number;
    name: string;
    address: string | null;
    full_address: string;
    province_id: number | null;
    city_id: number | null;
    barangay_id: number | null;
    description: string | null;
    specializations: string[];
    members: number;
    updated: string | null;
    updated_by: string | null;
};

const props = defineProps<{
    region: { id: number; name: string; label: string };
    organizations: Organization[];
    total: number;
    search: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Organizations', href: '/admin/organizations' }],
    },
});

const n = (v: number) => v.toLocaleString();
const regionTitle = computed(() =>
    props.region.name === props.region.label ? props.region.name : `${props.region.name} – ${props.region.label}`,
);

// ---- Search
const search = ref(props.search);
watch(
    search,
    useDebounceFn(
        () =>
            router.get(
                '/admin/organizations',
                { search: search.value || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            ),
        350,
    ),
);

// ---- Add / edit (one dialog for both)
const editing = ref<Organization | null>(null);
const dialogOpen = ref(false);
type Place = { id: number; name: string; type?: string };

const form = useForm<{
    name: string;
    province_id: number | null;
    city_id: number | null;
    barangay_id: number | null;
    address: string;
    description: string;
    specializations: string[];
}>({
    name: '',
    province_id: null,
    city_id: null,
    barangay_id: null,
    address: '',
    description: '',
    specializations: [''],
});

// ---- Address dropdowns: region is fixed (the admin's region) → province → city → barangay
const provinces = ref<Place[]>([]);
const cities = ref<Place[]>([]);
const barangays = ref<Place[]>([]);
const loadingPlaces = ref('');

const fetchPlaces = async (url: string, what: string): Promise<Place[]> => {
    loadingPlaces.value = what;
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        return response.ok ? await response.json() : [];
    } finally {
        loadingPlaces.value = '';
    }
};

const loadProvinces = async () => {
    if (!provinces.value.length) {
        provinces.value = await fetchPlaces(`/locations/regions/${props.region.id}/provinces`, 'provinces');
    }
};
const loadCities = async (provinceId: number | null) => {
    cities.value = provinceId ? await fetchPlaces(`/locations/provinces/${provinceId}/cities`, 'cities') : [];
};
const loadBarangays = async (cityId: number | null) => {
    barangays.value = cityId ? await fetchPlaces(`/locations/cities/${cityId}/barangays`, 'barangays') : [];
};

// Changing a level clears the levels below it
const onProvinceChange = () => {
    form.city_id = form.barangay_id = null;
    barangays.value = [];
    loadCities(form.province_id);
};
const onCityChange = () => {
    form.barangay_id = null;
    loadBarangays(form.city_id);
};

const openAdd = () => {
    editing.value = null;
    form.clearErrors();
    form.name = '';
    form.province_id = form.city_id = form.barangay_id = null;
    cities.value = [];
    barangays.value = [];
    loadProvinces();
    form.address = '';
    form.description = '';
    form.specializations = [''];
    dialogOpen.value = true;
};

const openEdit = (o: Organization) => {
    editing.value = o;
    form.clearErrors();
    form.name = o.name;
    form.province_id = o.province_id;
    form.city_id = o.city_id;
    form.barangay_id = o.barangay_id;
    loadProvinces();
    loadCities(o.province_id);
    loadBarangays(o.city_id);
    form.address = o.address ?? '';
    form.description = o.description ?? '';
    form.specializations = o.specializations.length ? [...o.specializations] : [''];
    dialogOpen.value = true;
};

// Specialization rows: add, remove
const specInputs = ref<HTMLElement | null>(null);
const addSpecialization = async () => {
    form.specializations.push('');
    await nextTick();
    const inputs = specInputs.value?.querySelectorAll('input');
    inputs?.[inputs.length - 1]?.focus();
};
const removeSpecialization = (i: number) => {
    form.specializations.splice(i, 1);
    if (!form.specializations.length) form.specializations.push('');
};

const specError = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('specializations'))
        .map(([, message]) => message)[0],
);

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (dialogOpen.value = false) };

    if (editing.value) {
        form.put(`/admin/organizations/${editing.value.id}`, options);
    } else {
        form.post('/admin/organizations', options);
    }
};

// ---- Delete
const deleting = ref<Organization | null>(null);
const deleteBusy = ref(false);
const confirmDelete = () => {
    if (!deleting.value) return;
    deleteBusy.value = true;
    router.delete(`/admin/organizations/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteBusy.value = false;
            deleting.value = null;
        },
    });
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 [&>option]:bg-background';
const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head title="Organizations" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <Heading title="RIC organizations" :description="`Organizations and clubs in ${regionTitle}`" />
            <Button class="w-full sm:w-auto" @click="openAdd"><Plus /> Add organization</Button>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-muted-foreground">
                {{ n(total) }} {{ total === 1 ? 'organization' : 'organizations' }}
                <span v-if="search && organizations.length !== total"> · {{ organizations.length }} found</span>
            </p>
            <div class="relative w-full sm:max-w-xs">
                <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search name, address, specialization…" class="pl-9" aria-label="Search organizations" />
            </div>
        </div>

        <!-- Cards -->
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="o in organizations" :key="o.id" class="flex flex-col gap-3 rounded-xl border bg-card p-5">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent">
                        <Building2 class="size-5 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="leading-snug font-semibold">{{ o.name }}</p>
                        <p class="flex items-start gap-1 text-sm text-muted-foreground">
                            <MapPin class="mt-0.5 size-3.5 shrink-0" /> {{ o.full_address || 'No address yet' }}
                        </p>
                    </div>
                </div>

                <p v-if="o.description" class="line-clamp-3 text-sm whitespace-pre-line">{{ o.description }}</p>

                <div v-if="o.specializations.length" class="flex flex-wrap gap-1.5">
                    <span
                        v-for="s in o.specializations"
                        :key="s"
                        class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"
                    >
                        {{ s }}
                    </span>
                </div>

                <div class="mt-auto flex items-center justify-between gap-3 border-t pt-3">
                    <span class="flex items-center gap-1.5 text-sm text-muted-foreground" title="Registered members who wrote this organization name">
                        <Users class="size-4" /> {{ n(o.members) }} {{ o.members === 1 ? 'member' : 'members' }}
                    </span>
                    <div class="flex gap-1">
                        <Button size="sm" variant="ghost" @click="openEdit(o)"><Pencil /> Edit</Button>
                        <Button size="sm" variant="ghost" class="text-destructive" aria-label="Delete" @click="deleting = o">
                            <Trash2 />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!organizations.length" class="flex flex-col items-center gap-3 rounded-xl border border-dashed bg-card p-10 text-center">
            <Building2 class="size-8 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">
                {{ search ? 'No organizations match your search.' : 'No organizations yet. Add the RIC organizations and clubs of your region.' }}
            </p>
            <Button v-if="!search" variant="outline" @click="openAdd"><Plus /> Add organization</Button>
        </div>

        <!-- Add / edit dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit organization' : 'Add organization' }}</DialogTitle>
                    <DialogDescription v-if="editing && editing.members">
                        Renaming also updates the {{ n(editing.members) }} member records that use this name.
                    </DialogDescription>
                    <DialogDescription v-else>Fields marked * are required.</DialogDescription>
                </DialogHeader>

                <form id="org-form" class="grid gap-4" @submit.prevent="save">
                    <div class="grid gap-1.5">
                        <Label for="org-name">Name of organization *</Label>
                        <Input id="org-name" v-model="form.name" maxlength="255" placeholder="e.g. RIC of Barangay San Isidro" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <fieldset class="grid gap-3 rounded-lg border p-3">
                        <legend class="px-1 text-sm font-medium">Address</legend>
                        <div class="grid gap-1.5">
                            <Label for="org-region">Region</Label>
                            <Input id="org-region" :model-value="regionTitle" disabled />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="org-province">Province *</Label>
                            <select id="org-province" v-model="form.province_id" :class="selectClass" @change="onProvinceChange">
                                <option :value="null" disabled>{{ loadingPlaces === 'provinces' ? 'Loading…' : 'Choose province' }}</option>
                                <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <InputError :message="form.errors.province_id" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="org-city">City / Municipality *</Label>
                            <select
                                id="org-city"
                                v-model="form.city_id"
                                :class="selectClass"
                                :disabled="!form.province_id"
                                @change="onCityChange"
                            >
                                <option :value="null" disabled>
                                    {{ loadingPlaces === 'cities' ? 'Loading…' : form.province_id ? 'Choose city / municipality' : 'Choose a province first' }}
                                </option>
                                <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError :message="form.errors.city_id" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="org-barangay">Barangay *</Label>
                            <select id="org-barangay" v-model="form.barangay_id" :class="selectClass" :disabled="!form.city_id">
                                <option :value="null" disabled>
                                    {{ loadingPlaces === 'barangays' ? 'Loading…' : form.city_id ? 'Choose barangay' : 'Choose a city / municipality first' }}
                                </option>
                                <option v-for="b in barangays" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                            <InputError :message="form.errors.barangay_id" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="org-address">Street / sitio / purok (optional)</Label>
                            <Input id="org-address" v-model="form.address" maxlength="255" placeholder="e.g. Purok 3" />
                            <InputError :message="form.errors.address" />
                        </div>
                    </fieldset>
                    <div class="grid gap-1.5">
                        <Label for="org-description">Description (optional)</Label>
                        <textarea
                            id="org-description"
                            v-model="form.description"
                            rows="3"
                            maxlength="2000"
                            :class="textareaClass"
                            placeholder="Activities, year founded, officers…"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Fields of specialization</Label>
                        <div ref="specInputs" class="grid gap-2">
                            <div v-for="(s, i) in form.specializations" :key="i" class="flex gap-2">
                                <Input
                                    v-model="form.specializations[i]"
                                    maxlength="100"
                                    :placeholder="i === 0 ? 'e.g. Food processing' : 'Another field'"
                                    :aria-label="`Field of specialization ${i + 1}`"
                                    @keydown.enter.prevent="addSpecialization"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="shrink-0 text-muted-foreground"
                                    :aria-label="`Remove field ${i + 1}`"
                                    @click="removeSpecialization(i)"
                                >
                                    <X />
                                </Button>
                            </div>
                        </div>
                        <Button type="button" variant="outline" size="sm" class="w-fit" @click="addSpecialization">
                            <Plus /> Add field
                        </Button>
                        <InputError :message="specError" />
                    </div>
                </form>

                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="dialogOpen = false">Cancel</Button>
                    <Button type="submit" form="org-form" :disabled="form.processing || !form.name.trim() || !form.barangay_id">
                        <Spinner v-if="form.processing" />
                        {{ editing ? 'Save changes' : 'Add organization' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete dialog -->
        <Dialog :open="!!deleting" @update:open="(v) => !v && (deleting = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete “{{ deleting?.name }}”?</DialogTitle>
                    <DialogDescription>
                        The organization is removed from your list. Member records are not changed<template v-if="deleting?.members">
                            — the {{ n(deleting.members) }} members keep the organization name they registered with</template
                        >.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="deleting = null">Cancel</Button>
                    <Button variant="destructive" :disabled="deleteBusy" @click="confirmDelete">
                        <Spinner v-if="deleteBusy" />
                        <Trash2 v-else /> Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
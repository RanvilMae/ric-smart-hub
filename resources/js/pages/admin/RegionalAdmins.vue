<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Search, ShieldPlus, UserMinus } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Admin = {
    id: number;
    name: string;
    email: string;
    member_code: string | null;
    ric_organization: string | null;
    region: string | null;
    is_active: boolean;
};

type Candidate = {
    id: number;
    full_name: string;
    member_code: string | null;
    email: string | null;
    region: string | null;
    city: string | null;
    ric_organization: string | null;
};

const props = defineProps<{
    admins: Admin[];
    candidates: Candidate[];
    filters: { region: number | null; search: string };
    regions: { id: number; name: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'National overview', href: '/admin' },
            { title: 'Regional admins', href: '/admin/admins' },
        ],
    },
});

// ---- Find members
const search = ref(props.filters.search);
const region = ref<number>(props.filters.region ?? 0);

const reload = () =>
    router.get(
        '/admin/admins',
        { search: search.value || undefined, region: region.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true, only: ['candidates', 'filters'] },
    );

watch(search, useDebounceFn(reload, 350));
watch(region, reload);

// ---- Actions
const promote = (c: Candidate) => {
    if (confirm(`Make ${c.full_name} the regional admin of ${c.region}?`)) {
        router.post(`/admin/admins/promote/${c.id}`, {}, { preserveScroll: true });
    }
};

const toggleActive = (a: Admin) =>
    router.patch(`/admin/admins/${a.id}`, { is_active: !a.is_active }, { preserveScroll: true });

const demote = (a: Admin) => {
    if (confirm(`Remove ${a.name} as regional admin? They will stay an approved member.`)) {
        router.delete(`/admin/admins/${a.id}`, { preserveScroll: true });
    }
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30 [&>option]:bg-background';
</script>

<template>
    <Head title="Regional admins" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Regional admins"
            description="Regional admins are approved members who manage registrations in their own region."
        />

        <!-- Current admins -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Current regional admins ({{ admins.length }})</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="hidden w-full text-sm md:table">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Region</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="a in admins" :key="a.id" :class="{ 'opacity-60': !a.is_active }">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ a.name }}</p>
                                <p class="text-muted-foreground">
                                    <span v-if="a.member_code" class="font-mono text-xs">{{ a.member_code }} · </span>{{ a.email }}
                                </p>
                                <p v-if="!a.member_code" class="text-xs text-amber-600">No member registration yet</p>
                            </td>
                            <td class="px-4 py-3">{{ a.region }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="a.is_active ? 'bg-accent text-accent-foreground' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ a.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="toggleActive(a)">
                                        {{ a.is_active ? 'Deactivate' : 'Activate' }}
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-destructive" @click="demote(a)">
                                        <UserMinus /> Remove admin role
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!admins.length">
                            <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                No regional admins yet. Choose one from the approved members below.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Phones: one card per admin -->
                <ul class="divide-y md:hidden">
                    <li v-for="a in admins" :key="`card-${a.id}`" class="grid gap-2 p-4 text-sm" :class="{ 'opacity-60': !a.is_active }">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ a.name }}</p>
                                <p class="truncate text-muted-foreground">{{ a.email }}</p>
                                <p v-if="a.member_code" class="font-mono text-xs text-muted-foreground">{{ a.member_code }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="a.is_active ? 'bg-accent text-accent-foreground' : 'bg-muted text-muted-foreground'"
                            >
                                {{ a.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="text-muted-foreground">{{ a.region }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <Button size="sm" variant="outline" @click="toggleActive(a)">
                                {{ a.is_active ? 'Deactivate' : 'Activate' }}
                            </Button>
                            <Button size="sm" variant="outline" class="text-destructive" @click="demote(a)">
                                <UserMinus /> Remove role
                            </Button>
                        </div>
                    </li>
                    <li v-if="!admins.length" class="p-6 text-center text-sm text-muted-foreground">No regional admins yet.</li>
                </ul>

            </CardContent>
        </Card>

        <!-- Choose from members -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Add a regional admin from the members</CardTitle>
                <CardDescription>
                    Only approved members are listed. They become admin of the region of their membership.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <select v-model="region" :class="selectClass" aria-label="Filter by region">
                        <option :value="0">All regions</option>
                        <option v-for="r in regions" :key="r.id" :value="r.id">
                            {{ r.name === r.label ? r.name : `${r.name} – ${r.label}` }}
                        </option>
                    </select>
                    <div class="relative flex-1">
                        <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" class="pl-9" placeholder="Search name, member ID or RIC organization…" />
                    </div>
                </div>

                <ul class="divide-y rounded-lg border">
                    <li v-for="c in candidates" :key="c.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium">{{ c.full_name }}</p>
                            <p class="text-muted-foreground">
                                <span class="font-mono text-xs">{{ c.member_code }}</span>
                                · {{ [c.city, c.region].filter(Boolean).join(', ') }}
                                <template v-if="c.ric_organization"> · {{ c.ric_organization }}</template>
                            </p>
                        </div>
                        <Button size="sm" @click="promote(c)"><ShieldPlus /> Make regional admin</Button>
                    </li>
                    <li v-if="!candidates.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        No approved members found. Members appear here after their registration is approved.
                    </li>
                </ul>
                <p class="text-xs text-muted-foreground">Showing up to 15 results. Use the search to find a specific member.</p>
            </CardContent>
        </Card>
    </div>
</template>

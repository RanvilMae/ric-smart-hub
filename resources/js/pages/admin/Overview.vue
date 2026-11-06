<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, CircleCheck, Clock, Trophy, TriangleAlert, Users } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type RegionRow = {
    id: number;
    name: string;
    label: string;
    total: number;
    approved: number;
    pending: number;
    organizations: number;
    admins: number;
};

const props = defineProps<{
    stats: {
        registered: number;
        approved: number;
        pending: number;
        organizations: number;
        regionalAdmins: number;
        regionsWithoutAdmin: number;
    };
    regions: RegionRow[];
    mostActive: RegionRow | null;
    topOrganizations: { name: string; region: string | null; members: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'National overview', href: '/admin' }],
    },
});

const n = (v: number) => v.toLocaleString();

// Regions sorted by number of registered members (largest first)
const ranked = computed(() => [...props.regions].sort((a, b) => b.total - a.total));
const maxTotal = computed(() => Math.max(1, ...props.regions.map((r) => r.total)));
const pct = (v: number) => `${(v / maxTotal.value) * 100}%`;
</script>

<template>
    <Head title="National overview" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading title="National overview" description="Summary of registered RIC members in all regions" />

        <!-- 1. Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    Total registered members <Users class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(stats.registered) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Approved + waiting for approval</p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    Approved <CircleCheck class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(stats.approved) }}</p>
            </div>
            <Link href="/admin/members?status=pending" class="rounded-xl border bg-card p-5 transition hover:bg-accent">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    Waiting for approval <Clock class="size-4 text-amber-500" />
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(stats.pending) }}</p>
            </Link>
            <div class="rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    RIC organizations <Building2 class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(stats.organizations) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">With at least one registered member</p>
            </div>
        </div>

        <div
            v-if="stats.regionsWithoutAdmin > 0"
            class="flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <TriangleAlert class="size-4 shrink-0" />
            <span>
                {{ stats.regionsWithoutAdmin }} of {{ regions.length }} regions have no active admin yet, so their
                registrations cannot be approved.
                <Link href="/admin/admins" class="font-medium underline">Add regional admins</Link>
            </span>
        </div>

        <!-- 2. Most active region -->
        <div v-if="mostActive" class="relative overflow-hidden rounded-2xl bg-linear-to-br from-primary to-teal-700 p-6 text-primary-foreground shadow-lg shadow-primary/20">
            <div class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-yellow-300/20 blur-2xl" />
            <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex size-12 items-center justify-center rounded-xl bg-white/15">
                        <Trophy class="size-6 text-yellow-300" />
                    </div>
                    <div>
                        <p class="text-sm text-primary-foreground/80">Region with the most active RIC organizations</p>
                        <p class="text-2xl font-semibold">
                            {{ mostActive.name }}
                            <span v-if="mostActive.name !== mostActive.label" class="font-normal text-primary-foreground/80">
                                – {{ mostActive.label }}
                            </span>
                        </p>
                    </div>
                </div>
                <dl class="grid grid-cols-3 gap-6 text-center">
                    <div>
                        <dd class="text-2xl font-semibold tabular-nums">{{ n(mostActive.organizations) }}</dd>
                        <dt class="text-xs text-primary-foreground/80">RIC organizations</dt>
                    </div>
                    <div>
                        <dd class="text-2xl font-semibold tabular-nums">{{ n(mostActive.total) }}</dd>
                        <dt class="text-xs text-primary-foreground/80">Registered members</dt>
                    </div>
                    <div>
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ stats.registered ? Math.round((mostActive.total / stats.registered) * 100) : 0 }}%
                        </dd>
                        <dt class="text-xs text-primary-foreground/80">of all members</dt>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <!-- 3. Members per region -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Registered members per region</CardTitle>
                    <CardDescription class="flex flex-wrap items-center gap-4">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary" /> Approved</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-amber-400" /> Waiting for approval</span>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-3">
                        <li v-for="r in ranked" :key="r.id" class="grid grid-cols-[4.5rem_1fr_3rem] items-center gap-2 sm:grid-cols-[7.5rem_1fr_3.5rem] sm:gap-3 text-sm">
                            <span class="truncate" :title="`${r.name} – ${r.label}`">
                                {{ r.name }}
                                <span v-if="!r.admins" class="text-xs text-amber-600" title="No regional admin">•</span>
                            </span>
                            <span class="flex h-3 overflow-hidden rounded-full bg-muted">
                                <span class="h-full bg-primary" :style="{ width: pct(r.approved) }" />
                                <span class="h-full bg-amber-400" :style="{ width: pct(r.pending) }" />
                            </span>
                            <span class="text-right font-medium tabular-nums">{{ n(r.total) }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- 4. Top RIC organizations -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Most active RIC organizations</CardTitle>
                    <CardDescription>Top 10 by number of registered members</CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="!topOrganizations.length" class="text-sm text-muted-foreground">No members registered yet.</p>
                    <ol v-else class="divide-y">
                        <li v-for="(o, i) in topOrganizations" :key="`${o.region}-${o.name}`" class="flex items-center gap-3 py-2.5 text-sm">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                :class="i < 3 ? 'bg-yellow-300 text-yellow-900' : 'bg-muted text-muted-foreground'"
                            >
                                {{ i + 1 }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ o.name }}</span>
                                <span class="block text-xs text-muted-foreground">{{ o.region }}</span>
                            </span>
                            <span class="font-semibold tabular-nums">{{ n(o.members) }}</span>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </div>

        <!-- Detail table -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Summary per region</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 pr-4 font-medium">Region</th>
                            <th class="py-2 pr-4 text-right font-medium">Registered</th>
                            <th class="py-2 pr-4 text-right font-medium">Approved</th>
                            <th class="py-2 pr-4 text-right font-medium">Pending</th>
                            <th class="py-2 pr-4 text-right font-medium">RIC orgs</th>
                            <th class="py-2 text-right font-medium">Admins</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="r in regions" :key="r.id">
                            <td class="py-2 pr-4">
                                <span class="font-medium">{{ r.name }}</span>
                                <span v-if="r.name !== r.label" class="text-muted-foreground"> – {{ r.label }}</span>
                            </td>
                            <td class="py-2 pr-4 text-right font-medium tabular-nums">{{ n(r.total) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(r.approved) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums" :class="r.pending ? 'font-medium text-amber-600' : 'text-muted-foreground'">
                                {{ n(r.pending) }}
                            </td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(r.organizations) }}</td>
                            <td class="py-2 text-right">
                                <span v-if="r.admins" class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">
                                    {{ r.admins }}
                                </span>
                                <span v-else class="text-xs text-amber-600">none</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-semibold">
                            <td class="py-2 pr-4">Total</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(stats.registered) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(stats.approved) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(stats.pending) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(stats.organizations) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ stats.regionalAdmins }}</td>
                        </tr>
                    </tfoot>
                </table>
            </CardContent>
        </Card>
    </div>
</template>

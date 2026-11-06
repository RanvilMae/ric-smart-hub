<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, CalendarPlus, CircleCheck, Clock, MapPin, Trophy, Users } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type ProvinceRow = {
    id: number;
    name: string;
    total: number;
    approved: number;
    pending: number;
    organizations: number;
};

const props = defineProps<{
    region: { name: string; label: string };
    stats: { registered: number; approved: number; pending: number; organizations: number; thisYear: number };
    provinces: ProvinceRow[];
    mostActive: ProvinceRow | null;
    topOrganizations: { name: string; place: string | null; members: number }[];
    topCities: { name: string; province: string | null; members: number; organizations: number }[];
    latestPending: { id: number; full_name: string; location: string; ric_organization: string | null; submitted: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Region dashboard', href: '/admin/region' }],
    },
});

const n = (v: number) => v.toLocaleString();
const regionTitle = computed(() =>
    props.region.name === props.region.label ? props.region.name : `${props.region.name} – ${props.region.label}`,
);

// Provinces sorted by number of registered members (largest first)
const ranked = computed(() => [...props.provinces].sort((a, b) => b.total - a.total));
const maxTotal = computed(() => Math.max(1, ...props.provinces.map((p) => p.total)));
const pct = (v: number) => `${(v / maxTotal.value) * 100}%`;
</script>

<template>
    <Head title="Region dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading :title="regionTitle" description="Summary of registered RIC members in your region" />

        <!-- 1. Summary cards -->
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
            <div class="col-span-2 rounded-xl border bg-card p-5 xl:col-span-1">
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
            <Link
                href="/admin/members?status=pending"
                class="rounded-xl border bg-card p-5 transition hover:bg-accent"
                :class="{ 'border-amber-300 bg-amber-50/60': stats.pending }"
            >
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    For approval <Clock class="size-4 text-amber-500" />
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(stats.pending) }}</p>
            </Link>
            <div class="rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    RIC organizations <Building2 class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(stats.organizations) }}</p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    New this year <CalendarPlus class="size-4 text-primary" />
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ n(stats.thisYear) }}</p>
            </div>
        </div>

        <!-- 2. Most active province -->
        <div
            v-if="mostActive"
            class="relative overflow-hidden rounded-2xl bg-linear-to-br from-primary to-teal-700 p-6 text-primary-foreground shadow-lg shadow-primary/20"
        >
            <div class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-yellow-300/20 blur-2xl" />
            <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/15">
                        <Trophy class="size-6 text-yellow-300" />
                    </div>
                    <div>
                        <p class="text-sm text-primary-foreground/80">Province with the most active RIC organizations</p>
                        <p class="text-2xl font-semibold">{{ mostActive.name }}</p>
                    </div>
                </div>
                <dl class="grid grid-cols-3 gap-4 text-center sm:gap-6">
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
                        <dt class="text-xs text-primary-foreground/80">of the region</dt>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <!-- 3. Members per province -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Registered members per province</CardTitle>
                    <CardDescription class="flex flex-wrap items-center gap-4">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary" /> Approved</span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2.5 rounded-sm bg-amber-400" /> Waiting for approval
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-3">
                        <li
                            v-for="p in ranked"
                            :key="p.id"
                            class="grid grid-cols-[6rem_1fr_3rem] items-center gap-2 text-sm sm:grid-cols-[10rem_1fr_3.5rem] sm:gap-3"
                        >
                            <span class="truncate" :title="p.name">{{ p.name }}</span>
                            <span class="flex h-3 overflow-hidden rounded-full bg-muted">
                                <span class="h-full bg-primary" :style="{ width: pct(p.approved) }" />
                                <span class="h-full bg-amber-400" :style="{ width: pct(p.pending) }" />
                            </span>
                            <span class="text-right font-medium tabular-nums">{{ n(p.total) }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- 4. Waiting for approval -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Latest registrations to review</CardTitle>
                    <CardDescription>Only you can approve members of your region</CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="!latestPending.length" class="text-sm text-muted-foreground">Nothing waiting. 🎉</p>
                    <template v-else>
                        <ul class="divide-y">
                            <li v-for="m in latestPending" :key="m.id">
                                <Link :href="`/admin/members/${m.id}`" class="block py-2.5 text-sm hover:bg-muted/40">
                                    <span class="flex items-baseline justify-between gap-3">
                                        <span class="truncate font-medium text-primary">{{ m.full_name }}</span>
                                        <span class="shrink-0 text-xs text-muted-foreground">{{ m.submitted }}</span>
                                    </span>
                                    <span class="block truncate text-muted-foreground">
                                        {{ [m.location, m.ric_organization].filter(Boolean).join(' · ') }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <Link href="/admin/members?status=pending" class="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                            Review all {{ n(stats.pending) }} →
                        </Link>
                    </template>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- 5. Top RIC organizations -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Most active RIC organizations</CardTitle>
                    <CardDescription>Top 10 by number of registered members</CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="!topOrganizations.length" class="text-sm text-muted-foreground">No members registered yet.</p>
                    <ol v-else class="divide-y">
                        <li v-for="(o, i) in topOrganizations" :key="o.name" class="flex items-center gap-3 py-2.5 text-sm">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                :class="i < 3 ? 'bg-yellow-300 text-yellow-900' : 'bg-muted text-muted-foreground'"
                            >
                                {{ i + 1 }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ o.name }}</span>
                                <span v-if="o.place" class="block text-xs text-muted-foreground">{{ o.place }}</span>
                            </span>
                            <span class="font-semibold tabular-nums">{{ n(o.members) }}</span>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <!-- 6. Top cities / municipalities -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Top cities and municipalities</CardTitle>
                    <CardDescription>Top 10 by number of registered members</CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="!topCities.length" class="text-sm text-muted-foreground">No members registered yet.</p>
                    <ol v-else class="divide-y">
                        <li v-for="c in topCities" :key="`${c.province}-${c.name}`" class="flex items-center gap-3 py-2.5 text-sm">
                            <MapPin class="size-4 shrink-0 text-primary" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ c.name }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ [c.province, `${n(c.organizations)} RIC org${c.organizations === 1 ? '' : 's'}`].filter(Boolean).join(' · ') }}
                                </span>
                            </span>
                            <span class="font-semibold tabular-nums">{{ n(c.members) }}</span>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </div>

        <!-- 7. Detail table -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Summary per province</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 pr-4 font-medium">Province</th>
                            <th class="py-2 pr-4 text-right font-medium">Registered</th>
                            <th class="py-2 pr-4 text-right font-medium">Approved</th>
                            <th class="py-2 pr-4 text-right font-medium">Pending</th>
                            <th class="py-2 text-right font-medium">RIC orgs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in provinces" :key="p.id">
                            <td class="py-2 pr-4 font-medium">{{ p.name }}</td>
                            <td class="py-2 pr-4 text-right font-medium tabular-nums">{{ n(p.total) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(p.approved) }}</td>
                            <td
                                class="py-2 pr-4 text-right tabular-nums"
                                :class="p.pending ? 'font-medium text-amber-600' : 'text-muted-foreground'"
                            >
                                {{ n(p.pending) }}
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ n(p.organizations) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-semibold">
                            <td class="py-2 pr-4">Total</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(stats.registered) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(stats.approved) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ n(stats.pending) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ n(stats.organizations) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </CardContent>
        </Card>
    </div>
</template>

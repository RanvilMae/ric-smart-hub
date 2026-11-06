<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Globe,
    HeartHandshake,
    Home,
    Landmark,
    MapPin,
    Sprout,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { dashboard, login, register } from '@/routes';

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const pillars = [
    {
        icon: Home,
        title: 'Home resource management',
        text: 'Helping rural families make the most of their homes, food, income and resources.',
    },
    {
        icon: Users,
        title: 'Leadership',
        text: 'Developing rural women as leaders of their clubs, families and communities.',
    },
    {
        icon: Sprout,
        title: 'Agri-entrepreneurship',
        text: 'Turning farm skills into livelihoods and enterprises that uplift families.',
    },
    {
        icon: HeartHandshake,
        title: 'Farm family approach',
        text: 'Integrated, holistic extension services delivered at the grassroots level.',
    },
];

const logoLayers = [
    {
        ring: 'Outer circle',
        title: 'Rural Improvement Clubs · Philippines',
        text: 'Yellow letters on green: the organization’s national identity and enduring commitment to rural development.',
        swatch: 'bg-[#047C2E] ring-2 ring-yellow-300',
    },
    {
        ring: 'Middle circle',
        title: 'Home · Family · Community',
        text: 'The foundational values that guide the organization in empowering individuals and strengthening rural communities.',
        swatch: 'bg-white ring-2 ring-[#047C2E]',
    },
    {
        ring: 'Inner circle',
        title: 'Flag, sun and three stars',
        text: 'Blue and red of the Philippine flag, a golden eight-rayed sun and three stars: patriotism, national unity and service to the Filipino people.',
        swatch: 'bg-gradient-to-br from-blue-600 via-yellow-300 to-red-600',
    },
    {
        ring: 'At the center',
        title: 'Nipa hut, mother and child',
        text: 'A nipa hut on three posts for Filipino culture and rural life; a woman holding her child for maternal love, women’s empowerment and family welfare.',
        swatch: 'bg-amber-100 ring-2 ring-amber-300',
    },
];
</script>

<template>
    <Head title="Rural Improvement Clubs of the Philippines" />

    <div class="min-h-svh bg-background text-foreground">
        <!-- Navigation -->
        <header class="sticky top-0 z-30 border-b border-border/60 bg-background/80 backdrop-blur">
            <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 md:px-6">
                <a href="#top" class="flex items-center gap-3">
                    <img src="/images/ric-logo.png" alt="" class="size-10 rounded-full" />
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold">RIC Smart Hub</span>
                        <span class="block text-xs text-muted-foreground">Rural Improvement Clubs of the Philippines</span>
                    </span>
                </a>

                <div class="hidden items-center gap-6 text-sm md:flex">
                    <a href="#about" class="text-muted-foreground hover:text-foreground">About</a>
                    <a href="#logo" class="text-muted-foreground hover:text-foreground">Our logo</a>
                    <a href="#contact" class="text-muted-foreground hover:text-foreground">Contact</a>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardUrl"
                        class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="login()" class="rounded-full px-4 py-2 text-sm font-medium hover:bg-accent">
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <main id="top">
            <!-- Hero -->
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute -top-40 -right-40 size-[36rem] rounded-full bg-primary/10 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-40 -left-40 size-[30rem] rounded-full bg-yellow-300/20 blur-3xl" />

                <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 md:grid-cols-[1.15fr_1fr] md:px-6 md:py-24">
                    <div class="space-y-7">
                        <span class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-accent px-3 py-1 text-xs font-medium text-accent-foreground">
                            <span class="size-1.5 rounded-full bg-primary" />
                            Empowering rural women since 1924
                        </span>

                        <h1 class="text-4xl leading-[1.1] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            Home. Family.
                            <span class="text-primary">Community.</span>
                        </h1>

                        <p class="max-w-xl text-lg text-pretty text-muted-foreground">
                            The Rural Improvement Club of the Philippines is the largest and one of the
                            oldest women’s organizations in the country. The RIC Smart Hub is its
                            national database of members.
                        </p>

                        <div class="flex flex-wrap gap-3">
                            <Link
                                v-if="!$page.props.auth.user"
                                :href="register()"
                                class="group inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/20 transition hover:bg-primary/90"
                            >
                                Register as a member
                                <ArrowRight class="size-4 transition group-hover:translate-x-0.5" />
                            </Link>
                            <Link
                                v-else
                                :href="dashboardUrl"
                                class="group inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/20 transition hover:bg-primary/90"
                            >
                                Go to dashboard
                                <ArrowRight class="size-4 transition group-hover:translate-x-0.5" />
                            </Link>
                            <a href="#about" class="inline-flex items-center rounded-full border px-6 py-3 text-sm font-semibold transition hover:bg-accent">
                                Learn more
                            </a>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-sm">
                        <div class="absolute inset-0 rounded-full bg-primary/10" />
                        <div class="absolute inset-6 rounded-full border-2 border-dashed border-primary/25" />
                        <img
                            src="/images/ric-logo.png"
                            alt="Rural Improvement Clubs Philippines logo"
                            class="relative aspect-square w-full rounded-full p-10 drop-shadow-xl"
                        />
                    </div>
                </div>

                <!-- Quick facts -->
                <div class="relative mx-auto max-w-6xl px-4 pb-16 md:px-6">
                    <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border bg-border md:grid-cols-4">
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">Founded</dt>
                            <dd class="mt-1 text-2xl font-semibold">1924</dd>
                        </div>
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">Founder</dt>
                            <dd class="mt-1 text-lg font-semibold">Maria Y. Orosa</dd>
                        </div>
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">Reach</dt>
                            <dd class="mt-1 text-lg font-semibold">All 18 regions</dd>
                        </div>
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">Principal office</dt>
                            <dd class="mt-1 text-lg font-semibold">ATI, Quezon City</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- About -->
            <section id="about" class="scroll-mt-20 border-y bg-card">
                <div class="mx-auto grid max-w-6xl gap-12 px-4 py-20 md:grid-cols-2 md:px-6">
                    <div class="space-y-5">
                        <p class="text-sm font-semibold tracking-wider text-primary uppercase">About the organization</p>
                        <h2 class="text-3xl font-semibold tracking-tight text-balance">Uplifting rural families for a century</h2>
                        <p class="text-muted-foreground">
                            Founded in 1924 by <strong class="text-foreground">Maria Y. Orosa</strong>, the RIC’s
                            main goal is to empower rural women and uplift their families through home resource
                            management, leadership, and agri-entrepreneurship, fostering comprehensive
                            socio-economic development in their communities.
                        </p>
                        <p class="text-muted-foreground">
                            The RIC is a key component in delivering integrated, holistic and effective extension
                            services at the grassroots level, using a farm family approach to empower rural
                            communities and support agricultural development.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="p in pillars"
                            :key="p.title"
                            class="rounded-2xl border bg-background p-5 transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div class="mb-4 flex size-10 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                <component :is="p.icon" class="size-5" />
                            </div>
                            <h3 class="font-semibold">{{ p.title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ p.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Logo meaning -->
            <section id="logo" class="scroll-mt-20">
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 md:grid-cols-[1fr_1.3fr] md:px-6">
                    <div class="mx-auto w-full max-w-xs">
                        <img src="/images/ric-logo.png" alt="RIC logo" class="aspect-square w-full rounded-full shadow-2xl shadow-primary/20" />
                    </div>

                    <div class="space-y-6">
                        <div class="space-y-3">
                            <p class="text-sm font-semibold tracking-wider text-primary uppercase">The RIC logo</p>
                            <h2 class="text-3xl font-semibold tracking-tight">Three circles, one mission</h2>
                            <p class="text-muted-foreground">
                                The logo’s concentric circles each represent the organization’s core values and mission.
                            </p>
                        </div>

                        <ol class="space-y-4">
                            <li v-for="l in logoLayers" :key="l.ring" class="flex gap-4">
                                <span :class="['mt-1 size-5 shrink-0 rounded-full', l.swatch]" />
                                <div>
                                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ l.ring }}</p>
                                    <p class="font-semibold">{{ l.title }}</p>
                                    <p class="text-sm text-muted-foreground">{{ l.text }}</p>
                                </div>
                            </li>
                        </ol>

                        <p class="rounded-xl border-l-4 border-primary bg-accent/60 p-4 text-sm">
                            Together, these elements embody the organization’s commitment to strengthening
                            families and uplifting rural communities throughout the Philippines.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Contact -->
            <section id="contact" class="scroll-mt-20 px-4 pb-20 md:px-6">
                <div class="relative mx-auto max-w-6xl overflow-hidden rounded-3xl bg-primary px-6 py-12 text-primary-foreground md:px-12">
                    <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-yellow-300/20 blur-2xl" />

                    <div class="relative grid gap-10 md:grid-cols-[1fr_1.2fr]">
                        <div class="space-y-3">
                            <h2 class="text-3xl font-semibold tracking-tight">Get in touch</h2>
                            <p class="text-primary-foreground/80">
                                Reach the RIC national office or your regional ATI training center for membership concerns.
                            </p>
                        </div>

                        <ul class="grid gap-4 sm:grid-cols-3 md:grid-cols-1 lg:grid-cols-3">
                            <li class="rounded-2xl bg-white/10 p-5">
                                <MapPin class="mb-3 size-5 text-yellow-300" />
                                <p class="text-xs text-primary-foreground/70">Principal office</p>
                                <p class="font-medium">Agricultural Training Institute, Diliman, Quezon City</p>
                            </li>
                            <li class="rounded-2xl bg-white/10 p-5">
                                <Globe class="mb-3 size-5 text-yellow-300" />
                                <p class="text-xs text-primary-foreground/70">Website</p>
                                <a href="https://www.ati.da.gov.ph" target="_blank" rel="noopener" class="font-medium underline-offset-4 hover:underline">
                                    www.ati.da.gov.ph
                                </a>
                            </li>
                            <li class="rounded-2xl bg-white/10 p-5">
                                <Landmark class="mb-3 size-5 text-yellow-300" />
                                <p class="text-xs text-primary-foreground/70">Facebook page</p>
                                <a
                                    href="https://www.facebook.com/search/pages?q=Rural%20Improvement%20Clubs%20of%20the%20Philippines"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium underline-offset-4 hover:underline"
                                >
                                    Rural Improvement Clubs of the Philippines
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-muted-foreground sm:flex-row md:px-6">
                <p>© {{ new Date().getFullYear() }} Rural Improvement Clubs of the Philippines</p>
                <p>RIC Smart Hub · National Database</p>
            </div>
        </footer>
    </div>
</template>
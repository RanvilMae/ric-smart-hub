<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import {
  ArrowDown,
  ArrowUp,
  ChevronDown,
  Eye,
  EyeOff,
  ListChecks,
  Lock,
  Pencil,
  Plus,
  Trash2,
} from "@lucide/vue";
import { reactive, ref } from "vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";

type Option = { id: number; name: string; is_active: boolean; members: number };
type OptionList = {
  key: string;
  label: string;
  question: string;
  options: Option[];
};

type Service = {
  id: number;
  name: string;
  description: string | null;
  follow_up: "training" | "scholarship" | "lsa" | null;
  is_locked: boolean;
  is_active: boolean;
  members: number;
  lists: OptionList[];
};

defineProps<{ services: Service[] }>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: "National overview", href: "/admin" },
      { title: "ATI services", href: "/admin/ati-services" },
    ],
  },
});

const followUpLabel: Record<string, string> = {
  training: "Asks which trainings",
  scholarship: "Asks which scholarship",
  lsa: "Asks LSA level and type",
};

const n = (v: number) => v.toLocaleString();
const opts = { preserveScroll: true };

// ---- Add
const addForm = useForm({ name: "", description: "" });
const add = () =>
  addForm.post("/admin/ati-services", {
    ...opts,
    onSuccess: () => addForm.reset(),
  });

// ---- Edit
const editing = ref<Service | null>(null);
const editForm = useForm({ name: "", description: "" });
const openEdit = (s: Service) => {
  editing.value = s;
  editForm.clearErrors();
  editForm.name = s.name;
  editForm.description = s.description ?? "";
};
const saveEdit = () =>
  editing.value &&
  editForm.put(`/admin/ati-services/${editing.value.id}`, {
    ...opts,
    onSuccess: () => (editing.value = null),
  });

// ---- Show / hide, reorder
const busyId = ref<number | null>(null);
const busy = { onFinish: () => (busyId.value = null) };

const toggle = (s: Service) => {
  busyId.value = s.id;
  router.put(
    `/admin/ati-services/${s.id}`,
    { is_active: !s.is_active },
    { ...opts, ...busy },
  );
};
const move = (s: Service, direction: "up" | "down") => {
  busyId.value = s.id;
  router.patch(
    `/admin/ati-services/${s.id}/move`,
    { direction },
    { ...opts, ...busy },
  );
};

// ---- Delete
const deleting = ref<Service | null>(null);
const hideInstead = () => {
  if (deleting.value) toggle(deleting.value);
  deleting.value = null;
};
const confirmDelete = () => {
  if (!deleting.value) return;
  router.delete(`/admin/ati-services/${deleting.value.id}`, {
    ...opts,
    onFinish: () => (deleting.value = null),
  });
};

// ---- Choices under a service (trainings, scholarships, LSA levels and types)
const expanded = reactive<Record<number, boolean>>({});
const choiceCount = (s: Service) =>
  s.lists.reduce((sum, l) => sum + l.options.length, 0);

const newChoice = reactive<Record<string, string>>({});
const choiceForm = useForm({ list: "", name: "" });
const addChoice = (list: OptionList) => {
  choiceForm.list = list.key;
  choiceForm.name = (newChoice[list.key] ?? "").trim();
  choiceForm.post("/admin/ati-service-options", {
    ...opts,
    onSuccess: () => (newChoice[list.key] = ""),
  });
};

const toggleChoice = (o: Option) =>
  router.put(
    `/admin/ati-service-options/${o.id}`,
    { is_active: !o.is_active },
    opts,
  );

const editingChoice = ref<Option | null>(null);
const choiceEditForm = useForm({ name: "" });
const openChoiceEdit = (o: Option) => {
  editingChoice.value = o;
  choiceEditForm.clearErrors();
  choiceEditForm.name = o.name;
};
const saveChoice = () =>
  editingChoice.value &&
  choiceEditForm.put(`/admin/ati-service-options/${editingChoice.value.id}`, {
    ...opts,
    onSuccess: () => (editingChoice.value = null),
  });

const deletingChoice = ref<Option | null>(null);
const hideChoiceInstead = () => {
  if (deletingChoice.value) toggleChoice(deletingChoice.value);
  deletingChoice.value = null;
};
const confirmChoiceDelete = () => {
  if (!deletingChoice.value) return;
  router.delete(`/admin/ati-service-options/${deletingChoice.value.id}`, {
    ...opts,
    onFinish: () => (deletingChoice.value = null),
  });
};
</script>

<template>
  <Head title="ATI services" />

  <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
    <Heading
      title="ATI services"
      description="Choices for “Which services from the Agricultural Training Institute have you availed?” on the registration form."
    />

    <!-- Add -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base">Add a service</CardTitle>
        <CardDescription
          >It appears on the registration form right away.</CardDescription
        >
      </CardHeader>
      <CardContent>
        <form
          class="grid gap-3 md:grid-cols-[1fr_1.5fr_auto] md:items-start"
          @submit.prevent="add"
        >
          <div class="grid gap-1.5">
            <Label for="new-name">Service name *</Label>
            <Input
              id="new-name"
              v-model="addForm.name"
              placeholder="e.g. Farm School"
              maxlength="100"
            />
            <InputError :message="addForm.errors.name" />
          </div>
          <div class="grid gap-1.5">
            <Label for="new-description">Description (optional)</Label>
            <Input
              id="new-description"
              v-model="addForm.description"
              placeholder="Short note for admins"
              maxlength="255"
            />
            <InputError :message="addForm.errors.description" />
          </div>
          <Button
            type="submit"
            class="md:mt-6"
            :disabled="addForm.processing || !addForm.name.trim()"
          >
            <Spinner v-if="addForm.processing" />
            <Plus v-else /> Add service
          </Button>
        </form>
      </CardContent>
    </Card>

    <!-- List -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base">Services on the form</CardTitle>
        <CardDescription
          >Listed in the order members see them. Hidden services stay on
          existing member records.</CardDescription
        >
      </CardHeader>
      <CardContent class="p-0">
        <ul class="divide-y border-t">
          <li
            v-for="(s, i) in services"
            :key="s.id"
            :class="{ 'bg-muted/40': !s.is_active }"
          >
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
              <!-- Order -->
              <div class="hidden flex-col sm:flex">
                <button
                  type="button"
                  class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                  :disabled="i === 0 || busyId === s.id"
                  :aria-label="`Move ${s.name} up`"
                  @click="move(s, 'up')"
                >
                  <ArrowUp class="size-4" />
                </button>
                <button
                  type="button"
                  class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                  :disabled="i === services.length - 1 || busyId === s.id"
                  :aria-label="`Move ${s.name} down`"
                  @click="move(s, 'down')"
                >
                  <ArrowDown class="size-4" />
                </button>
              </div>

              <!-- Details -->
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span
                    class="font-medium"
                    :class="{
                      'text-muted-foreground line-through': !s.is_active,
                    }"
                    >{{ s.name }}</span
                  >
                  <span
                    v-if="!s.is_active"
                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                  >
                    Hidden from form
                  </span>
                  <span
                    v-if="s.follow_up"
                    class="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                    title="This service opens extra questions on the form, so its name cannot be changed"
                  >
                    <Lock class="size-3" /> {{ followUpLabel[s.follow_up] }}
                  </span>
                </div>
                <p v-if="s.description" class="text-sm text-muted-foreground">
                  {{ s.description }}
                </p>
                <p class="text-xs text-muted-foreground">
                  Ticked by {{ n(s.members) }}
                  {{ s.members === 1 ? "member" : "members" }}
                </p>
              </div>

              <!-- Actions -->
              <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                <div class="col-span-2 flex gap-2 sm:hidden">
                  <Button
                    size="sm"
                    variant="outline"
                    class="flex-1"
                    :disabled="i === 0 || busyId === s.id"
                    @click="move(s, 'up')"
                  >
                    <ArrowUp /> Up
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    class="flex-1"
                    :disabled="i === services.length - 1 || busyId === s.id"
                    @click="move(s, 'down')"
                  >
                    <ArrowDown /> Down
                  </Button>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  :disabled="busyId === s.id"
                  @click="toggle(s)"
                >
                  <component :is="s.is_active ? EyeOff : Eye" />
                  {{ s.is_active ? "Hide" : "Show" }}
                </Button>
                <Button size="sm" variant="outline" @click="openEdit(s)"
                  ><Pencil /> Edit</Button
                >
                <Button
                  v-if="!s.is_locked"
                  size="sm"
                  variant="outline"
                  class="col-span-2 text-destructive sm:col-span-1"
                  @click="deleting = s"
                >
                  <Trash2 /> Delete
                </Button>
                <Button
                  v-if="s.lists.length"
                  size="sm"
                  :variant="expanded[s.id] ? 'secondary' : 'outline'"
                  class="col-span-2 sm:col-span-1"
                  @click="expanded[s.id] = !expanded[s.id]"
                >
                  <ListChecks /> Details ({{ choiceCount(s) }})
                  <ChevronDown
                    class="transition"
                    :class="{ 'rotate-180': expanded[s.id] }"
                  />
                </Button>
              </div>
            </div>

            <!-- Details: choices of the follow-up questions -->
            <div
              v-if="s.lists.length && expanded[s.id]"
              class="grid gap-4 px-4 pb-4 lg:grid-cols-2"
            >
              <div
                v-for="list in s.lists"
                :key="list.key"
                class="rounded-lg border bg-background p-4"
              >
                <p class="text-sm font-medium">{{ list.label }}</p>
                <p class="mb-3 text-xs text-muted-foreground">
                  Form question: “{{ list.question }}”
                </p>

                <ul class="divide-y rounded-md border">
                  <li
                    v-for="o in list.options"
                    :key="o.id"
                    class="flex flex-col gap-2 px-3 py-2 text-sm sm:flex-row sm:items-center"
                    :class="{ 'bg-muted/50': !o.is_active }"
                  >
                    <div class="min-w-0 flex-1">
                      <span
                        :class="{
                          'text-muted-foreground line-through': !o.is_active,
                        }"
                        >{{ o.name }}</span
                      >
                      <span class="block text-xs text-muted-foreground">
                        {{ o.is_active ? "" : "Hidden · " }}Chosen by
                        {{ n(o.members) }}
                        {{ o.members === 1 ? "member" : "members" }}
                      </span>
                    </div>
                    <div class="flex shrink-0 gap-1">
                      <Button
                        size="sm"
                        variant="ghost"
                        :aria-label="o.is_active ? 'Hide' : 'Show'"
                        @click="toggleChoice(o)"
                      >
                        <component :is="o.is_active ? EyeOff : Eye" />
                        <span class="sm:sr-only">{{
                          o.is_active ? "Hide" : "Show"
                        }}</span>
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        aria-label="Edit"
                        @click="openChoiceEdit(o)"
                      >
                        <Pencil /> <span class="sm:sr-only">Edit</span>
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        aria-label="Delete"
                        @click="deletingChoice = o"
                      >
                        <Trash2 /> <span class="sm:sr-only">Delete</span>
                      </Button>
                    </div>
                  </li>
                  <li
                    v-if="!list.options.length"
                    class="px-3 py-4 text-center text-sm text-muted-foreground"
                  >
                    No choices yet.
                  </li>
                </ul>

                <form class="mt-3 flex gap-2" @submit.prevent="addChoice(list)">
                  <Input
                    v-model="newChoice[list.key]"
                    :placeholder="`Add to ${list.label.toLowerCase()}…`"
                    maxlength="255"
                  />
                  <Button
                    type="submit"
                    size="sm"
                    class="h-9 shrink-0"
                    :disabled="
                      choiceForm.processing ||
                      !(newChoice[list.key] ?? '').trim()
                    "
                  >
                    <Plus /> Add
                  </Button>
                </form>
                <InputError
                  v-if="choiceForm.list === list.key"
                  class="mt-1"
                  :message="choiceForm.errors.name"
                />
              </div>
            </div>
          </li>
          <li
            v-if="!services.length"
            class="p-8 text-center text-sm text-muted-foreground"
          >
            No ATI services yet.
          </li>
        </ul>
      </CardContent>
    </Card>

    <!-- Edit dialog -->
    <Dialog :open="!!editing" @update:open="(v) => !v && (editing = null)">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Edit ATI service</DialogTitle>
          <DialogDescription v-if="editing?.is_locked">
            The name stays “{{ editing.name }}” because it opens extra questions
            on the form.
          </DialogDescription>
          <DialogDescription v-else-if="editing?.members">
            Renaming also updates the {{ n(editing.members) }} member records
            that ticked it.
          </DialogDescription>
        </DialogHeader>
        <form id="edit-service" class="grid gap-4" @submit.prevent="saveEdit">
          <div class="grid gap-1.5">
            <Label for="edit-name">Service name *</Label>
            <Input
              id="edit-name"
              v-model="editForm.name"
              :disabled="editing?.is_locked"
              maxlength="100"
            />
            <InputError :message="editForm.errors.name" />
          </div>
          <div class="grid gap-1.5">
            <Label for="edit-description">Description (optional)</Label>
            <Input
              id="edit-description"
              v-model="editForm.description"
              maxlength="255"
            />
            <InputError :message="editForm.errors.description" />
          </div>
        </form>
        <DialogFooter class="gap-2">
          <Button variant="outline" @click="editing = null">Cancel</Button>
          <Button
            type="submit"
            form="edit-service"
            :disabled="editForm.processing || !editForm.name.trim()"
          >
            <Spinner v-if="editForm.processing" /> Save changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Delete dialog -->
    <Dialog :open="!!deleting" @update:open="(v) => !v && (deleting = null)">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete “{{ deleting?.name }}”?</DialogTitle>
          <DialogDescription v-if="deleting?.members">
            {{ n(deleting.members) }}
            {{ deleting.members === 1 ? "member has" : "members have" }} ticked
            this service, so it cannot be deleted.{{
              deleting.is_active ? " Hide it from the form instead." : ""
            }}
          </DialogDescription>
          <DialogDescription v-else
            >It will be removed from the registration form. This cannot be
            undone.</DialogDescription
          >
        </DialogHeader>
        <DialogFooter class="gap-2">
          <Button variant="outline" @click="deleting = null">Cancel</Button>
          <template v-if="deleting?.members">
            <Button v-if="deleting.is_active" @click="hideInstead"
              ><EyeOff /> Hide from form</Button
            >
          </template>
          <Button v-else variant="destructive" @click="confirmDelete"
            ><Trash2 /> Delete</Button
          >
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit choice dialog -->
    <Dialog
      :open="!!editingChoice"
      @update:open="(v) => !v && (editingChoice = null)"
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Edit choice</DialogTitle>
          <DialogDescription v-if="editingChoice?.members">
            Renaming also updates the {{ n(editingChoice.members) }} member
            records that chose it.
          </DialogDescription>
        </DialogHeader>
        <form
          id="edit-choice"
          class="grid gap-1.5"
          @submit.prevent="saveChoice"
        >
          <Label for="edit-choice-name">Name *</Label>
          <Input
            id="edit-choice-name"
            v-model="choiceEditForm.name"
            maxlength="255"
          />
          <InputError :message="choiceEditForm.errors.name" />
        </form>
        <DialogFooter class="gap-2">
          <Button variant="outline" @click="editingChoice = null"
            >Cancel</Button
          >
          <Button
            type="submit"
            form="edit-choice"
            :disabled="choiceEditForm.processing || !choiceEditForm.name.trim()"
          >
            <Spinner v-if="choiceEditForm.processing" /> Save changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Delete choice dialog -->
    <Dialog
      :open="!!deletingChoice"
      @update:open="(v) => !v && (deletingChoice = null)"
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete “{{ deletingChoice?.name }}”?</DialogTitle>
          <DialogDescription v-if="deletingChoice?.members">
            {{ n(deletingChoice.members) }}
            {{
              deletingChoice.members === 1 ? "member has" : "members have"
            }}
            chosen this, so it cannot be deleted.{{
              deletingChoice.is_active ? " Hide it from the form instead." : ""
            }}
          </DialogDescription>
          <DialogDescription v-else
            >It will be removed from the registration form. This cannot be
            undone.</DialogDescription
          >
        </DialogHeader>
        <DialogFooter class="gap-2">
          <Button variant="outline" @click="deletingChoice = null"
            >Cancel</Button
          >
          <template v-if="deletingChoice?.members">
            <Button v-if="deletingChoice.is_active" @click="hideChoiceInstead"
              ><EyeOff /> Hide from form</Button
            >
          </template>
          <Button v-else variant="destructive" @click="confirmChoiceDelete"
            ><Trash2 /> Delete</Button
          >
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
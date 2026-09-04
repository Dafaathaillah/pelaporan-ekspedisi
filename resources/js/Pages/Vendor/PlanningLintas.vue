<script setup>
import AppShell from "@/Components/AppShell.vue";
import { router, useForm } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({ items: { type: Array, default: () => [] } });
const createForms = reactive({});
const editDates = reactive({});
const today = new Date().toISOString().slice(0, 10);
const formFor = (submission) => {
    if (!createForms[submission.id])
        createForms[submission.id] = useForm({ planned_date: "" });
    return createForms[submission.id];
};
const addDate = (submission) => {
    const form = formFor(submission);
    form.post(`/vendor/planning-lintas/${submission.id}`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
const updateDate = (item) =>
    router.patch(
        `/vendor/planning-lintas/items/${item.id}`,
        { planned_date: editDates[item.id] || item.planned_date },
        { preserveScroll: true },
    );
const removeDate = (item) =>
    router.delete(`/vendor/planning-lintas/items/${item.id}`, {
        preserveScroll: true,
    });
const dateLabel = (value) =>
    value
        ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString(
              "id-ID",
              { weekday: "short", day: "2-digit", month: "short", year: "numeric" },
          )
        : "-";
</script>

<template>
    <AppShell
        title="Planning Lintas"
        subtitle="Atur tanggal operasional setelah Ijin Lintas Released."
        eyebrow="Akun Vendor · Planning"
    >
        <div class="space-y-5">
            <article v-for="submission in items" :key="submission.id" class="panel">
                <header class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <span class="status-badge status-approved">Released</span>
                        <h2 class="mt-2 break-words text-xl font-black">{{ submission.submission_number }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ submission.planning_frequency === "NON_REGULAR" ? "Non-Reguler" : `Reguler · ${submission.planning_frequency}` }}
                            · Berlaku sampai {{ dateLabel(submission.release_expired_at) }}
                        </p>
                        <p class="mt-2 text-xs font-semibold text-slate-600">
                            Unit: {{ submission.units.map((unit) => unit.plate_number).join(", ") || "-" }}
                        </p>
                    </div>
                    <a
                        v-if="submission.release_document_path"
                        :href="`/submissions/${submission.id}/release-document`"
                        target="_blank"
                        class="btn-ghost shrink-0"
                    >
                        Lihat Izin Released
                    </a>
                </header>

                <form class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,260px)_auto] sm:items-start" @submit.prevent="addDate(submission)">
                    <div>
                        <label class="text-sm font-bold">Tambah tanggal planning</label>
                        <input
                            v-model="formFor(submission).planned_date"
                            type="date"
                            class="field"
                            :min="today"
                            :max="String(submission.release_expired_at).slice(0, 10)"
                        />
                        <p class="err">{{ formFor(submission).errors.planned_date }}</p>
                    </div>
                    <button class="btn sm:mt-6" :disabled="formFor(submission).processing || !formFor(submission).planned_date">
                        Tambah Planning
                    </button>
                </form>

                <div class="mt-5 space-y-3">
                    <div
                        v-for="item in submission.planning_items"
                        :key="item.id"
                        class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:grid-cols-[minmax(0,220px)_1fr_auto] sm:items-center"
                    >
                        <input
                            type="date"
                            class="control"
                            :placeholder="String(item.planned_date).slice(0, 10)"
                            :value="editDates[item.id] || String(item.planned_date).slice(0, 10)"
                            :min="today"
                            :max="String(submission.release_expired_at).slice(0, 10)"
                            :disabled="item.is_locked"
                            @input="editDates[item.id] = $event.target.value"
                        />
                        <div>
                            <span
                                class="status-badge"
                                :class="item.is_locked ? 'status-revision' : 'status-approved'"
                            >{{ item.is_locked ? "Terkunci" : "Dapat Diubah" }}</span>
                            <small v-if="item.is_locked && item.locker" class="mt-1 block text-slate-500">
                                Dikunci oleh {{ item.locker.name }}
                            </small>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <button type="button" class="btn-ghost" :disabled="item.is_locked" @click="updateDate(item)">Simpan</button>
                            <button type="button" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="item.is_locked" @click="removeDate(item)">Hapus</button>
                        </div>
                    </div>
                    <p v-if="!submission.planning_items.length" class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                        Belum ada tanggal planning untuk izin ini.
                    </p>
                </div>
            </article>

            <section v-if="!items.length" class="panel py-14 text-center">
                <h2 class="font-black">Belum ada Ijin Lintas aktif</h2>
                <p class="mt-2 text-sm text-slate-500">Planning dapat diisi setelah izin berstatus Released.</p>
            </section>
        </div>
    </AppShell>
</template>

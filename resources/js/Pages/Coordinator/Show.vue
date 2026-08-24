<script setup>
import AppShell from "@/Components/AppShell.vue";
import { useForm, Link, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
const p = defineProps({ submission: Object });
const used=(p.submission.resubmissions||[]).flatMap(x=>x.resubmission_subject_ids||[]);const selected = ref(p.submission.units.filter(x=>!used.includes(x.id)).map((x) => x.id));
const choices = computed(() =>
    p.submission.status === "SUBMITTED"
        ? ["APPROVED", "REVISION", "REJECTED"]
        : p.submission.status === "APPROVED"
          ? ["RELEASED"]
          : ["RELEASED", "REJECTED"].includes(p.submission.status)
            ? ["CLOSED"]
            : [],
);
const labels = {
    APPROVED: "Disetujui",
    REVISION: "Perlu Revisi",
    REJECTED: "Ditolak",
    RELEASED: "Released",
    CLOSED: "Closed",
};
const f = useForm({ status: choices.value[0] || "", coordinator_note: "" });
const send = () =>
    f.patch("/coordinator/submission/" + p.submission.id + "/status");
const verify = (d, status) =>
    router.patch(
        "/coordinator/document/" + d.id + "/verify",
        { status },
        { preserveScroll: true },
    );
const request = (mode) =>
    router.post(
        "/coordinator/submission/" + p.submission.id + "/request-resubmission",
        { mode, subject_ids: selected.value },
        { preserveScroll: true },
    );
const expired = computed(
    () =>
        p.submission.release_expired_at &&
        new Date(p.submission.release_expired_at) <= new Date(),
);
const selectedDocs = computed(() =>
    p.submission.units
        .filter((u) => selected.value.includes(u.id))
        .flatMap((u) => u.documents),
);
const allValid = computed(
    () =>
        selectedDocs.value.length &&
        selectedDocs.value.every((d) => d.verification_status === "VALID"),
);
const date = (v) => (v ? new Date(v).toLocaleDateString("id-ID") : "-");
</script>
<template>
    <AppShell
        title="Review Pengajuan"
        :subtitle="submission.submission_number"
        eyebrow="Koordinator Sarana"
        ><div class="mb-4 flex justify-between">
            <Link
                :href="'/coordinator/submissions/' + submission.type"
                class="btn-ghost"
                >← Kembali</Link
            ><a
                :href="
                    '/submissions/' + submission.id + '/documents/download-all'
                "
                class="btn"
                >↓ Download Semua</a
            >
        </div>
        <div class="grid gap-5 lg:grid-cols-3">
            <section class="panel lg:col-span-2">
                <h2 class="text-2xl font-black">
                    {{ submission.vendor_name }}
                </h2>
                <p class="text-sm text-slate-500">
                    {{ submission.applicant_name }} · {{ submission.phone }}
                </p>
                <div
                    v-if="submission.released_at"
                    class="my-5 grid gap-3 sm:grid-cols-3"
                >
                    <div class="rounded-xl bg-slate-50 p-3">
                        <small>Release</small
                        ><b class="block">{{ date(submission.released_at) }}</b>
                    </div>
                    <div class="rounded-xl bg-red-50 p-3">
                        <small>Expired</small
                        ><b class="block text-red-700">{{
                            date(submission.release_expired_at)
                        }}</b>
                    </div>
                    <div
                        class="rounded-xl p-3"
                        :class="expired ? 'bg-red-50' : 'bg-emerald-50'"
                    >
                        <small>Masa berlaku</small
                        ><b class="block">{{
                            expired ? "Sudah habis" : "Masih berlaku"
                        }}</b>
                    </div>
                </div>
                <article
                    v-for="unit in submission.units"
                    :key="unit.id"
                    class="mb-5 rounded-2xl border p-4"
                >
                    <label class="flex items-center gap-3 text-lg font-black"><input v-model="selected" type="checkbox" :value="unit.id" :disabled="used.includes(unit.id)" class="rounded border-slate-300 text-red-600"/>{{ unit.subject_type==='UNIT' ? 'Unit '+unit.unit_order+' · '+unit.plate_number : 'Orang '+unit.unit_order+' · '+unit.person_name }}<small v-if="used.includes(unit.id)" class="text-emerald-600">Sudah diajukan ulang</small></label>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div
                            v-for="d in unit.documents"
                            :key="d.id"
                            class="rounded-xl border p-3"
                        >
                            <div class="flex justify-between gap-2">
                                <div>
                                    <b>{{ d.document_name }}</b
                                    ><small
                                        class="block"
                                        :class="
                                            d.verification_status === 'VALID'
                                                ? 'text-emerald-600'
                                                : d.verification_status ===
                                                    'INVALID'
                                                  ? 'text-red-600'
                                                  : 'text-amber-600'
                                        "
                                        >{{ d.verification_status }}</small
                                    >
                                </div>
                                <a
                                    :href="'/documents/' + d.id"
                                    target="_blank"
                                    class="font-black text-red-600"
                                    >Lihat/Download</a
                                >
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700"
                                    @click="verify(d, 'VALID')"
                                >
                                    Sesuai</button
                                ><button
                                    type="button"
                                    class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-700"
                                    @click="verify(d, 'INVALID')"
                                >
                                    Tidak Sesuai
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
                <div
                    v-if="!submission.units.length"
                    class="grid gap-3 md:grid-cols-2"
                >
                    <div
                        v-for="d in submission.documents"
                        class="rounded-xl border p-4"
                    >
                        <b>{{ d.document_name }}</b
                        ><a
                            :href="'/documents/' + d.id"
                            target="_blank"
                            class="float-right font-bold text-red-600"
                            >Download</a
                        >
                    </div>
                </div>
            </section>
            <aside class="space-y-5">
                <form class="panel" @submit.prevent="send">
                    <h3 class="font-black">STATUS PENGAJUAN</h3>
                    <template v-if="choices.length"
                        ><select
                            v-model="f.status"
                            class="field select-control"
                        >
                            <option v-for="s in choices" :value="s">
                                {{ labels[s] }}
                            </option></select
                        ><textarea
                            v-model="f.coordinator_note"
                            class="field"
                            rows="3"
                            placeholder="Catatan"
                        /><button class="btn mt-3 w-full">
                            Simpan Status
                        </button></template
                    >
                </form>
                <section
                    v-if="
                        expired &&
                        submission.units.length &&
                        !submission.resubmission_requested_at
                    "
                    class="panel"
                >
                    <h3 class="font-black">PENGAJUAN ULANG</h3>
                    <p class="mt-2 text-xs text-slate-500">
                        Centang hanya orang/unit yang akan diajukan ulang. Periksa dokumen subjek terpilih sebelum menentukan tindakan.
                    </p>
                    <button
                        :disabled="!allValid || !selected.length"
                        class="btn mt-4 w-full"
                        @click="request('USE_EXISTING')"
                    >
                        Dokumen Sesuai · Ajukan</button
                    ><button
                        class="btn-ghost mt-3 w-full"
                        @click="request('REQUEST_HEALTH')"
                    >
                        Minta Dokumen Kesehatan Baru
                    </button>
                    <p v-if="!allValid" class="mt-2 text-xs text-amber-600">
                        Validasi seluruh dokumen sebagai “Sesuai” untuk
                        pengajuan langsung.
                    </p>
                </section>
            </aside>
        </div></AppShell
    >
</template>

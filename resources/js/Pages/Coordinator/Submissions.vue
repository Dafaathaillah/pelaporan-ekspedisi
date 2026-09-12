<script setup>
import AppShell from "@/Components/AppShell.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import SortButton from "@/Components/SortButton.vue";
import { expeditionVehicleLabel } from "@/Utils/expeditionRequest";
import { Link, router, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
const p = defineProps({
    type: String,
    items: Object,
    stats: Object,
    filters: Object,
    reviewBase: { type: String, default: "/coordinator" },
    pageRole: { type: String, default: "coordinator" },
    internalItems: { type: Array, default: () => [] },
});
const listUrl = computed(() =>
    p.reviewBase === "/purchasing"
        ? "/purchasing/vendor-submissions/IJIN_LINTAS"
        : "/coordinator/submissions/" + p.type,
);
const itemUrl = (id) =>
    p.reviewBase === "/purchasing"
        ? "/purchasing/vendor-submission/" + id
        : "/coordinator/submission/" + id;
const f = reactive({
    search: "",
    date_from: "",
    date_to: "",
    status: "",
    sort: "submitted_at",
    direction: "desc",
    per_page: 25,
    ...p.filters,
});
const labels = {
    VISITOR: "Visitor",
    WASKAT: "Waskat",
    PERMIT_VENDOR_NON_SIMPER: "Permit Vendor Non Simper",
    IJIN_LINTAS: "Ijin Lintas",
};
const statusLabels = {
    SUBMITTED: "Diajukan",
    APPROVED: "Disetujui",
    REVISION: "Perlu Revisi",
    REJECTED: "Ditolak",
    RELEASED: "Released",
    CLOSED: "Closed",
};
const dialog = ref(null);
const internalDialog = ref(false);
const statusForm = useForm({ status: "", coordinator_note: "" });
const internalForm = useForm({ title: "", escort_date: "", unit_count: 1, vehicle_plates: "" });
const internalEndpoint = computed(() => `${p.reviewBase}/internal-ijin-lintas`);
const saveInternal = () => internalForm.post(internalEndpoint.value, {
    preserveScroll: true,
    onSuccess: () => { internalDialog.value = false; internalForm.reset(); },
});
const load = () =>
    router.get(listUrl.value, f, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const reset = () =>
    router.get(
        listUrl.value,
        {},
        { preserveScroll: true, replace: true },
    );
const sort = (c) => {
    f.direction = f.sort === c && f.direction === "asc" ? "desc" : "asc";
    f.sort = c;
    load();
};
const size = (v) => {
    f.per_page = v;
    load();
};
const date = (v) => (v ? new Date(v).toLocaleDateString("id-ID") : "-");
const detail = (id) => itemUrl(id);
const documents = (id) => "/submissions/" + id + "/documents/download-all";
const choices = (row) =>
    row.status === "SUBMITTED"
        ? ["APPROVED", "REVISION", "REJECTED"]
        : row.status === "APPROVED"
          ? ["RELEASED"]
          : ["RELEASED", "REJECTED"].includes(row.status)
            ? ["CLOSED"]
            : [];
const openStatus = (row, value) => {
    if (!value) return;
    dialog.value = row;
    statusForm.status = value;
    statusForm.coordinator_note = row.coordinator_note || "";
};
const closeStatus = () => {
    dialog.value = null;
    statusForm.reset();
};
const saveStatus = () =>
    statusForm.patch(itemUrl(dialog.value.id) + "/status", {
        preserveScroll: true,
        onSuccess: closeStatus,
    });
const exportQuery = computed(() =>
    new URLSearchParams(
        Object.fromEntries(
            Object.entries(f).filter(
                ([key, value]) =>
                    value && !["sort", "direction", "per_page"].includes(key),
            ),
        ),
    ).toString(),
);
const validity = (x) => {
    if (!x.released_at)
        return { label: "Belum dirilis", tone: "text-slate-500" };
    const end = new Date(x.release_expired_at);
    const days = Math.max(0, Math.ceil((end - Date.now()) / 86400000));
    return end <= new Date()
        ? { label: "Masa berlaku habis", tone: "text-red-600" }
        : {
              label: `Berlaku ${days} hari lagi`,
              tone: days <= 2 ? "text-amber-600" : "text-emerald-600",
          };
};
const canRequest = (x) =>
    ["VISITOR", "WASKAT", "PERMIT_VENDOR_NON_SIMPER", "IJIN_LINTAS"].includes(
        x.type,
    ) &&
    x.release_expired_at &&
    new Date(x.release_expired_at) <= new Date() &&
    !x.resubmission_requested_at;
const subjectLabel = computed(() =>
    p.type === "IJIN_LINTAS" ? "unit" : "orang",
);
const renewalDocument = computed(
    () =>
        ({
            VISITOR: "Surat Tugas",
            WASKAT: "Surat Sehat",
            PERMIT_VENDOR_NON_SIMPER: "MCU",
            IJIN_LINTAS: "Surat Sehat",
        })[p.type],
);
const requestAgain = (x) =>
    router.post(
        itemUrl(x.id) + "/request-resubmission",
        { mode: "REQUEST_HEALTH" },
        { preserveScroll: true },
    );
</script>
<template>
    <AppShell
        :title="pageRole === 'purchasing_admin' ? 'Review Ijin Lintas' : 'Monitoring Koordinator Sarana'"
        subtitle="Pelaporan Ekspedisi & Pengajuan Dokumen Vendor"
        :eyebrow="pageRole === 'purchasing_admin' ? 'Admin Purchasing · Review Dokumen' : 'Koordinator Sarana · Monitoring Pengajuan'"
        ><template #hero-title>Pengajuan {{ labels[type] }}</template
        ><template #hero-copy
            >Periksa dokumen, ubah status, dan unduh seluruh data yang sudah
            diajukan.</template
        >
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="metric-card">
                <p>Total Pengajuan</p>
                <strong>{{ stats.total }}</strong
                ><span>Pada menu terpilih</span>
            </article>
            <article class="metric-card">
                <p>Baru Diajukan</p>
                <strong>{{ stats.submitted }}</strong
                ><span>Belum diproses</span>
            </article>
            <article class="metric-card">
                <p>Disetujui</p>
                <strong>{{ stats.approved }}</strong
                ><span>Dokumen dinyatakan lengkap</span>
            </article>
            <article class="metric-card">
                <p>Perlu Revisi</p>
                <strong>{{ stats.revision }}</strong
                ><span>Menunggu perbaikan vendor</span>
            </article>
        </section>
        <section v-if="type === 'IJIN_LINTAS'" class="panel my-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="panel-title">Izin Lintas Internal</h2><p class="panel-copy">Buat izin internal dan jadwalkan satu card untuk seluruh unit.</p></div>
            <button type="button" class="btn shrink-0" @click="internalDialog = true">Buat Izin Lintas</button>
        </section>
        <section v-if="type === 'IJIN_LINTAS' && internalItems.length" class="panel my-5">
            <h2 class="panel-title">Daftar Izin Lintas Internal</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="item in internalItems" :key="item.id" class="rounded-2xl border border-sky-200 bg-sky-50/50 p-4">
                    <span class="text-[10px] font-black uppercase tracking-wider text-sky-700">Internal</span>
                    <h3 class="mt-1 font-black text-slate-900">{{ item.title }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ date(item.escort_date) }} · {{ item.unit_count }} unit</p>
                    <p class="mt-1 break-words text-sm font-semibold text-slate-800">{{ item.vehicle_plates.join(', ') }}</p>
                </article>
            </div>
        </section>
        <form class="panel my-5" @submit.prevent="load">
            <h2 class="panel-title">Filter Pengajuan</h2>
            <p class="panel-copy">
                Pencarian, sorting, dan pagination diproses server
            </p>
            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <input
                    v-model="f.search"
                    class="control"
                    placeholder="Cari vendor, pemohon, ID..."
                /><input
                    v-model="f.date_from"
                    type="date"
                    class="control"
                /><input
                    v-model="f.date_to"
                    type="date"
                    class="control"
                /><select v-model="f.status" class="control select-control">
                    <option value="">Semua status</option>
                    <option
                        v-for="(label, value) in statusLabels"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </select>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button class="btn">Terapkan Filter</button
                ><button type="button" class="btn-ghost" @click="reset">
                    Reset</button
                ><button
                    type="button"
                    class="btn-ghost ml-auto"
                    @click="
                        router.reload({
                            only: ['items', 'stats'],
                            preserveScroll: true,
                        })
                    "
                >
                    Muat Ulang</button
                ><a v-if="pageRole !== 'purchasing_admin'"
                    :href="
                        '/coordinator/submissions/' +
                        type +
                        '/export?' +
                        exportQuery
                    "
                    class="btn-ghost"
                    >↓ Download Semua Data</a
                >
            </div>
        </form>
        <section class="panel overflow-hidden p-0">
            <div class="flex justify-between border-b px-5 py-4">
                <div>
                    <h2 class="panel-title">Monitoring {{ type }}</h2>
                    <p class="panel-copy">DataTable server-side</p>
                </div>
                <span class="text-xs text-slate-400"
                    >{{ items.total }} data</span
                >
            </div>
            <div class="overflow-x-auto">
                <table class="data-table submission-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>
                                <SortButton
                                    label="Tanggal"
                                    column="submitted_at"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="ID Pengajuan"
                                    column="submission_number"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>Keterangan</th>
                            <th>Request Ekspedisi</th>
                            <th>
                                <SortButton
                                    label="Vendor"
                                    column="vendor_name"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Pemohon/PIC"
                                    column="applicant_name"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Nomor HP"
                                    column="phone"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>Dokumen</th>
                            <th>
                                <SortButton
                                    label="Status"
                                    column="status"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>Catatan</th>
                            <th>Aksi</th>
                            <th>Release</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(x, i) in items.data" :key="x.id">
                            <td>{{ items.from + i }}</td>
                            <td>{{ date(x.submitted_at) }}</td>
                            <td class="id-cell">
                                <strong>{{ x.submission_number }}</strong>
                            </td>
                            <td class="description-cell table-wrap-cell">
                                {{ x.description || "-" }}
                            </td>
                            <td class="table-wrap-cell">
                                <template v-if="x.expedition_request">
                                    <b class="text-red-700">{{
                                        x.expedition_request.request_number
                                    }}</b>
                                    <small
                                        >{{
                                            expeditionVehicleLabel(
                                                x.expedition_request
                                                    .expedition_type,
                                            )
                                        }}
                                        ·
                                        {{ x.expedition_request.route_from }} →
                                        {{
                                            x.expedition_request.route_to
                                        }}</small
                                    >
                                    <small class="mt-1 block text-slate-500">
                                        PR:
                                        {{
                                            x.expedition_request.pr_number ||
                                            "Belum rilis"
                                        }}
                                        · PO:
                                        {{
                                            x.expedition_request.po_number ||
                                            "Belum rilis"
                                        }}
                                    </small>
                                </template>
                                <span v-else>-</span>
                            </td>
                            <td class="table-wrap-cell">{{ x.vendor_name }}</td>
                            <td class="table-wrap-cell">
                                {{ x.applicant_name }}
                            </td>
                            <td>
                                <a
                                    :href="'tel:' + x.phone"
                                    class="text-blue-600 underline"
                                    >{{ x.phone }}</a
                                >
                            </td>
                            <td>
                                <a
                                    :href="documents(x.id)"
                                    class="font-bold text-red-600"
                                    >↓ {{ x.documents_count }} file</a
                                >
                            </td>
                            <td class="status-cell">
                                <div>
                                    <span
                                        class="status-badge mb-2"
                                        :class="
                                            'status-' + x.status.toLowerCase()
                                        "
                                        >{{ statusLabels[x.status] }}</span
                                    ><select
                                        v-if="choices(x).length"
                                        class="status-select"
                                        value=""
                                        @change="
                                            openStatus(x, $event.target.value);
                                            $event.target.value = '';
                                        "
                                    >
                                        <option value="">Ubah status...</option>
                                        <option
                                            v-for="status in choices(x)"
                                            :value="status"
                                        >
                                            {{ statusLabels[status] }}
                                        </option></select
                                    ><span
                                        v-else
                                        class="block text-[10px] text-slate-400"
                                        >Status final</span
                                    >
                                </div>
                            </td>
                            <td class="note-cell table-wrap-cell">
                                {{ x.coordinator_note || "-" }}
                            </td>
                            <td>
                                <Link
                                    :href="detail(x.id)"
                                    class="font-bold text-red-600"
                                    >Review</Link
                                >
                            </td>
                            <td>{{ date(x.released_at) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div
                    v-if="!items.data.length"
                    class="p-16 text-center text-sm text-slate-500"
                >
                    Data tidak ditemukan.
                </div>
            </div>
            <DataTableFooter
                :paginator="items"
                :per-page="f.per_page"
                @update:per-page="size"
            />
        </section>
        <Teleport to="body"
            ><div
                v-if="dialog"
                class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm"
                @click.self="closeStatus"
            >
                <form
                    class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl"
                    @submit.prevent="saveStatus"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-[10px] font-black uppercase tracking-widest text-red-600"
                            >
                                Perbarui Status
                            </p>
                            <h3 class="mt-1 text-xl font-black">
                                {{ dialog.submission_number }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ dialog.vendor_name }} ·
                                {{ dialog.applicant_name }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg bg-slate-100 px-3 py-2 text-sm"
                            @click="closeStatus"
                        >
                            ✕
                        </button>
                    </div>
                    <label class="mt-6 block text-sm font-bold"
                        >Status baru<select
                            v-model="statusForm.status"
                            class="field select-control"
                        >
                            <option
                                v-for="status in choices(dialog)"
                                :value="status"
                            >
                                {{ statusLabels[status] }}
                            </option>
                        </select></label
                    ><label class="mt-4 block text-sm font-bold"
                        >Catatan Koordinator
                        <span
                            v-if="
                                ['REVISION', 'REJECTED'].includes(
                                    statusForm.status,
                                )
                            "
                            class="text-red-600"
                            >*</span
                        ><textarea
                            v-model="statusForm.coordinator_note"
                            class="field"
                            rows="4"
                            placeholder="Tuliskan catatan yang jelas untuk vendor"
                        />
                    </label>
                    <p class="err">{{ statusForm.errors.coordinator_note }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="btn-ghost"
                            @click="closeStatus"
                        >
                            Batal</button
                        ><button :disabled="statusForm.processing" class="btn">
                            {{
                                statusForm.processing
                                    ? "Menyimpan..."
                                    : "Simpan Status"
                            }}
                        </button>
                    </div>
                </form>
            </div></Teleport
        >
        <section
            v-if="
                [
                    'VISITOR',
                    'WASKAT',
                    'PERMIT_VENDOR_NON_SIMPER',
                    'IJIN_LINTAS',
                ].includes(type)
            "
            class="panel mt-5 overflow-hidden"
        >
            <div
                class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-600"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M8 2v3M16 2v3M3.5 9h17M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                            />
                            <path d="m9 15 2 2 4-5" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="panel-title">
                            Masa Berlaku & Pengajuan Ulang
                        </h2>
                        <p class="panel-copy mt-1 max-w-2xl">
                            Release berlaku selama 7 hari. Periksa
                            {{ subjectLabel }} dan validasi
                            {{ renewalDocument }} sebelum membuat pengajuan
                            ulang.
                        </p>
                    </div>
                </div>
                <span
                    class="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600"
                    >{{
                        items.data.filter((row) => row.released_at).length
                    }}
                    pengajuan</span
                >
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <article
                    v-for="x in items.data.filter((row) => row.released_at)"
                    :key="x.id"
                    class="group flex min-h-[230px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-red-200 hover:shadow-lg hover:shadow-red-100/60"
                >
                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start justify-between gap-4">
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-black"
                                :class="
                                    validity(x).tone.includes('red')
                                        ? 'bg-red-50 text-red-600'
                                        : validity(x).tone.includes('amber')
                                          ? 'bg-amber-50 text-amber-700'
                                          : 'bg-emerald-50 text-emerald-700'
                                "
                                >{{ validity(x).label }}</span
                            >
                            <span class="text-xs font-bold text-slate-400"
                                >{{ x.units_count }} {{ subjectLabel }}</span
                            >
                        </div>
                        <h3
                            class="mt-4 break-words text-base font-black leading-snug text-slate-900"
                        >
                            {{ x.submission_number }}
                        </h3>
                        <p class="mt-1 text-sm font-semibold text-slate-600">
                            {{ x.vendor_name }}
                        </p>
                        <div
                            class="mt-4 grid grid-cols-[1fr_auto_1fr] items-center gap-3 rounded-xl bg-slate-50 p-3"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400"
                                >
                                    Release
                                </p>
                                <p
                                    class="mt-1 text-xs font-black text-slate-700"
                                >
                                    {{ date(x.released_at) }}
                                </p>
                            </div>
                            <svg
                                class="h-4 w-4 text-slate-300"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M5 12h14m-4-4 4 4-4 4" />
                            </svg>
                            <div class="text-right">
                                <p
                                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400"
                                >
                                    Berakhir
                                </p>
                                <p class="mt-1 text-xs font-black text-red-600">
                                    {{ date(x.release_expired_at) }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="x.resubmission_requested_at"
                            class="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs font-bold leading-relaxed text-amber-800"
                        >
                            <span
                                class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-amber-500"
                            ></span>
                            Menunggu vendor memperbarui {{ renewalDocument }}.
                        </div>
                    </div>
                    <div class="border-t border-slate-100 bg-slate-50/60 p-3">
                        <Link
                            v-if="canRequest(x)"
                            :href="detail(x.id)"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-100"
                        >
                            Review & Pilih
                            {{ subjectLabel === "unit" ? "Unit" : "Orang" }}
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </Link>
                        <Link
                            v-else
                            :href="detail(x.id)"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-red-200 hover:text-red-600"
                            >Lihat Detail</Link
                        >
                    </div>
                    <!--
                            <p class="mt-1 text-xs text-slate-500">
                                Release {{ date(x.released_at) }} · Expired
                                {{ date(x.release_expired_at) }}
                            </p>
                        </div>
                        <span
                            class="text-xs font-black"
                            :class="validity(x).tone"
                            >{{ validity(x).label }}</span
                        >
                    </div>
                    <p
                        v-if="x.resubmission_requested_at"
                        class="mt-3 rounded-xl bg-amber-50 p-3 text-xs font-bold text-amber-700"
                    >
                        Menunggu vendor memperbarui Surat Sehat.
                    </p>
                    <Link
                        v-else-if="canRequest(x)"
                        :href="detail(x.id)"
                        class="btn mt-3 w-full"
                    >
                        Review & Pilih Orang/Unit
                    </Link>
                    -->
                </article>
            </div>
        </section>
        <div v-if="internalDialog" class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="internalDialog = false">
            <form class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl" @submit.prevent="saveInternal">
                <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-widest text-sky-700">Izin Lintas Internal</p><h3 class="mt-1 text-xl font-black">Buat Jadwal Escort</h3></div><button type="button" class="rounded-lg bg-slate-100 px-3 py-2" @click="internalDialog = false">×</button></div>
                <div class="mt-5 space-y-4">
                    <label class="block text-sm font-bold">Judul Escort<input v-model="internalForm.title" class="field" maxlength="150" required /><p class="err">{{ internalForm.errors.title }}</p></label>
                    <label class="block text-sm font-bold">Tanggal Escort<input v-model="internalForm.escort_date" type="date" class="field" required /><p class="err">{{ internalForm.errors.escort_date }}</p></label>
                    <label class="block text-sm font-bold">Jumlah Unit<input v-model.number="internalForm.unit_count" type="number" min="1" max="100" class="field" required /><p class="err">{{ internalForm.errors.unit_count }}</p></label>
                    <label class="block text-sm font-bold">Plat Nomor<textarea v-model="internalForm.vehicle_plates" class="field" rows="3" placeholder="DA 1, DA 2" required></textarea><span class="mt-1 block text-xs font-normal text-slate-500">Pisahkan setiap plat dengan koma.</span><p class="err">{{ internalForm.errors.vehicle_plates || internalForm.errors.plates }}</p></label>
                </div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2 font-bold text-slate-600" @click="internalDialog = false">Batal</button><button class="btn" :disabled="internalForm.processing">Simpan Izin Lintas</button></div>
            </form>
        </div>
    </AppShell
>
</template>

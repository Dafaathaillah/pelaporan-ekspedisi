<script setup>
import AppShell from "@/Components/AppShell.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import SortButton from "@/Components/SortButton.vue";
import { expeditionVehicleLabel } from "@/Utils/expeditionRequest";
import { router, useForm } from "@inertiajs/vue3";
import { computed, reactive } from "vue";

const props = defineProps({
    items: Object,
    vendors: Array,
    filters: Object,
    canCreate: Boolean,
    pageRole: String,
    endpoint: String,
});
const form = useForm({
    vendor_company_id: "",
    expedition_type: "",
    route_from: "",
    route_to: "",
    description: "",
});
const query = reactive({
    search: props.filters.search || "",
    status: props.filters.status || "",
    sort: props.filters.sort || "created_at",
    direction: props.filters.direction || "desc",
    per_page: Number(props.filters.per_page || 10),
});
const procurementDrafts = reactive({});
const procurementDraft = (item) => {
    if (!procurementDrafts[item.id]) {
        procurementDrafts[item.id] = {
            pr_number: item.pr_number || "",
            po_number: item.po_number || "",
        };
    }
    return procurementDrafts[item.id];
};
const saveProcurement = (item) => {
    const base = isPurchasing.value
        ? "/purchasing/requests"
        : "/coordinator/expedition-requests";
    router.patch(`${base}/${item.id}/procurement`, procurementDraft(item), {
        preserveScroll: true,
        preserveState: true,
    });
};
const vendorOptions = computed(() =>
    props.vendors.map((vendor) => ({
        value: vendor.id,
        label: `${vendor.abbreviation} · ${vendor.name}`,
        keywords: `${vendor.name} ${vendor.abbreviation}`,
    })),
);
const typeOptions = [
    { value: "FUSO", label: "Fuso" },
    { value: "COLT_DIESEL", label: "Colt Diesel" },
    { value: "TRAILER", label: "Trailer" },
    { value: "DUMP_TRUCK", label: "Dump Truck" },
    { value: "PICKUP", label: "Pickup" },
    { value: "LIGHT_VEHICLE", label: "Light Vehicle" },
    { value: "LAINNYA", label: "Lainnya" },
];
const statusOptions = [
    { value: "", label: "Semua status" },
    { value: "OPEN", label: "Menunggu Ijin Lintas" },
    { value: "USED", label: "Sudah Diproses Vendor" },
    { value: "CLOSED", label: "Selesai" },
];
const load = () =>
    router.get(props.endpoint, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const sort = (column) => {
    query.direction =
        query.sort === column && query.direction === "asc" ? "desc" : "asc";
    query.sort = column;
    load();
};
const submit = () =>
    form.post(props.endpoint, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const date = (value) =>
    new Date(value).toLocaleString("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    });
const isPurchasing = computed(() => props.pageRole === "purchasing_admin");
const eyebrow = computed(() =>
    props.pageRole === "developer"
        ? "Developer Sistem"
        : isPurchasing.value
          ? "Admin Purchasing"
          : "Koordinator Sarana",
);
</script>

<template>
    <AppShell
        title="Request Ekspedisi"
        :subtitle="
            isPurchasing
                ? 'Kirim kebutuhan ekspedisi langsung kepada vendor tujuan.'
                : 'Buat dan pantau seluruh request ekspedisi serta tindak lanjut vendor dalam satu site.'
        "
        :eyebrow="eyebrow"
    >
        <div
            class="grid gap-5"
            :class="{ 'xl:grid-cols-[.85fr_1.15fr]': canCreate }"
        >
            <form v-if="canCreate" class="panel h-fit" @submit.prevent="submit">
                <div class="mb-5 flex items-start gap-3">
                    <div
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-red-50 text-red-600"
                    >
                        ⇄
                    </div>
                    <div>
                        <h2 class="panel-title">Buat Request Baru</h2>
                        <p class="panel-copy">
                            Nomor request dibuat otomatis dan dikirim realtime
                            ke vendor.
                        </p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-bold">Vendor tujuan</label>
                        <SearchableSelect
                            v-model="form.vendor_company_id"
                            :options="vendorOptions"
                            class="mt-2"
                            placeholder="Pilih perusahaan vendor"
                            search-placeholder="Cari nama atau singkatan vendor..."
                        />
                        <p class="err">{{ form.errors.vendor_company_id }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-bold">Jenis kendaraan</label>
                        <SearchableSelect
                            v-model="form.expedition_type"
                            :options="typeOptions"
                            class="mt-2"
                            placeholder="Pilih kendaraan"
                            search-placeholder="Cari jenis kendaraan..."
                        />
                        <p class="err">{{ form.errors.expedition_type }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-bold"
                            >Rute dari<input
                                v-model="form.route_from"
                                class="field"
                                placeholder="Lokasi asal"
                            /><span class="err">{{
                                form.errors.route_from
                            }}</span></label
                        >
                        <label class="text-sm font-bold"
                            >Rute ke<input
                                v-model="form.route_to"
                                class="field"
                                placeholder="Lokasi tujuan"
                            /><span class="err">{{
                                form.errors.route_to
                            }}</span></label
                        >
                    </div>
                    <label class="block text-sm font-bold"
                        >Keterangan<textarea
                            v-model="form.description"
                            class="field"
                            rows="3"
                            placeholder="Muatan, jadwal, PIC, atau informasi tambahan..."
                        ></textarea>
                    </label>
                </div>
                <button class="btn mt-5 w-full" :disabled="form.processing">
                    {{
                        form.processing
                            ? "MENGIRIM REQUEST..."
                            : "KIRIM REQUEST KE VENDOR"
                    }}
                </button>
            </form>

            <section class="panel overflow-hidden p-0">
                <div class="border-b border-slate-100 p-5">
                    <h2 class="panel-title">
                        {{
                            isPurchasing
                                ? "Riwayat Request Saya"
                                : "Seluruh Request Ekspedisi"
                        }}
                    </h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_190px_auto]">
                        <input
                            v-model="query.search"
                            class="control"
                            placeholder="Cari nomor, vendor, atau rute..."
                            @keyup.enter="load"
                        />
                        <SearchableSelect
                            v-model="query.status"
                            :options="statusOptions"
                            placeholder="Semua status"
                        />
                        <button class="btn" @click="load">Terapkan</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table min-w-[1250px]">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>
                                    <SortButton
                                        label="Nomor Request"
                                        column="request_number"
                                        :sort="query.sort"
                                        :direction="query.direction"
                                        @sort="sort"
                                    />
                                </th>
                                <th>Vendor</th>
                                <th>Nomor PR</th>
                                <th>Nomor PO</th>
                                <th>Jenis Kendaraan</th>
                                <th>Rute</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in items.data"
                                :key="item.id"
                            >
                                <td>{{ items.from + index }}</td>
                                <td>
                                    <b>{{ item.request_number }}</b
                                    ><small>{{ date(item.created_at) }}</small>
                                </td>
                                <td>
                                    <b>{{ item.vendor_company.name }}</b
                                    ><small>{{
                                        item.vendor_company.abbreviation
                                    }}</small>
                                </td>
                                <td>
                                    <input
                                        v-model="
                                            procurementDraft(item).pr_number
                                        "
                                        class="control min-w-40 py-2"
                                        placeholder="Belum rilis"
                                        @keyup.enter="saveProcurement(item)"
                                    />
                                </td>
                                <td>
                                    <div
                                        class="flex min-w-56 items-center gap-2"
                                    >
                                        <input
                                            v-model="
                                                procurementDraft(item).po_number
                                            "
                                            class="control py-2"
                                            placeholder="Belum rilis"
                                            @keyup.enter="saveProcurement(item)"
                                        />
                                        <button
                                            type="button"
                                            class="shrink-0 rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-700 transition hover:bg-red-600 hover:text-white"
                                            @click="saveProcurement(item)"
                                        >
                                            Simpan
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    {{
                                        expeditionVehicleLabel(
                                            item.expedition_type,
                                        )
                                    }}
                                </td>
                                <td class="table-wrap-cell">
                                    <b>{{ item.route_from }}</b>
                                    <span class="mx-2 text-red-500">→</span>
                                    <b>{{ item.route_to }}</b>
                                </td>
                                <td>
                                    <span
                                        class="status-badge"
                                        :class="
                                            item.status === 'OPEN'
                                                ? 'status-revision'
                                                : 'status-approved'
                                        "
                                        >{{
                                            item.status === "OPEN"
                                                ? "Menunggu Vendor"
                                                : "Sudah Diproses"
                                        }}</span
                                    ><small
                                        >{{
                                            item.submissions_count
                                        }}
                                        pengajuan</small
                                    >
                                </td>
                            </tr>
                            <tr v-if="!items.data.length">
                                <td
                                    colspan="8"
                                    class="py-14 text-center text-slate-500"
                                >
                                    Belum ada request ekspedisi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTableFooter
                    :paginator="items"
                    :per-page="query.per_page"
                    @update:per-page="
                        (value) => {
                            query.per_page = value;
                            load();
                        }
                    "
                />
            </section>
        </div>
    </AppShell>
</template>

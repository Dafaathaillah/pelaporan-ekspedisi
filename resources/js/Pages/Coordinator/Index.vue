<script setup>
import AppShell from "@/Components/AppShell.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import Modal from "@/Components/Modal.vue";
import SortButton from "@/Components/SortButton.vue";
import { router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
const p = defineProps({
    reports: Object,
    summary: Object,
    hourly: Array,
    filters: Object,
    options: Object,
});
const f = reactive({
    search: "",
    date_from: "",
    date_to: "",
    company: "",
    vehicle_type: "",
    vendor: "",
    destination: "",
    sort: "arrived_at",
    direction: "desc",
    per_page: 25,
    ...p.filters,
});
const cards = computed(() => [
    ["Kedatangan Hari Ini", p.summary.today, "Total laporan hari ini"],
    ["Bulan Ini", p.summary.month, "Akumulasi bulan berjalan"],
    ["Total Laporan", p.summary.total, "Seluruh data tersimpan"],
    ["Ekspedisi Hari Ini", p.summary.companies, "Perusahaan berbeda"],
]);
const maxValue = computed(() => Math.max(1, ...p.hourly.map((x) => x.total)));
const chartPoints = computed(() =>
    p.hourly.map((x, i) => ({
        x: 24 + i * (552 / 23),
        y: 178 - (x.total / maxValue.value) * 132,
        ...x,
    })),
);
const linePath = computed(() =>
    chartPoints.value
        .map((x, i) => (i ? "L" : "M") + x.x + " " + x.y)
        .join(" "),
);
const areaPath = computed(() => linePath.value + " L 576 178 L 24 178 Z");
const activeHours = computed(() => p.hourly.filter((x) => x.total > 0));
const peak = computed(() =>
    activeHours.value.reduce(
        (best, x) => (x.total > (best?.total || 0) ? x : best),
        null,
    ),
);
const average = computed(() =>
    activeHours.value.length
        ? (p.summary.today / activeHours.value.length).toFixed(1)
        : "0",
);
const activityRange = computed(() =>
    activeHours.value.length
        ? activeHours.value[0].hour +
          ".00 – " +
          activeHours.value.at(-1).hour +
          ".59"
        : "Belum ada aktivitas",
);
const busiest = computed(() =>
    [...activeHours.value].sort((a, b) => b.total - a.total).slice(0, 3),
);
const activePoint = ref(null);
const selectedEscort = ref(null);
const selectPoint = (point) => (activePoint.value = point);
const load = () =>
    router.get("/coordinator", f, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const reset = () =>
    router.get("/coordinator", {}, { preserveScroll: true, replace: true });
const reload = () =>
    router.reload({
        only: ["reports", "summary", "hourly", "options"],
        preserveScroll: true,
    });
const sort = (column) => {
    f.direction = f.sort === column && f.direction === "asc" ? "desc" : "asc";
    f.sort = column;
    load();
};
const size = (value) => {
    f.per_page = value;
    load();
};
const query = computed(() =>
    new URLSearchParams(
        Object.fromEntries(Object.entries(f).filter(([, v]) => v)),
    ).toString(),
);
const date = (v) =>
    new Date(v).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    });
const time = (v) => new Date(v).toLocaleTimeString("id-ID", { hour12: false });
const escortStatusLabel = (value) =>
    ({
        WAITING: "Menunggu Driver",
        PROCESS: "Sedang Di-escort",
        CLOSED: "Escort Selesai",
    })[value] || value;
const escortStatusClass = (value) =>
    ({
        WAITING: "bg-blue-50 text-blue-700",
        PROCESS: "bg-amber-50 text-amber-700",
        CLOSED: "bg-emerald-50 text-emerald-700",
    })[value] || "bg-slate-100 text-slate-600";
const escortTime = (value) =>
    value
        ? new Date(value).toLocaleTimeString("id-ID", {
              hour: "2-digit",
              minute: "2-digit",
              second: "2-digit",
              hour12: false,
          }) + " WITA"
        : "Belum dimulai";
const healthStatusClass = (status) =>
    ({
        FIT: "bg-emerald-50 text-emerald-700",
        FIT_WITH_NOTE: "bg-blue-50 text-blue-700",
        PENDING: "bg-amber-50 text-amber-700",
        UNFIT_PENDING: "bg-orange-50 text-orange-700",
        UNFIT: "bg-red-50 text-red-700",
    })[status] || "bg-slate-100 text-slate-600";
</script>
<template>
    <AppShell
        title="Monitoring Koordinator Sarana"
        subtitle="Pelaporan Ekspedisi & Pengajuan Dokumen Vendor"
        eyebrow="Koordinator Sarana"
        ><template #hero-title>Dashboard Kedatangan</template
        ><template #hero-copy
            >Informasi laporan kedatangan ekspedisi di Underpass.</template
        >
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article v-for="x in cards" :key="x[0]" class="metric-card">
                <p>{{ x[0] }}</p>
                <strong>{{ x[1] }}</strong
                ><span>{{ x[2] }}</span>
            </article>
            <article class="metric-card">
                <p>Kedatangan Terakhir</p>
                <strong class="text-2xl">{{
                    summary.latest?.time || "--:--:--"
                }}</strong
                ><span>{{
                    summary.latest?.company || "Belum ada kedatangan"
                }}</span>
            </article>
        </section>
        <section class="mt-5 grid gap-4 2xl:grid-cols-[1.05fr_1.35fr]">
            <article class="panel overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="panel-title">Pola Kedatangan Hari Ini</h2>
                        <p class="panel-copy">
                            Sentuh atau arahkan kursor untuk melihat detail
                            setiap jam
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-red-50 px-3 py-1.5 text-[10px] font-black text-red-700"
                        >● LIVE WITA</span
                    >
                </div>
                <div class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="chart-stat">
                        <span>Jam Tersibuk</span
                        ><strong>{{ peak ? peak.hour + ".00" : "--" }}</strong
                        ><small>{{ peak?.total || 0 }} kedatangan</small>
                    </div>
                    <div class="chart-stat">
                        <span>Rata-rata Aktif</span
                        ><strong>{{ average }}</strong
                        ><small>kendaraan/jam</small>
                    </div>
                    <div class="chart-stat">
                        <span>Rentang Aktif</span
                        ><strong class="text-sm">{{ activityRange }}</strong
                        ><small>{{ activeHours.length }} jam aktif</small>
                    </div>
                </div>
                <div class="relative mt-5 select-none touch-pan-y">
                    <svg
                        class="h-auto w-full"
                        viewBox="0 0 600 220"
                        role="img"
                        aria-label="Grafik interaktif tren kedatangan per jam"
                        @pointerleave="activePoint = null"
                    >
                        <defs>
                            <linearGradient
                                id="arrivalArea"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stop-color="#ef4444"
                                    stop-opacity=".32"
                                />
                                <stop
                                    offset="100%"
                                    stop-color="#ef4444"
                                    stop-opacity=".02"
                                />
                            </linearGradient>
                            <filter id="lineGlow">
                                <feGaussianBlur
                                    stdDeviation="3"
                                    result="blur"
                                />
                                <feMerge>
                                    <feMergeNode in="blur" />
                                    <feMergeNode in="SourceGraphic" />
                                </feMerge>
                            </filter>
                        </defs>
                        <g stroke="#e8edf3" stroke-width="1">
                            <line
                                v-for="y in [46, 90, 134, 178]"
                                :key="y"
                                x1="24"
                                :y1="y"
                                x2="576"
                                :y2="y"
                                stroke-dasharray="4 6"
                            />
                        </g>
                        <path :d="areaPath" fill="url(#arrivalArea)" />
                        <path
                            :d="linePath"
                            fill="none"
                            stroke="#dc2626"
                            stroke-width="4"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            filter="url(#lineGlow)"
                        />
                        <g v-for="point in chartPoints" :key="point.hour">
                            <text
                                v-if="Number(point.hour) % 3 === 0"
                                :x="point.x"
                                y="205"
                                text-anchor="middle"
                                class="fill-slate-400 text-[10px]"
                            >
                                {{ point.hour }}
                            </text>
                            <rect
                                :x="point.x - 12"
                                y="25"
                                width="24"
                                height="170"
                                fill="transparent"
                                tabindex="0"
                                role="button"
                                :aria-label="
                                    point.hour +
                                    '.00, ' +
                                    point.total +
                                    ' kedatangan'
                                "
                                @pointerenter="selectPoint(point)"
                                @pointerdown.prevent="selectPoint(point)"
                                @focus="selectPoint(point)"
                            />
                            <circle
                                :cx="point.x"
                                :cy="point.y"
                                :r="
                                    activePoint?.hour === point.hour
                                        ? 8
                                        : point.total
                                          ? 5
                                          : 2
                                "
                                :fill="
                                    activePoint?.hour === point.hour
                                        ? '#dc2626'
                                        : point.total
                                          ? '#fff'
                                          : '#cbd5e1'
                                "
                                :stroke="point.total ? '#dc2626' : '#cbd5e1'"
                                :stroke-width="point.total ? 3 : 1"
                                class="pointer-events-none transition-all"
                            />
                        </g>
                        <g v-if="activePoint" class="pointer-events-none">
                            <line
                                :x1="activePoint.x"
                                y1="32"
                                :x2="activePoint.x"
                                y2="178"
                                stroke="#dc2626"
                                stroke-width="1.5"
                                stroke-dasharray="4 4"
                            />
                            <g
                                :transform="`translate(${Math.min(470, Math.max(28, activePoint.x - 52))},18)`"
                            >
                                <rect
                                    width="104"
                                    height="44"
                                    rx="10"
                                    fill="#101b35"
                                />
                                <text
                                    x="52"
                                    y="18"
                                    text-anchor="middle"
                                    fill="white"
                                    font-size="10"
                                    font-weight="700"
                                >
                                    {{ activePoint.hour }}.00 WITA
                                </text>
                                <text
                                    x="52"
                                    y="34"
                                    text-anchor="middle"
                                    fill="#fecaca"
                                    font-size="11"
                                    font-weight="800"
                                >
                                    {{ activePoint.total }} kedatangan
                                </text>
                            </g>
                        </g>
                    </svg>
                </div>
                <div class="mt-1 flex flex-wrap gap-2">
                    <span class="text-[10px] font-bold uppercase text-slate-400"
                        >Jam terpadat:</span
                    ><span
                        v-for="(x, i) in busiest"
                        :key="x.hour"
                        class="rounded-lg bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600"
                        ><b class="text-red-600">#{{ i + 1 }}</b>
                        {{ x.hour }}.00 · {{ x.total }}</span
                    >
                </div>
            </article>
            <form class="panel" @submit.prevent="load">
                <h2 class="panel-title">Filter Monitoring</h2>
                <p class="panel-copy">Seluruh pencarian diproses server</p>
                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <input
                        v-model="f.search"
                        class="control xl:col-span-2"
                        placeholder="Cari driver, nopol, perusahaan..."
                    /><input
                        v-model="f.date_from"
                        type="date"
                        class="control"
                    /><input
                        v-model="f.date_to"
                        type="date"
                        class="control"
                    /><select v-model="f.company" class="control">
                        <option value="">Semua ekspedisi</option>
                        <option v-for="x in options.companies">
                            {{ x }}
                        </option></select
                    ><select v-model="f.vehicle_type" class="control">
                        <option value="">Semua kendaraan</option>
                        <option v-for="x in options.vehicleTypes">
                            {{ x }}
                        </option></select
                    ><select v-model="f.vendor" class="control">
                        <option value="">Semua vendor</option>
                        <option v-for="x in options.vendors">
                            {{ x }}
                        </option></select
                    ><select v-model="f.destination" class="control">
                        <option value="">Semua tujuan</option>
                        <option v-for="x in options.destinations">
                            {{ x }}
                        </option>
                    </select>
                </div>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button class="btn">Terapkan Filter</button
                    ><button type="button" class="btn-ghost" @click="reset">
                        Reset</button
                    ><button
                        type="button"
                        class="btn-ghost ml-auto"
                        @click="reload"
                    >
                        Muat Ulang</button
                    ><a :href="'/coordinator/export?' + query" class="btn-ghost"
                        >Export CSV</a
                    >
                </div>
            </form>
        </section>
        <section class="panel mt-5 overflow-hidden p-0">
            <div class="flex justify-between border-b px-5 py-4">
                <div>
                    <h2 class="panel-title">Monitoring Kedatangan Ekspedisi</h2>
                    <p class="panel-copy">DataTable server-side</p>
                </div>
                <span class="text-xs text-slate-400"
                    >{{ reports.total }} data</span
                >
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[2220px] table-fixed">
                    <colgroup>
                        <col class="w-[55px]" />
                        <col class="w-[115px]" />
                        <col class="w-[125px]" />
                        <col class="w-[220px]" />
                        <col class="w-[175px]" />
                        <col class="w-[145px]" />
                        <col class="w-[150px]" />
                        <col class="w-[130px]" />
                        <col class="w-[90px]" />
                        <col class="w-[145px]" />
                        <col class="w-[330px]" />
                        <col class="w-[175px]" />
                        <col class="w-[150px]" />
                        <col class="w-[320px]" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>
                                <SortButton
                                    label="Tanggal"
                                    column="arrived_at"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Jam Tiba"
                                    column="arrived_at"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Perusahaan"
                                    column="expedition_company"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Driver"
                                    column="driver_name"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Nomor HP"
                                    column="driver_phone"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Nomor Polisi"
                                    column="vehicle_plate"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Kendaraan"
                                    column="vehicle_type"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>Tensi</th>
                            <th>Bukti Foto</th>
                            <th>Koordinat & Lokasi</th>
                            <th>
                                <SortButton
                                    label="Vendor"
                                    column="sender_vendor"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>
                                <SortButton
                                    label="Tujuan"
                                    column="destination"
                                    :sort="f.sort"
                                    :direction="f.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>Informasi Escort</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(x, i) in reports.data" :key="x.id">
                            <td>{{ reports.from + i }}</td>
                            <td>{{ date(x.arrived_at) }}</td>
                            <td>
                                <span
                                    class="time-badge inline-flex whitespace-nowrap"
                                    >{{ time(x.arrived_at) }} WITA</span
                                >
                            </td>
                            <td>
                                <strong>{{ x.expedition_company }}</strong
                                ><small>{{ x.report_number }}</small>
                            </td>
                            <td>{{ x.driver_name }}</td>
                            <td>
                                <a
                                    :href="'tel:' + x.driver_phone"
                                    class="text-blue-600 underline"
                                    >{{ x.driver_phone }}</a
                                >
                            </td>
                            <td>
                                <span
                                    class="plate-badge inline-flex whitespace-nowrap"
                                    >{{ x.vehicle_plate }}</span
                                >
                            </td>
                            <td>{{ x.vehicle_type }}</td>
                            <td>
                                <div
                                    v-if="x.blood_pressure_systolic"
                                    class="min-w-44"
                                >
                                    <b
                                        >{{ x.blood_pressure_systolic }}/{{
                                            x.blood_pressure_diastolic
                                        }}
                                        mmHg</b
                                    >
                                    <span
                                        class="mt-2 block w-fit rounded-full px-2.5 py-1 text-[10px] font-bold"
                                        :class="
                                            healthStatusClass(x.health_status)
                                        "
                                        >{{
                                            x.health_status_label ||
                                            "Belum dinilai"
                                        }}</span
                                    >
                                    <p
                                        v-if="x.health_status_reason"
                                        class="mt-2 max-w-56 whitespace-normal break-words text-[11px] leading-4 text-slate-500"
                                    >
                                        {{ x.health_status_reason }}
                                    </p>
                                </div>
                                <span v-else>-</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        v-if="x.arrival_photo_path"
                                        :href="`/coordinator/reports/${x.id}/photos/arrival`"
                                        target="_blank"
                                        class="font-bold text-red-600"
                                        >Kedatangan</a
                                    ><a
                                        v-if="x.blood_pressure_photo_path"
                                        :href="`/coordinator/reports/${x.id}/photos/blood-pressure`"
                                        target="_blank"
                                        class="font-bold text-red-600"
                                        >Tensi</a
                                    ><span
                                        v-if="
                                            !x.arrival_photo_path &&
                                            !x.blood_pressure_photo_path
                                        "
                                        >-</span
                                    >
                                </div>
                            </td>
                            <td>
                                <div
                                    v-if="x.arrival_latitude"
                                    class="w-full whitespace-normal rounded-xl border border-slate-100 bg-slate-50/80 p-3"
                                >
                                    <b
                                        class="block leading-relaxed text-slate-800"
                                        >{{
                                            x.arrival_location_name ||
                                            "Nama daerah tidak tersedia"
                                        }}</b
                                    ><span
                                        class="mt-1 block font-mono text-[10px] text-slate-500"
                                        >{{ x.arrival_latitude }},
                                        {{ x.arrival_longitude }}</span
                                    ><span
                                        v-if="
                                            x.arrival_location_accuracy !== null
                                        "
                                        class="mt-1 block text-[10px] text-slate-400"
                                        >Akurasi ±{{
                                            x.arrival_location_accuracy
                                        }}
                                        meter</span
                                    ><a
                                        :href="
                                            'https://www.google.com/maps?q=' +
                                            x.arrival_latitude +
                                            ',' +
                                            x.arrival_longitude
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-2 inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1.5 text-[10px] font-black text-red-600 transition hover:bg-red-600 hover:text-white"
                                        >Buka Maps ↗</a
                                    >
                                </div>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td>{{ x.sender_vendor }}</td>
                            <td>{{ x.destination }}</td>
                            <td>
                                <div
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span
                                            class="status-badge"
                                            :class="
                                                escortStatusClass(
                                                    x.escort_status,
                                                )
                                            "
                                            >{{
                                                escortStatusLabel(
                                                    x.escort_status,
                                                )
                                            }}</span
                                        >
                                        <b class="text-xs text-slate-700">{{
                                            date(x.arrived_at)
                                        }}</b>
                                    </div>
                                    <dl
                                        class="mt-2 grid gap-1.5 text-[11px] leading-4"
                                    >
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-slate-500">
                                                Mulai escort
                                            </dt>
                                            <dd
                                                class="font-semibold text-slate-800"
                                            >
                                                {{
                                                    escortTime(
                                                        x.escort_started_at,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="x.escort_closed_at"
                                            class="flex justify-between gap-3"
                                        >
                                            <dt class="text-slate-500">
                                                Selesai escort
                                            </dt>
                                            <dd
                                                class="font-semibold text-emerald-700"
                                            >
                                                {{
                                                    escortTime(
                                                        x.escort_closed_at,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="x.escort_driver"
                                            class="mt-1 border-t border-slate-200 pt-2"
                                        >
                                            <dt class="text-slate-500">
                                                Driver escort
                                            </dt>
                                            <dd
                                                class="mt-0.5 break-words font-semibold text-slate-800"
                                            >
                                                {{ x.escort_driver.name }} ·
                                                Lambung
                                                {{
                                                    x.escort_driver
                                                        .hull_number || "-"
                                                }}
                                            </dd>
                                        </div>
                                        <button
                                            v-if="x.escort_status === 'CLOSED'"
                                            type="button"
                                            class="mt-1 inline-flex w-full items-center justify-center rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-[11px] font-bold text-red-600 transition hover:border-red-200 hover:bg-red-600 hover:text-white"
                                            @click="selectedEscort = x"
                                        >
                                            Lihat bukti & lokasi close
                                        </button>
                                        <div
                                            v-if="x.escort_status === 'CLOSED'"
                                            class="hidden"
                                        >
                                            <dt class="text-slate-500">
                                                Bukti close escort
                                            </dt>
                                            <dd
                                                class="mt-2 flex flex-wrap gap-2"
                                            >
                                                <a
                                                    v-if="
                                                        x.escort_close_photo_path
                                                    "
                                                    :href="`/coordinator/reports/${x.id}/escort-photo`"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-2.5 py-1.5 font-bold text-red-600 transition hover:bg-red-600 hover:text-white"
                                                    >Lihat foto close</a
                                                >
                                                <span
                                                    v-else
                                                    class="text-slate-400"
                                                    >Foto tidak tersedia</span
                                                >
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                x.escort_status === 'CLOSED' &&
                                                x.escort_close_latitude !==
                                                    null &&
                                                x.escort_close_longitude !==
                                                    null
                                            "
                                            class="hidden"
                                        >
                                            <dt class="text-slate-500">
                                                Lokasi close escort
                                            </dt>
                                            <dd
                                                class="mt-1 break-words font-semibold text-slate-800"
                                            >
                                                {{
                                                    x.escort_close_location_name ||
                                                    "Nama lokasi tidak tersedia"
                                                }}
                                            </dd>
                                            <dd
                                                class="mt-1 break-all font-mono text-[10px] text-slate-500"
                                            >
                                                {{ x.escort_close_latitude }},
                                                {{ x.escort_close_longitude }}
                                            </dd>
                                            <dd
                                                v-if="
                                                    x.escort_close_location_accuracy !==
                                                    null
                                                "
                                                class="mt-1 text-[10px] text-slate-400"
                                            >
                                                Akurasi ±{{
                                                    x.escort_close_location_accuracy
                                                }}
                                                meter
                                            </dd>
                                            <dd class="mt-2">
                                                <a
                                                    :href="`https://www.google.com/maps?q=${x.escort_close_latitude},${x.escort_close_longitude}`"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1.5 font-bold text-emerald-700 transition hover:bg-emerald-600 hover:text-white"
                                                    >Buka lokasi close di
                                                    Maps</a
                                                >
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    v-if="!reports.data.length"
                    class="p-14 text-center text-sm text-slate-500"
                >
                    Data tidak ditemukan.
                </div>
            </div>
            <DataTableFooter
                :paginator="reports"
                :per-page="f.per_page"
                @update:per-page="size"
            />
        </section>
        <Modal
            :show="Boolean(selectedEscort)"
            max-width="lg"
            @close="selectedEscort = null"
        >
            <div v-if="selectedEscort" class="p-5 sm:p-6">
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-bold uppercase tracking-[.18em] text-red-600"
                        >
                            Detail Escort Ekspedisi
                        </p>
                        <h3
                            class="mt-1 break-words text-xl font-bold text-slate-950"
                        >
                            {{ selectedEscort.expedition_company }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ selectedEscort.vehicle_plate }} ·
                            {{ selectedEscort.report_number }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200"
                        aria-label="Tutup detail"
                        @click="selectedEscort = null"
                    >
                        ✕
                    </button>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p
                            class="text-[10px] font-bold uppercase tracking-wide text-slate-400"
                        >
                            Status & Waktu
                        </p>
                        <span
                            class="status-badge mt-2"
                            :class="
                                escortStatusClass(selectedEscort.escort_status)
                            "
                            >{{
                                escortStatusLabel(selectedEscort.escort_status)
                            }}</span
                        >
                        <dl class="mt-3 grid gap-2 text-xs">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Mulai</dt>
                                <dd class="font-semibold">
                                    {{
                                        escortTime(
                                            selectedEscort.escort_started_at,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Selesai</dt>
                                <dd class="font-semibold text-emerald-700">
                                    {{
                                        escortTime(
                                            selectedEscort.escort_closed_at,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p
                            class="text-[10px] font-bold uppercase tracking-wide text-slate-400"
                        >
                            Driver Escort
                        </p>
                        <p
                            class="mt-2 break-words text-sm font-bold text-slate-900"
                        >
                            {{ selectedEscort.escort_driver?.name || "-" }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Nomor lambung
                            {{
                                selectedEscort.escort_driver?.hull_number || "-"
                            }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 rounded-2xl border border-slate-200 p-4">
                    <p
                        class="text-[10px] font-bold uppercase tracking-wide text-slate-400"
                    >
                        Lokasi Penutupan
                    </p>
                    <p
                        class="mt-2 break-words text-sm font-bold leading-5 text-slate-900"
                    >
                        {{
                            selectedEscort.escort_close_location_name ||
                            "Nama lokasi tidak tersedia"
                        }}
                    </p>
                    <p
                        v-if="selectedEscort.escort_close_latitude !== null"
                        class="mt-2 break-all font-mono text-[11px] text-slate-500"
                    >
                        {{ selectedEscort.escort_close_latitude }},
                        {{ selectedEscort.escort_close_longitude }}
                    </p>
                    <p
                        v-if="
                            selectedEscort.escort_close_location_accuracy !==
                            null
                        "
                        class="mt-1 text-[11px] text-slate-400"
                    >
                        Akurasi ±{{
                            selectedEscort.escort_close_location_accuracy
                        }}
                        meter
                    </p>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <a
                        v-if="selectedEscort.escort_close_photo_path"
                        :href="`/coordinator/reports/${selectedEscort.id}/escort-photo`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-600 px-4 text-sm font-bold text-white shadow-lg shadow-red-100 transition hover:bg-red-700"
                        >Lihat Foto Close</a
                    >
                    <a
                        v-if="selectedEscort.escort_close_latitude !== null"
                        :href="`https://www.google.com/maps?q=${selectedEscort.escort_close_latitude},${selectedEscort.escort_close_longitude}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-50 px-4 text-sm font-bold text-emerald-700 transition hover:bg-emerald-600 hover:text-white"
                        >Buka di Google Maps</a
                    >
                </div>
            </div>
        </Modal>
    </AppShell>
</template>

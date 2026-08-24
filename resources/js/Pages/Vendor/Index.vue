<script setup>
import AppShell from "@/Components/AppShell.vue";
import FileDropInput from "@/Components/FileDropInput.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import { useForm, Link, router } from "@inertiajs/vue3";
import { ref, computed, reactive } from "vue";
const p = defineProps({
    requirements: Object,
    submissions: Object,
    tableFilters: Object,
    vendorIdentity: String,
});
const type = ref("VISITOR");
const source = ref(null);
const multi = computed(() => true);
const isUnit = computed(() => type.value === "IJIN_LINTAS");
const renewalCode = computed(() => ({ WASKAT:"SURAT_SEHAT_VENDOR", IJIN_LINTAS:"SURAT_SEHAT", PERMIT_VENDOR_NON_SIMPER:"MCU", VISITOR:"SURAT_TUGAS" })[type.value]);
const blankUnit = () => ({ plate_number: "", person_name: "", source_unit_id:null, documents: {} });
const form = useForm({
    type: "VISITOR",
    service_category: "",
    planning_frequency: "",
    parent_submission_id: null,
    vendor_name: p.vendorIdentity,
    applicant_name: "",
    phone: "",
    description: "",
    documents: {},
    units: [blankUnit()],
});
const docs = computed(() => p.requirements[type.value].documents);
const shownDocs = computed(() =>
    source.value
        ? docs.value.filter((d) => d.code === renewalCode.value)
        : docs.value,
);
const table = reactive({ search: "", per_page: 10, ...p.tableFilters });
const pending = computed(() =>
    p.submissions.data.filter(
        (x) => x.resubmission_requested_at && !x.resubmissions_count,
    ),
);
const selectType = (x) => {
    type.value = x;
    source.value = null;
    form.type = x;
    form.parent_submission_id = null;
    form.service_category = "";
    form.planning_frequency = "";
    form.documents = {};
    form.units = [blankUnit()];
    form.vendor_name = p.vendorIdentity;
};
const addUnit = () => form.units.push(blankUnit());
const removeUnit = (i) => {
    if (form.units.length > 1) form.units.splice(i, 1);
};
const setUnitFile = (i, code, file) => {
    form.units[i].documents = { ...form.units[i].documents, [code]: file };
    if (!file) delete form.units[i].documents[code];
};
const setFile = (code, file) => {
    form.documents = { ...form.documents, [code]: file };
    if (!file) delete form.documents[code];
};
const complete = (x) => {
    source.value = x;
    type.value = x.type;
    form.type = x.type;
    form.parent_submission_id = x.id;
    form.service_category = x.service_category || "";
    form.planning_frequency = x.planning_frequency || (x.service_category === "NON_REGULAR" ? "NON_REGULAR" : "WEEKLY");
    form.vendor_name = x.vendor_name;
    form.applicant_name = x.applicant_name;
    form.phone = x.phone;
    form.description = x.description || "";
    const allowed=x.resubmission_subject_ids||x.units.map(u=>u.id);
    form.units = (x.units || []).filter(u=>allowed.includes(u.id)).map((u) => ({
        source_unit_id:u.id, plate_number: u.plate_number||"", person_name:u.person_name||"",
        documents: {},
    }));
    scrollTo({ top: 0, behavior: "smooth" });
};
const send = () =>
    form.post("/vendor/submissions", {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            selectType("VISITOR");
        },
    });
const load = () =>
    router.get("/vendor", table, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const date = (v) => (v ? new Date(v).toLocaleDateString("id-ID") : "-");
const validity = (x) =>
    !x.released_at
        ? "Belum dirilis"
        : new Date(x.release_expired_at) <= new Date()
          ? "Expired"
          : `Aktif sampai ${date(x.release_expired_at)}`;
</script>
<template>
    <AppShell
        title="Pengajuan Vendor Multi-Unit"
        subtitle="Satu pengajuan dapat memuat beberapa nomor plat dan dokumen per unit."
        eyebrow="Akun Vendor"
        ><section
            v-if="pending.length"
            class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5"
        >
            <h2 class="font-black">Permintaan Surat Sehat dari Koordinator</h2>
            <button
                v-for="x in pending"
                :key="x.id"
                class="mt-3 mr-3 rounded-xl bg-white p-4 text-left shadow"
                @click="complete(x)"
            >
                <b>{{ x.submission_number }}</b
                ><span class="block text-sm font-bold text-red-600"
                    >Lengkapi Surat Sehat
                    {{ x.units_count || x.units?.length }} unit →</span
                >
            </button>
        </section>
        <div class="lg:grid lg:grid-cols-[230px_1fr] lg:gap-5">
            <aside class="panel hidden h-fit p-3 lg:block">
                <button
                    v-for="(v, k) in requirements"
                    class="vendor-type-link"
                    :class="{ 'vendor-type-active': type === k }"
                    @click="selectType(k)"
                >
                    {{ v.label }}
                </button>
            </aside>
            <div>
                <select
                    :value="type"
                    class="control mb-4 lg:hidden"
                    @change="selectType($event.target.value)"
                >
                    <option v-for="(v, k) in requirements" :value="k">
                        {{ v.label }}
                    </option>
                </select>
                <form class="panel" @submit.prevent="send">
                    <h2 class="text-xl font-black">
                        {{
                            source
                                ? "Pembaruan Surat Sehat"
                                : "Pengajuan " + requirements[type].label
                        }}
                    </h2>
                    <p v-if="source" class="text-sm text-slate-500">
                        Nomor plat dan seluruh dokumen sebelumnya tetap
                        digunakan.
                    </p>
                    <label
                        v-if="type === 'IJIN_LINTAS' && !source"
                        class="mt-4 block"
                        >Kategori Planning Ijin Lintas<select
                            v-model="form.planning_frequency"
                            class="field"
                        >
                            <option value="">Pilih kategori planning</option>
                            <option value="DAILY">Reguler · Daily</option>
                            <option value="WEEKLY">Reguler · Weekly</option>
                            <option value="NON_REGULAR">Non Reguler</option>
                        </select><span class="err">{{ form.errors.planning_frequency }}</span></label
                    >
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label
                            >Nama Vendor<input
                                v-model="form.vendor_name"
                                readonly
                                class="field bg-slate-50" /><small class="text-slate-400">Otomatis dari akun login</small></label
                        ><label
                            >Pemohon/PIC<input
                                v-model="form.applicant_name"
                                class="field" /></label
                        ><label
                            >Nomor HP<input
                                v-model="form.phone"
                                class="field" /></label
                        ><label
                            >Keterangan<textarea
                                v-model="form.description"
                                class="field"
                            />
                        </label>
                    </div>
                    <template v-if="multi"
                        ><div
                            v-for="(unit, i) in form.units"
                            :key="i"
                            class="mt-6 rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex items-end gap-3">
                                <label class="flex-1 font-bold"
                                    >{{ isUnit ? "Nomor Plat Unit" : "Nama Orang" }} {{ i + 1
                                    }}<input v-if="isUnit" v-model="unit.plate_number"
                                        class="field uppercase"
                                        placeholder="DA 1234 XX" /><input v-else v-model="unit.person_name" class="field" placeholder="Nama lengkap orang" /></label
                                ><button
                                    v-if="form.units.length > 1"
                                    type="button"
                                    class="btn-ghost"
                                    @click="removeUnit(i)"
                                >
                                    Hapus Unit
                                </button>
                            </div>
                            <p class="err">
                                {{
                                    form.errors["units." + i + ".plate_number"]
                                }}
                            </p>
                            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                                <FileDropInput
                                    v-for="d in shownDocs"
                                    :key="d.code"
                                    :label="d.name"
                                    :required="source || d.required"
                                    :model-value="unit.documents[d.code]"
                                    :error="
                                        form.errors[
                                            'units.' +
                                                i +
                                                '.documents.' +
                                                d.code
                                        ]
                                    "
                                    @update:model-value="
                                        setUnitFile(i, d.code, $event)
                                    "
                                />
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn-ghost mt-4 w-full"
                            @click="addUnit"
                        >
                            + Tambah {{ isUnit ? "Nomor Plat / Unit" : "Orang" }}
                        </button></template
                    >
                    <div v-else class="mt-6 grid gap-4 xl:grid-cols-2">
                        <FileDropInput
                            v-for="d in docs"
                            :key="d.code"
                            :label="d.name"
                            :required="d.required"
                            :model-value="form.documents[d.code]"
                            @update:model-value="setFile(d.code, $event)"
                        />
                    </div>
                    <p class="err mt-3">
                        {{ form.errors.units || form.errors.documents }}
                    </p>
                    <button :disabled="form.processing" class="btn mt-6 w-full">
                        {{
                            form.processing
                                ? "MENGIRIM..."
                                : source
                                  ? "KIRIM SURAT SEHAT TERBARU"
                                  : "KIRIM PENGAJUAN"
                        }}
                    </button>
                </form>
                <section class="panel mt-6 overflow-hidden p-0">
                    <div class="border-b p-5">
                        <h2 class="panel-title">Data Akun Vendor Ini</h2>
                        <form class="mt-3 flex gap-3" @submit.prevent="load">
                            <input
                                v-model="table.search"
                                class="control flex-1"
                            /><button class="btn">Cari</button>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table min-w-[900px]">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>ID</th>
                                    <th>Jenis</th>
                                    <th>Unit</th>
                                    <th>Status</th>
                                    <th>Release/Expired</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(x, i) in submissions.data">
                                    <td>{{ submissions.from + i }}</td>
                                    <td>
                                        <b>{{ x.submission_number }}</b>
                                    </td>
                                    <td>{{ requirements[x.type].label }}</td>
                                    <td>{{ x.units_count }} unit</td>
                                    <td>{{ x.status }}</td>
                                    <td>
                                        <b>{{ validity(x) }}</b
                                        ><small
                                            >{{ date(x.released_at) }} →
                                            {{
                                                date(x.release_expired_at)
                                            }}</small
                                        >
                                    </td>
                                    <td>
                                        <Link
                                            :href="
                                                '/vendor/submissions/' + x.id
                                            "
                                            class="font-bold text-red-600"
                                            >Detail</Link
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <DataTableFooter
                        :paginator="submissions"
                        :per-page="table.per_page"
                        @update:per-page="
                            (v) => {
                                table.per_page = v;
                                load();
                            }
                        "
                    />
                </section>
            </div></div
    ></AppShell>
</template>

<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Modal from "@/Components/Modal.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import { useForm } from "@inertiajs/vue3";
import { computed, watch } from "vue";

const props = defineProps({
    show: Boolean,
    planning: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    vehicleTypes: { type: Array, default: () => [] },
    endpoint: String,
    range: Object,
});
const emit = defineEmits(["close"]);
const blankUnit = () => ({ id: null, vehicle_plate: "", vehicle_type: "", route_from: "", route_to: "", planned_date: props.range?.start || "" });
const form = useForm({ vendor_company_id: "", notes: "", units: [blankUnit()] });
const editing = computed(() => Boolean(props.planning?.id));
const vendorOptions = computed(() => props.vendors.map((vendor) => ({ value: vendor.id, label: `${vendor.name} (${vendor.abbreviation})`, keywords: vendor.abbreviation })));

watch(() => [props.show, props.planning], () => {
    if (!props.show) return;
    form.clearErrors();
    form.vendor_company_id = props.planning?.vendor_company_id || "";
    form.notes = props.planning?.notes || "";
    form.units = props.planning?.units?.length
        ? props.planning.units.map((unit) => ({ id: unit.id, vehicle_plate: unit.vehicle_plate, vehicle_type: unit.vehicle_type, route_from: unit.route_from, route_to: unit.route_to, planned_date: String(unit.planned_date).slice(0, 10) }))
        : [blankUnit()];
}, { deep: true });

const addUnit = () => form.units.push(blankUnit());
const removeUnit = (index) => {
    if (form.units.length > 1) form.units.splice(index, 1);
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit("close") };
    if (editing.value) form.put(`${props.endpoint}/${props.planning.id}`, options);
    else form.post(props.endpoint, options);
};
</script>

<template>
    <Modal :show="show" max-width="4xl" :closeable="!form.processing" @close="$emit('close')">
        <form class="flex max-h-[92vh] flex-col" @submit.prevent="submit">
            <header class="border-b border-slate-200 px-5 py-4 sm:px-7 sm:py-5">
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-red-600">Planning tanpa pengajuan</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">{{ editing ? "Edit Planning Langsung" : "Buat Planning Langsung" }}</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih vendor dan masukkan satu atau beberapa unit dengan jadwal masing-masing.</p>
            </header>

            <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5 sm:px-7">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="Vendor" />
                        <SearchableSelect v-model="form.vendor_company_id" class="mt-1" :options="vendorOptions" placeholder="Pilih vendor aktif" search-placeholder="Cari nama vendor..." />
                        <InputError class="mt-1" :message="form.errors.vendor_company_id" />
                    </div>
                    <div>
                        <InputLabel value="Catatan (opsional)" />
                        <textarea v-model="form.notes" class="control mt-1 min-h-11 resize-y" maxlength="2000" placeholder="Keterangan kebutuhan planning" />
                        <InputError class="mt-1" :message="form.errors.notes" />
                    </div>
                </div>

                <section>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Daftar unit kendaraan</h3>
                            <p class="text-xs text-slate-500">Setiap unit dapat memiliki tanggal dan rute berbeda.</p>
                        </div>
                        <button type="button" class="btn-ghost shrink-0" @click="addUnit">Tambah Unit</button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.units" />

                    <div class="mt-3 space-y-4">
                        <article v-for="(unit, index) in form.units" :key="unit.id || index" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-black text-white">Unit {{ index + 1 }}</span>
                                <button type="button" class="text-xs font-bold text-red-600 disabled:cursor-not-allowed disabled:text-slate-300" :disabled="form.units.length === 1" @click="removeUnit(index)">Hapus unit</button>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <InputLabel value="Nomor Polisi" />
                                    <input v-model="unit.vehicle_plate" class="control mt-1 uppercase" maxlength="30" placeholder="DA 1234 AB" />
                                    <InputError class="mt-1" :message="form.errors[`units.${index}.vehicle_plate`]" />
                                </div>
                                <div>
                                    <InputLabel value="Jenis Kendaraan" />
                                    <select v-model="unit.vehicle_type" class="control mt-1"><option value="">Pilih kendaraan</option><option v-for="type in vehicleTypes" :key="type" :value="type">{{ type }}</option></select>
                                    <InputError class="mt-1" :message="form.errors[`units.${index}.vehicle_type`]" />
                                </div>
                                <div>
                                    <InputLabel value="Tanggal Planning" />
                                    <input v-model="unit.planned_date" type="date" class="control mt-1" :min="range.start" :max="range.end" />
                                    <InputError class="mt-1" :message="form.errors[`units.${index}.planned_date`]" />
                                </div>
                                <div>
                                    <InputLabel value="Asal" />
                                    <input v-model="unit.route_from" class="control mt-1" maxlength="200" placeholder="Lokasi asal" />
                                    <InputError class="mt-1" :message="form.errors[`units.${index}.route_from`]" />
                                </div>
                                <div class="sm:col-span-1 lg:col-span-2">
                                    <InputLabel value="Tujuan" />
                                    <input v-model="unit.route_to" class="control mt-1" maxlength="200" placeholder="Lokasi tujuan" />
                                    <InputError class="mt-1" :message="form.errors[`units.${index}.route_to`]" />
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <footer class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
                <button type="button" class="btn-ghost" :disabled="form.processing" @click="$emit('close')">Batal</button>
                <button type="submit" class="btn" :disabled="form.processing">{{ form.processing ? "Menyimpan..." : editing ? "Simpan Perubahan" : "Buat Planning" }}</button>
            </footer>
        </form>
    </Modal>
</template>

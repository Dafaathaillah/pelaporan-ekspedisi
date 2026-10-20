<?php

namespace App\Services;

use App\Enums\SubmissionType;

class VendorDocumentRequirementService
{
    public function for(SubmissionType|string $type): array
    {
        $type = $type instanceof SubmissionType ? $type : SubmissionType::from($type);
        $items = match ($type) {
            SubmissionType::VISITOR => [['SURAT_TUGAS', 'Surat Tugas', true], ['KTP', 'KTP', true]],
            SubmissionType::WASKAT => [['KTP_VENDOR', 'KTP Vendor', true], ['FOTO_4X4_LATAR_MERAH', 'Foto 4x4 Latar Merah', true], ['SURAT_SEHAT_VENDOR', 'Surat Sehat Vendor', true], ['MINE_PERMIT_PENGAWAS_1', 'Mine Permit Pengawas 1', false], ['MINE_PERMIT_PENGAWAS_2', 'Mine Permit Pengawas 2', false], ['SPDK', 'SPDK', false], ['JSA_IK', 'JSA / IK', false], ['SOSIALISASI_JSA', 'Sosialisasi JSA', false]],
            SubmissionType::PERMIT_VENDOR_NON_SIMPER => [['FOTO_4X4', 'Foto 4x4', true], ['KTP', 'KTP', true], ['MCU', 'MCU', true], ['SPDK', 'SPDK', false], ['MINE_PERMIT_CUSTODIAN', 'Mine Permit Custodian', false]],
            SubmissionType::IJIN_LINTAS => [['FOTO_UNIT_4_SISI', 'Foto Unit 4 Sisi', true], ['FOTO_P2H', 'Foto P2H', true], ['KTP', 'KTP', true], ['SIM', 'SIM', true], ['STNK', 'STNK', true], ['KIR', 'KIR', true], ['SURAT_SEHAT', 'Surat Sehat', true]],
        };

        return array_map(fn ($i) => ['code' => $i[0], 'name' => $i[1], 'required' => $i[2]], $items);
    }

    public function all(): array
    {
        return collect(SubmissionType::cases())->mapWithKeys(fn ($t) => [$t->value => ['label' => $t->label(), 'documents' => $this->for($t)]])->all();
    }
}

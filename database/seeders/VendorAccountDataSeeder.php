<?php

namespace Database\Seeders;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\User;
use App\Models\VendorSubmission;
use App\Models\VendorCompany;
use App\Services\VendorDocumentRequirementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VendorAccountDataSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('local')->deleteDirectory('private/vendor-documents');
        Storage::disk('local')->deleteDirectory('private/expedition-reports');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['planning_items', 'vendor_documents', 'vendor_submission_units', 'vendor_submissions', 'expedition_reports'] as $table) {
            DB::table($table)->truncate();
        }
        User::where('role', UserRole::VENDOR)->delete();
        User::where('role', UserRole::EXPEDITION)->where('username', 'like', 'expedition_%')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $accounts = [
            ['DMA', 'vendor_dma', 'PT Dini Murni Abadi', ['DA 8123 DM', 'DA 8456 DM'], 'expedition_borneo', 'PT Borneo Logistik'],
            ['BUMDES', 'vendor_bumdes', 'Bumdes HTIIF', ['DA 7234 HT', 'DA 7567 HT'], 'expedition_mitra', 'PT Mitra Angkut BIB'],
            ['SINAR', 'vendor_sinar', 'Sinar Jaya Teknik', ['DA 6345 SJ', 'DA 6678 SJ'], 'expedition_trans', 'CV Trans Kalimantan'],
        ];
        $requirements = app(VendorDocumentRequirementService::class);

        foreach ($accounts as $accountIndex => [$code, $vendorUsername, $vendorName, $plates, $expeditionUsername, $expeditionName]) {
            $master = VendorCompany::where('abbreviation', $code === 'SINAR' ? 'SJPL' : $code)->first();
            $vendorName = $master?->name ?? $vendorName;
            $vendor = User::create(['name' => $vendorName, 'username' => $vendorUsername, 'password' => 'Vendor123!', 'role' => UserRole::VENDOR, 'vendor_company_id' => $master?->id, 'site' => 'BIB', 'is_active' => true, 'permissions' => ['vendor.submission']]);
            $expedition = User::create(['name' => $expeditionName, 'username' => $expeditionUsername, 'password' => 'Expedition123!', 'role' => UserRole::EXPEDITION, 'site' => 'BIB', 'is_active' => true, 'permissions' => ['expedition.report']]);

            foreach (SubmissionType::cases() as $typeIndex => $type) {
                $released = now()->subDays(10);
                $submission = VendorSubmission::create([
                    'submission_number' => 'DEMO-'.$code.'-'.$type->value,
                    'type' => $type,
                    'service_category' => $type === SubmissionType::IJIN_LINTAS ? 'REGULAR' : null,
                    'vendor_name' => $vendorName,
                    'applicant_name' => 'PIC '.$vendorName,
                    'phone' => '08129000'.$accountIndex.$typeIndex,
                    'description' => 'Data subjek khusus akun '.$vendorUsername,
                    'status' => SubmissionStatus::RELEASED,
                    'submitted_at' => now()->subDays(12),
                    'released_at' => $released,
                    'release_expired_at' => $released->copy()->addDays(7),
                    'created_by' => $vendor->id,
                    'site' => 'BIB',
                ]);
                $submission->planningItems()->create(['position' => 0]);

                $subjects = $type === SubmissionType::IJIN_LINTAS ? $plates : ['Andi '.$code, 'Budi '.$code];
                foreach ($subjects as $index => $identity) {
                    $isUnit = $type === SubmissionType::IJIN_LINTAS;
                    $subject = $submission->units()->create(['subject_type' => $isUnit ? 'UNIT' : 'PERSON', 'person_name' => $isUnit ? null : $identity, 'plate_number' => $isUnit ? $identity : null, 'unit_order' => $index + 1]);
                    foreach ($requirements->for($type) as $document) {
                        $path = 'private/vendor-documents/'.$submission->id.'/'.$subject->id.'/'.$document['code'].'.pdf';
                        Storage::disk('local')->put($path, "%PDF-1.4\n% Demo $identity {$document['code']}\n%%EOF");
                        $subject->documents()->create(['vendor_submission_id' => $submission->id, 'document_code' => $document['code'], 'document_name' => $document['name'], 'file_name' => $document['code'].'.pdf', 'original_name' => $document['code'].'-'.$identity.'.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => Storage::disk('local')->size($path), 'is_required' => $document['required'], 'uploaded_at' => now()->subDays(12), 'verification_status' => $index === 0 ? 'VALID' : 'PENDING']);
                    }
                }

                if ($type === SubmissionType::IJIN_LINTAS) {
                    foreach ($plates as $index => $plate) {
                        ExpeditionReport::create(['report_number' => 'EXP-'.$code.'-'.$index, 'expedition_company' => $expeditionName, 'driver_name' => 'Driver '.$code.' '.($index + 1), 'driver_phone' => '08123000'.$accountIndex.$index, 'vehicle_plate' => $plate, 'vehicle_type' => 'Fuso', 'sender_vendor' => $vendorName, 'vendor_user_id' => $vendor->id, 'destination' => 'Workshop Plant', 'arrived_at' => now()->subDays($index), 'created_by' => $expedition->id, 'site' => 'BIB']);
                    }
                }
            }
        }
    }
}

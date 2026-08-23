<?php

namespace Database\Seeders;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\ExpeditionReport;
use App\Models\User;
use App\Models\VendorSubmission;
use App\Services\VendorDocumentRequirementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DummyOperationalDataSeeder extends Seeder
{
    public function run(): void
    {
        $expeditionUser = User::where('username', 'expedition')->firstOrFail();
        $vendorUser = User::where('username', 'vendor')->firstOrFail();
        $companies = ['PT Dini Murni Abadi', 'PT Borneo Logistik', 'PT Cipta Krida Bahari', 'PT Trans Kaltim', 'PT Nusantara Cargo'];
        $drivers = ['Asep Rahman', 'Hanafi', 'Anto Saputra', 'Habibi', 'Agus Fahrian', 'Rizal Maulana'];
        $vehicles = ['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup'];
        $vendors = ['DMA', 'Bumdes HTIIF', 'Sinar Jaya Teknik', 'Mitra Sarana BIB'];
        $destinations = ['Workshop Plant', 'Logistik', 'Warehouse', 'Main Office', 'Underpass'];

        foreach (range(1, 60) as $i) {
            $arrivedAt = $i <= 24
                ? now()->startOfDay()->addHours(7 + ($i % 11))->addMinutes(($i * 7) % 60)
                : now()->subDays(1 + ($i % 20))->setTime(7 + ($i % 10), ($i * 11) % 60);
            ExpeditionReport::updateOrCreate(['report_number' => 'EXP-DUMMY-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)], [
                'expedition_company' => $companies[$i % count($companies)], 'driver_name' => $drivers[$i % count($drivers)],
                'driver_phone' => '08'.str_pad((string) (1200000000 + $i * 173), 10, '0', STR_PAD_LEFT),
                'vehicle_plate' => 'DA '.(8000 + $i).' '.chr(65 + ($i % 20)).chr(65 + (($i + 4) % 20)),
                'vehicle_type' => $vehicles[$i % count($vehicles)], 'sender_vendor' => $vendors[$i % count($vendors)],
                'destination' => $destinations[$i % count($destinations)], 'arrived_at' => $arrivedAt,
                'created_by' => $expeditionUser->id, 'site' => 'BIB',
            ]);
        }

        $requirements = app(VendorDocumentRequirementService::class);
        $statuses = SubmissionStatus::cases();
        foreach (SubmissionType::cases() as $typeIndex => $type) {
            foreach (range(1, 8) as $i) {
                $submission = VendorSubmission::updateOrCreate(['submission_number' => 'VND-DUMMY-'.$typeIndex.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)], [
                    'type' => $type, 'vendor_name' => $vendors[($i + $typeIndex) % count($vendors)],
                    'applicant_name' => $drivers[($i + 2) % count($drivers)], 'phone' => '0812'.str_pad((string) (34000000 + $i * 91), 8, '0', STR_PAD_LEFT),
                    'description' => 'Pengajuan contoh '.$type->label().' untuk kebutuhan operasional Site BIB.',
                    'status' => $statuses[($i - 1) % count($statuses)], 'coordinator_note' => $i % 3 === 0 ? 'Mohon pastikan dokumen masih berlaku.' : null,
                    'submitted_at' => now()->subDays($i - 1)->subHours($typeIndex), 'reviewed_at' => $i > 1 ? now()->subDays(max(0, $i - 2)) : null,
                    'reviewed_by' => null, 'released_at' => $statuses[($i - 1) % count($statuses)] === SubmissionStatus::RELEASED ? now()->subDay() : null,
                    'revision_count' => $i % 3 === 0 ? 1 : 0, 'created_by' => $vendorUser->id, 'site' => 'BIB',
                ]);
                foreach (collect($requirements->for($type))->take(2) as $document) {
                    $path = 'private/vendor-documents/'.$submission->id.'/'.$document['code'].'.pdf';
                    Storage::disk('local')->put($path, "%PDF-1.4\n% Dummy document for development\n%%EOF");
                    $submission->documents()->updateOrCreate(['document_code' => $document['code']], [
                        'document_name' => $document['name'], 'file_name' => basename($path), 'original_name' => $document['code'].'.pdf',
                        'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => Storage::disk('local')->size($path),
                        'is_required' => $document['required'], 'uploaded_at' => now(),
                    ]);
                }
            }
        }
    }
}

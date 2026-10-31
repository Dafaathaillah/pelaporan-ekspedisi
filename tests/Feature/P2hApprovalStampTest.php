<?php

namespace Tests\Feature;

use App\Models\ArrivalP2h;
use App\Models\DepartureP2h;
use App\Models\User;
use App\Models\VendorSubmission;
use App\Models\VendorSubmissionUnit;
use App\Services\P2hPdf;
use App\Support\P2hChecklist;
use Tests\TestCase;

class P2hApprovalStampTest extends TestCase
{
    private function fixture(string $class, string $status): array
    {
        $submission = new VendorSubmission(['vendor_name' => 'Vendor Stamp']);
        $unit = new VendorSubmissionUnit(['plate_number' => 'DA 123 AB']);
        $unit->setRelation('latestArrivalReport', null)->setRelation('latestDepartureP2h', null);
        $p2h = new $class(['driver_name' => 'Driver Stamp', 'vehicle_type' => 'Fuso', 'vehicle_plate' => 'DA 123 AB', 'items' => array_fill(0, count(P2hChecklist::ITEMS), ['condition' => 'BAIK', 'note' => '']), 'tire_positions' => [1, 2], 'decision' => 'LAIK', 'notes' => null, 'revision' => 1, 'status' => $status, 'created_at' => now(), 'reviewed_at' => $status === 'PENDING' ? null : now(), 'rejection_reason' => $status === 'REJECTED' ? 'Periksa ulang' : null]);
        $p2h->setRelation('reviewer', $status === 'PENDING' ? null : new User(['name' => 'Reviewer Stamp']));

        return compact('submission', 'unit', 'p2h');
    }

    public function test_both_p2h_types_use_embedded_seal_only_when_the_review_is_approved(): void
    {
        foreach ([DepartureP2h::class, ArrivalP2h::class] as $class) {
            foreach (['PENDING', 'APPROVED', 'REJECTED'] as $status) {
                $data = $this->fixture($class, $status);
                $html = view('pdf.p2h', [...$data, 'checklist' => P2hChecklist::ITEMS, 'isArrival' => $class === ArrivalP2h::class])->render();
                // The driver submission stamp remains; the reviewer stamp is conditional.
                $this->assertSame($status === 'APPROVED' ? 2 : 1, substr_count($html, 'class="approval-stamp"'));
                $this->assertStringContainsString('data:image/png;base64,', $html);
                $this->assertStringNotContainsString('<span class="check">', $html);
                if ($status === 'REJECTED') {
                    $this->assertStringContainsString('REJECTED', $html);
                    $this->assertStringContainsString('Periksa ulang', $html);
                }
            }
        }
    }

    public function test_pdf_contains_image_objects_for_departure_and_arrival(): void
    {
        foreach ([DepartureP2h::class, ArrivalP2h::class] as $class) {
            $data = $this->fixture($class, 'APPROVED');
            $pdf = app(P2hPdf::class)->render($data['submission'], $data['unit'], $data['p2h']);
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertTrue(str_contains($pdf, '/Subtype /Image'), 'The PDF must embed the approval seal image.');
            $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf), 'The standard P2H form must still fit on one page.');
        }
    }

    public function test_seal_asset_is_png_with_transparent_outer_background(): void
    {
        $path = public_path('images/approval-ppa.png');
        $this->assertFileExists($path);
        $info = getimagesize($path);
        $this->assertSame('image/png', $info['mime']);
        $image = imagecreatefrompng($path);
        $this->assertGreaterThanOrEqual(120, (imagecolorat($image, 0, 0) >> 24) & 0x7F);
        $this->assertLessThanOrEqual(3, (imagecolorat($image, (int) ($info[0] / 2), (int) ($info[1] / 2)) >> 24) & 0x7F);
        imagedestroy($image);
    }
}

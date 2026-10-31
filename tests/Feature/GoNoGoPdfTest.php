<?php

namespace Tests\Feature;

use App\Models\EscortGoNoGo;
use App\Services\GoNoGoPdf;
use App\Support\GoNoGoChecklist;
use Tests\TestCase;

class GoNoGoPdfTest extends TestCase
{
    public function test_standard_four_unit_pdf_fits_a4_and_preserves_no_go_and_review_dates(): void
    {
        $form = new EscortGoNoGo([
            'id' => 'pdf-fixture', 'department' => 'SCM', 'position' => 'Driver LV', 'destination' => 'PPA-BIB',
            'escorter_name' => 'Escort Commander', 'nik' => '812345', 'revision' => 1,
            'units' => array_map(fn ($i) => ['vehicle_plate' => "DA $i AB", 'driver_name' => "Driver $i"], range(1, 4)),
            'items' => array_fill(0, 11, ['condition' => 'SESUAI', 'evidence' => '', 'note' => '']),
            'driver_decision' => 'TIDAK_LAIK', 'status' => 'APPROVED', 'reviewer_name' => 'Coordinator',
            'submitted_at' => '2026-10-30 01:00:00', 'reviewed_at' => '2026-10-31 02:00:00',
        ]);
        $html = view('pdf.go-no-go', ['form' => $form, 'checklist' => GoNoGoChecklist::ITEMS])->render();
        $this->assertStringContainsString('DA 1 AB, DA 2 AB, DA 3 AB, DA 4 AB', $html);
        $this->assertStringContainsString('NO GO', $html);
        $this->assertStringContainsString('30/10/2026', $html);
        $this->assertStringContainsString('31/10/2026', $html);
        $this->assertSame(1, substr_count($html, 'class="approval-stamp"'));
        $pdf = app(GoNoGoPdf::class)->render($form);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, preg_match_all('/\\/Type\\s*\\/Page\\b/', $pdf));
        $this->assertTrue(str_contains($pdf, '/Subtype /Image'));
        $form->status = 'PENDING';
        $form->reviewed_at = null;
        $form->reviewer_name = null;
        $html = view('pdf.go-no-go', ['form' => $form, 'checklist' => GoNoGoChecklist::ITEMS])->render();
        $this->assertStringNotContainsString('class="approval-stamp"', $html);
        $this->assertStringContainsString('MENUNGGU APPROVAL', $html);
    }
}

<?php

namespace App\Services;

use App\Models\DepartureP2h;
use App\Models\VendorSubmission;
use App\Models\VendorSubmissionUnit;
use App\Support\P2hChecklist;
use Dompdf\Dompdf;
use Dompdf\Options;

class P2hPdf
{
    public function render(VendorSubmission $submission, VendorSubmissionUnit $unit, DepartureP2h $p2h): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(view('pdf.p2h', compact('submission', 'unit', 'p2h'))->with('checklist', P2hChecklist::ITEMS)->render());
        $pdf->render();

        return $pdf->output();
    }
}

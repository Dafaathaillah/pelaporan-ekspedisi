<?php

namespace App\Services;

use App\Models\EscortGoNoGo;
use App\Support\GoNoGoChecklist;
use Dompdf\Dompdf;
use Dompdf\Options;

class GoNoGoPdf
{
    public function render(EscortGoNoGo $form): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(view('pdf.go-no-go', ['form' => $form, 'checklist' => GoNoGoChecklist::ITEMS])->render());
        $pdf->render();

        return $pdf->output();
    }
}

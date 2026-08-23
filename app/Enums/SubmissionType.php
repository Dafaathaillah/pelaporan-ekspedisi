<?php

namespace App\Enums;

enum SubmissionType: string
{
    case VISITOR = 'VISITOR';
    case WASKAT = 'WASKAT';
    case PERMIT_VENDOR_NON_SIMPER = 'PERMIT_VENDOR_NON_SIMPER';
    case IJIN_LINTAS = 'IJIN_LINTAS';

    public function label(): string
    {
        return match ($this) {
            self::VISITOR => 'Visitor', self::WASKAT => 'Waskat', self::PERMIT_VENDOR_NON_SIMPER => 'Permit Vendor Non Simper', self::IJIN_LINTAS => 'Ijin Lintas'
        };
    }
}

<?php

namespace Tests\Unit;

use App\Enums\SubmissionType;
use App\Services\VendorDocumentRequirementService;
use PHPUnit\Framework\TestCase;

class VendorDocumentRequirementServiceTest extends TestCase
{
    public function test_ijin_lintas_requires_a_p2h_photo_for_each_unit(): void
    {
        $requirements = collect((new VendorDocumentRequirementService)->for(SubmissionType::IJIN_LINTAS));

        $this->assertSame(
            ['code' => 'FOTO_P2H', 'name' => 'Foto P2H', 'required' => true],
            $requirements->firstWhere('code', 'FOTO_P2H'),
        );
    }
}

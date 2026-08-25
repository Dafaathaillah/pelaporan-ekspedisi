<?php

namespace Tests\Unit;

use App\Support\BloodPressureAssessment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BloodPressureAssessmentTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'normal' => [119, 79, 'FIT'],
            'systolic fit with note' => [120, 70, 'FIT_WITH_NOTE'],
            'diastolic fit with note' => [110, 80, 'FIT_WITH_NOTE'],
            'systolic pending' => [140, 70, 'PENDING'],
            'diastolic pending' => [120, 90, 'PENDING'],
            'systolic unfit pending' => [160, 80, 'UNFIT_PENDING'],
            'diastolic unfit pending' => [130, 100, 'UNFIT_PENDING'],
            'systolic unfit' => [180, 80, 'UNFIT'],
            'diastolic unfit' => [120, 110, 'UNFIT'],
            'most severe value wins' => [125, 111, 'UNFIT'],
        ];
    }

    #[DataProvider('cases')]
    public function test_assessment_uses_the_most_severe_reading(int $systolic, int $diastolic, string $expected): void
    {
        $assessment = BloodPressureAssessment::assess($systolic, $diastolic);

        $this->assertSame($expected, $assessment['status']);
        $this->assertNotSame('', $assessment['reason']);
    }
}

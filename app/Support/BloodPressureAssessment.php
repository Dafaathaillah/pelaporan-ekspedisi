<?php

namespace App\Support;

final class BloodPressureAssessment
{
    public static function assess(int $systolic, int $diastolic): array
    {
        if ($systolic >= 180 || $diastolic >= 110) {
            return ['status' => 'UNFIT', 'label' => 'Unfit', 'reason' => 'Tekanan darah sangat tinggi dan memerlukan evaluasi medis segera.'];
        }

        if ($systolic >= 160 || $diastolic >= 100) {
            return ['status' => 'UNFIT_PENDING', 'label' => 'Unfit / Pending', 'reason' => 'Tekanan darah cukup tinggi dan memerlukan evaluasi dokter.'];
        }

        if ($systolic >= 140 || $diastolic >= 90) {
            return ['status' => 'PENDING', 'label' => 'Pending', 'reason' => 'Perlu pemeriksaan atau monitoring lebih lanjut.'];
        }

        if ($systolic >= 120 || $diastolic >= 80) {
            return ['status' => 'FIT_WITH_NOTE', 'label' => 'Fit dengan Catatan Medis', 'reason' => 'Masih dapat bekerja, tetapi ada catatan atau rekomendasi kesehatan.'];
        }

        return ['status' => 'FIT', 'label' => 'Fit', 'reason' => 'Tekanan darah normal.'];
    }
}

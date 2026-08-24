<?php

namespace Database\Seeders;

use App\Models\VendorCompany;
use Illuminate\Database\Seeder;

class VendorCompanySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['BANUA ALAM SEMESTA','BAS','REGULAR','Daily, Weekly'], ['CIPTA KRIDA BAHARI','CKB','REGULAR','Daily, Weekly'],
            ['LOGISTIC ONE','LOGONE','NON_REGULAR',null], ['SERASI LOGISTIC','SELOG','NON_REGULAR',null],
            ['UNITED TRACTOR','UT','REGULAR','Daily, Weekly'], ['EXPRESS JAYA','EXA','REGULAR','Daily, Weekly'],
            ['ANGKA AGUNG LESTARI','AAL','NON_REGULAR',null], ['KAPUAS KENCANA JAYA','KKJ','REGULAR','Daily, Weekly'],
            ['PERTAMINA PATRA NIAGA','PPN','REGULAR','Daily, Weekly'], ['BADAN USAHA MILIK DESA','BUMDES','REGULAR','Daily, Weekly'],
            ['DINI MURNI ABADI','DMA','REGULAR','Daily, Weekly'], ['BOSTON','BOSTON','NON_REGULAR',null],
            ['MASABARU','MSB','REGULAR','Daily, Weekly'], ['SARANA PRIMA LESTARI','SPL','REGULAR','Daily, Weekly'],
            ['SINAR JAYA PRIMA LANGGENG','SJPL','REGULAR','Daily, Weekly'], ['INDO JAYA TEKNINDO','IJT','REGULAR','Daily, Weekly'],
            ['GAPURA','GAPURA','REGULAR','Daily, Weekly'], ['ANDALAN RANTAI LOGISTIC','ARPILOG','NON_REGULAR',null],
            ['BERSINAR JAYA PERKASA','BJP','REGULAR','Daily, Weekly'], ['DUMA NUSANTARA','DNT','REGULAR','Daily, Weekly'],
            ['SAMATOR','SMT','REGULAR','Daily, Weekly'], ['MULTIGAS','MTG','REGULAR','Daily, Weekly'],
            ['SATYA ENERGI SOLUSI','SES','NON_REGULAR',null], ['KILAN MITRA ENERGI','KME','NON_REGULAR',null],
            ['SINAR BINTANG ALBAR','SBA','REGULAR','Daily, Weekly'], ['MAJU JAYA ASRI UTAMA','MAJU','REGULAR','Daily, Weekly'],
            ['KANCA SATUI','KCS','REGULAR','Daily, Weekly'], ['AIR','PANJI','REGULAR','Daily, Weekly'],
            ['BERKAT SABAR','BS','REGULAR','Daily, Weekly'], ['KOPERASI','KPS','REGULAR','Daily, Weekly'],
            ['OLI SEFAS','WIDYATAMA','REGULAR','Daily, Weekly'], ['STAR WAGEN INDONESIA','SWI','REGULAR','Daily, Weekly'],
            ['STEEL FORCE','SF','REGULAR','Daily, Weekly'], ['ALROS','ALS','REGULAR','Daily, Weekly'],
            ['BANGUN TEHNIK BAJA','BTB','REGULAR','Daily, Weekly'],
        ];
        foreach ($rows as [$name, $abbreviation, $category, $remarks]) {
            VendorCompany::updateOrCreate(['abbreviation' => $abbreviation], compact('name', 'category') + ['remarks' => null, 'is_active' => true]);
        }
    }
}

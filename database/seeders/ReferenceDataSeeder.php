<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Generic, editable starting categories. These are common administrative
 * classifications, not official NDC data; administrators can rename,
 * deactivate or add categories in Settings.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $documentCategories = [
            ['POL', 'Policies and Procedures', 'Siyaasadaha iyo Habraacyada', null],
            ['RPT', 'Reports', 'Warbixinno', null],
            ['CTR', 'Contracts and Agreements', 'Qandaraasyo iyo Heshiisyo', null],
            ['PLN', 'Plans and Strategies', 'Qorshayaal iyo Istaraatiijiyado', null],
            ['TEC', 'Technical Documents', 'Dukumiintiyo Farsamo', null],
            ['FIN', 'Financial Documents', 'Dukumiintiyo Maaliyadeed', null],
            ['HR', 'Human Resources', 'Shaqaalaha', null],
            ['MIN', 'Meeting Records', 'Diiwaannada Shirarka', null],
            ['OTH', 'Other', 'Kale', null],
        ];
        foreach ($documentCategories as [$code, $name, $so, $retention]) {
            DocumentCategory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'name_so' => $so, 'retention_years' => $retention]);
        }

        $expenseCategories = [
            ['EQP', 'Equipment', 'Qalab'],
            ['SFT', 'Software and Licences', 'Software iyo Liisamo'],
            ['SRV', 'Professional Services', 'Adeegyo Xirfadeed'],
            ['TRV', 'Travel', 'Safar'],
            ['TRN', 'Training', 'Tababar'],
            ['OFF', 'Office Supplies', 'Agabka Xafiiska'],
            ['UTL', 'Utilities and Communications', 'Adeegyada Guud iyo Isgaarsiinta'],
            ['CON', 'Construction and Facilities', 'Dhismo iyo Xarumo'],
            ['OTH', 'Other', 'Kale'],
        ];
        foreach ($expenseCategories as [$code, $name, $so]) {
            ExpenseCategory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'name_so' => $so]);
        }

        $assetCategories = [
            ['CMP', 'Computers and Laptops', 'Kombiyuutarro iyo Laptops', 'ict'],
            ['PRN', 'Printers and Scanners', 'Daabacayaal iyo Sawir-qaadeyaal', 'ict'],
            ['PHN', 'Phones and Mobile Devices', 'Taleefannada', 'ict'],
            ['NET', 'Office Network Equipment', 'Qalabka Shabakadda Xafiiska', 'ict'],
            ['FUR', 'Furniture', 'Alaab Guri', 'furniture'],
            ['OFE', 'Office Equipment', 'Qalabka Xafiiska', 'office'],
            ['VEH', 'Vehicles', 'Gaadiid', 'vehicle'],
            ['OTH', 'Other', 'Kale', 'other'],
        ];
        foreach ($assetCategories as [$code, $name, $so, $class]) {
            AssetCategory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'name_so' => $so, 'asset_class' => $class]);
        }
    }
}

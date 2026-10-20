<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Initial reference lists. These are generic starting values that
 * administrators can rename, extend or deactivate under Reference Data.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'document' => [
                ['Policy', 'Siyaasad'], ['Procedure', 'Habraac'], ['Report', 'Warbixin'], ['Contract', 'Qandaraas'],
                ['Memorandum', 'Xusuus-qor'], ['Plan', 'Qorshe'], ['Technical Specification', 'Faahfaahin Farsamo'],
                ['Minutes', 'Qoraal Kulan'], ['Form', 'Foom'], ['Other', 'Kale'],
            ],
            'asset' => [
                ['Desktop Computer', 'Kombiyuutar Miiska'], ['Laptop', 'Laabtoob'], ['Printer', 'Daabace'],
                ['Network Equipment', 'Qalabka Shabakadda'], ['Furniture', 'Alaab Guri'], ['Vehicle', 'Gaari'],
                ['Phone / Mobile Device', 'Telefoon'], ['Projector / AV', 'Borojektar'],
                // Reserved for future data center infrastructure.
                ['Server', 'Seerfar'], ['Rack', 'Raak'], ['Storage System', 'Nidaamka Kaydinta'],
                ['Power / UPS', 'Koronto / UPS'], ['Cooling', 'Qaboojiye'], ['Other', 'Kale'],
            ],
            'expense' => [
                ['Equipment', 'Qalab'], ['Construction & Works', 'Dhismo'], ['Consultancy', 'La-talin'],
                ['Training', 'Tababar'], ['Travel', 'Safar'], ['Office Supplies', 'Agabka Xafiiska'],
                ['Software & Licences', 'Software & Shatiyo'], ['Utilities', 'Adeegyada'], ['Maintenance', 'Dayactir'],
                ['Other', 'Kale'],
            ],
            'supplier' => [
                ['ICT Equipment', 'Qalabka ICT'], ['Construction', 'Dhismo'], ['Consultancy', 'La-talin'],
                ['Office Supplies', 'Agabka Xafiiska'], ['Services', 'Adeegyo'], ['Other', 'Kale'],
            ],
            'funding_source' => [
                ['Government Budget', 'Miisaaniyadda Dowladda'], ['Development Partner', 'Deeq-bixiye'], ['Other', 'Kale'],
            ],
        ];

        foreach ($lists as $type => $items) {
            foreach ($items as $i => [$name, $so]) {
                Category::query()->firstOrCreate(
                    ['type' => $type, 'name' => $name],
                    ['name_so' => $so, 'sort_order' => ($i + 1) * 10, 'is_active' => true],
                );
            }
        }
    }
}

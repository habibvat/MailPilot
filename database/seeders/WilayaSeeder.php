<?php

namespace Database\Seeders;

use App\Models\Wilaya;
use Illuminate\Database\Seeder;

class WilayaSeeder extends Seeder
{
    /**
     * Les 58 wilayas d'Algérie (découpage de 2019) avec les graphies alternatives
     * rencontrées dans les fichiers d'entreprises.
     */
    public const WILAYAS = [
        1 => ['Adrar', []],
        2 => ['Chlef', ['Ech Cheliff', 'El Asnam', 'Cheliff']],
        3 => ['Laghouat', ['Laghouate']],
        4 => ['Oum El Bouaghi', ['OEB', 'Oum Bouaghi']],
        5 => ['Batna', []],
        6 => ['Béjaïa', ['Bejaia', 'Bgayet', 'Bougie', 'Vgayet']],
        7 => ['Biskra', []],
        8 => ['Béchar', ['Bechar']],
        9 => ['Blida', []],
        10 => ['Bouira', []],
        11 => ['Tamanrasset', ['Tamanghasset', 'Tam']],
        12 => ['Tébessa', ['Tebessa', 'Tbessa']],
        13 => ['Tlemcen', []],
        14 => ['Tiaret', ['Tihert']],
        15 => ['Tizi Ouzou', ['Tizi-Ouzou', 'Tiziouzou', 'TO']],
        16 => ['Alger', ['Algiers', 'Alger Centre', 'Al Djazair', 'El Djazair', 'Dzayer']],
        17 => ['Djelfa', []],
        18 => ['Jijel', ['Djidjel']],
        19 => ['Sétif', ['Setif', 'Stif']],
        20 => ['Saïda', ['Saida']],
        21 => ['Skikda', []],
        22 => ['Sidi Bel Abbès', ['Sidi Bel Abbes', 'Sidi Belabbes', 'SBA']],
        23 => ['Annaba', ['Bone', 'Bône']],
        24 => ['Guelma', []],
        25 => ['Constantine', ['Qacentina', 'Ksentina']],
        26 => ['Médéa', ['Medea', 'Lemdiya']],
        27 => ['Mostaganem', ['Mosta']],
        28 => ["M'Sila", ['Msila', 'M Sila']],
        29 => ['Mascara', ['Mouaskar']],
        30 => ['Ouargla', ['Wargla']],
        31 => ['Oran', ['Wahran']],
        32 => ['El Bayadh', ['El Bayad']],
        33 => ['Illizi', []],
        34 => ['Bordj Bou Arréridj', ['Bordj Bou Arreridj', 'BBA', 'Bordj']],
        35 => ['Boumerdès', ['Boumerdes']],
        36 => ['El Tarf', ['Taref', 'El Taref']],
        37 => ['Tindouf', []],
        38 => ['Tissemsilt', []],
        39 => ['El Oued', ['Oued Souf', 'Souf']],
        40 => ['Khenchela', []],
        41 => ['Souk Ahras', []],
        42 => ['Tipaza', ['Tipasa']],
        43 => ['Mila', []],
        44 => ['Aïn Defla', ['Ain Defla']],
        45 => ['Naâma', ['Naama']],
        46 => ['Aïn Témouchent', ['Ain Temouchent']],
        47 => ['Ghardaïa', ['Ghardaia']],
        48 => ['Relizane', ['Ghilizane']],
        49 => ['Timimoun', []],
        50 => ['Bordj Badji Mokhtar', ['BBM']],
        51 => ['Ouled Djellal', []],
        52 => ['Béni Abbès', ['Beni Abbes']],
        53 => ['In Salah', ['Ain Salah', 'Aïn Salah']],
        54 => ['In Guezzam', ['Ain Guezzam', 'Aïn Guezzam']],
        55 => ['Touggourt', ['Tuggurt']],
        56 => ['Djanet', []],
        57 => ["El M'Ghair", ['El Mghair', 'El Meghaier', 'El Meghaïer']],
        58 => ['El Meniaa', ['El Menia', 'El Menea', 'El Goléa', 'El Golea']],
    ];

    public function run(): void
    {
        foreach (self::WILAYAS as $code => [$name, $aliases]) {
            Wilaya::updateOrCreate(['code' => $code], ['name' => $name, 'aliases' => $aliases]);
        }
    }
}

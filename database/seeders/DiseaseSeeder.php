<?php

namespace Database\Seeders;

use App\Models\Disease;
use Illuminate\Database\Seeder;

class DiseaseSeeder extends Seeder
{
    public function run(): void
    {
        $diseases = [
            [
                'name' => 'Oídio',
                'description' => 'Enfermedad fúngica que se manifiesta como un polvo blanco sobre las hojas, tallos y flores.',
                'symptoms' => 'Manchas blancas polvorientas en hojas, deformación de hojas, amarillamiento progresivo.',
                'treatment' => 'Aplicación de fungicidas azufrados, bicarbonato de potasio, o extracto de ajo. Mejorar ventilación.',
                'prevention' => 'Evitar riego por aspersión, mantener distancia entre plantas, elegir variedades resistentes.',
                'severity' => 'medium',
            ],
            [
                'name' => 'Royas',
                'description' => 'Enfermedad fúngica que produce pústulas anaranjadas o rojizas en el envés de las hojas.',
                'symptoms' => 'Pústulas anaranjadas en hojas, marchitamiento, caída prematura de hojas.',
                'treatment' => 'Fungicidas a base de cobre, eliminación de hojas infectadas.',
                'prevention' => 'Rotación de cultivos, evitar humedad excesiva, eliminar residuos infectados.',
                'severity' => 'high',
            ],
            [
                'name' => 'Mildiu',
                'description' => 'Enfermedad fúngica que provoca manchas amarillas en el haz y crecimiento algodonoso en el envés.',
                'symptoms' => 'Manchas amarillas en hojas, moho blanco en envés, marchitamiento de tallos.',
                'treatment' => 'Fungicidas preventivos, mejora del drenaje, eliminación de partes infectadas.',
                'prevention' => 'Evitar riego nocturno, mantener plantas ventiladas, usar variedades resistentes.',
                'severity' => 'high',
            ],
            [
                'name' => 'Moniliasis',
                'description' => 'Enfermedad fúngica que afecta frutos, produciendo podredumbre blanda y moho gris.',
                'symptoms' => 'Podredumbre blanda en frutos, moho gris, frutos marchitos en la rama.',
                'treatment' => 'Eliminación de frutos infectados, aplicación de fungicidas en floración.',
                'prevention' => 'Cosechar en el momento adecuado, evitar heridas en frutos, mantener higiene.',
                'severity' => 'medium',
            ],
            [
                'name' => 'Antracnosis',
                'description' => 'Enfermedad fúngica que provoca manchas oscuras hundidas en hojas, tallos y frutos.',
                'symptoms' => 'Manchas oscuras circulares, lesiones hundidas, caída de hojas.',
                'treatment' => 'Fungicidas a base de cobre, poda de ramas infectadas.',
                'prevention' => 'Evitar salpicaduras de agua, mantener plantas sanas, usar semillas certificadas.',
                'severity' => 'medium',
            ],
            [
                'name' => 'Nematodos de raíz',
                'description' => 'Gusanos microscópicos que atacan las raíces, debilitando la planta.',
                'symptoms' => 'Marchitamiento sin causa aparente, raíces con nudosidades, crecimiento lento.',
                'treatment' => 'Nematicidas biológicos, rotación de cultivos, uso de plantas trampa.',
                'prevention' => 'Solarización del suelo, uso de variedades resistentes, compostaje bien fermentado.',
                'severity' => 'high',
            ],
        ];

        foreach ($diseases as $diseaseData) {
            Disease::create($diseaseData);
        }
    }
}

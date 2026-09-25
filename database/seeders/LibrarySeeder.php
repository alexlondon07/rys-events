<?php

namespace Database\Seeders;

use App\Models\ItemCatalog;
use App\Models\TextTemplate;
use Illuminate\Database\Seeder;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'introduction',
                'name' => 'Introducción general',
                'body_with_variables' => 'En el marco de {evento}, realizado en el municipio de {municipio}, {departamento}, entre el {fecha_inicio} y el {fecha_fin}, se ejecutaron las actividades artísticas, culturales y logísticas contratadas, orientadas a fortalecer la identidad local y brindar espacios de integración para la comunidad.',
            ],
            [
                'key' => 'description',
                'name' => 'Descripción del evento',
                'body_with_variables' => 'Durante {dias} días se llevó a cabo {evento} en el municipio de {municipio}, {departamento}, con una programación continua que incluyó presentaciones artísticas, actividades culturales y el montaje técnico y logístico requerido para cada jornada.',
            ],
            [
                'key' => 'coordination',
                'name' => 'Coordinación previa (artistas)',
                'body_with_variables' => 'Previo al desarrollo del evento se realizó un proceso de coordinación con {artista} y su equipo, en el que se verificaron los requerimientos técnicos y de hospitalidad (rider) para garantizar el correcto montaje y desarrollo de la presentación.',
            ],
            [
                'key' => 'hospitality',
                'name' => 'Hospitalidad',
                'body_with_variables' => 'Se garantizó la hospitalidad de {artista} y su equipo, incluyendo el alojamiento, la alimentación y la hidratación conforme a lo pactado, así como el acompañamiento logístico durante su permanencia en el municipio de {municipio}.',
            ],
            [
                'key' => 'conclusion',
                'name' => 'Conclusión general',
                'body_with_variables' => 'El desarrollo de {evento} en el municipio de {municipio}, {departamento}, cumplió con el objeto contractual del contrato No {contrato}, ejecutando las actividades artísticas, técnicas y logísticas previstas y aportando a la programación cultural del municipio.',
            ],
        ];

        foreach ($templates as $template) {
            TextTemplate::query()->updateOrCreate(
                ['key' => $template['key'], 'name' => $template['name']],
                ['body_with_variables' => $template['body_with_variables'], 'active' => true],
            );
        }

        $technical = [
            ['SONIDO, MICROFONERÍA Y DISTRIBUCIÓN ELÉCTRICA', 'Suministro e instalación de sistema de sonido profesional, microfónica y distribución eléctrica para el evento.'],
            ['ILUMINACIÓN ESCÉNICA', 'Montaje de iluminación escénica profesional con consola, luminarias y estructuras para el espectáculo.'],
            ['PANTALLAS LED', 'Suministro e instalación de pantallas LED para la transmisión y proyección durante el evento.'],
            ['TECHOS Y TARIMAS', 'Montaje de techos y tarimas con las dimensiones y especificaciones requeridas por el contratante.'],
            ['PERSONAL DE PRODUCCIÓN Y MONTAJE', 'Disposición de personal técnico y operativo para el montaje, la operación y el desmontaje del evento.'],
            ['BACKLINE', 'Suministro de backline (instrumentos y equipos de amplificación) para las agrupaciones participantes.'],
            ['EFECTOS Y PIROTECNIA', 'Ejecución de efectos especiales y pirotecnia con personal certificado y permisos vigentes.'],
            ['PLANTA ELÉCTRICA', 'Suministro y operación de planta eléctrica para el respaldo energético del evento.'],
            ['STREAMING Y CIRCUITO CERRADO', 'Montaje de streaming y circuito cerrado para la transmisión y monitoreo del evento.'],
            ['TRANSPORTE', 'Suministro de transporte para artistas, equipo técnico y elementos requeridos para el evento.'],
        ];

        foreach ($technical as [$category, $specification]) {
            ItemCatalog::query()->updateOrCreate(
                ['type' => 'technical', 'category_label' => $category],
                [
                    'specification' => $specification,
                    'default_narrative' => null,
                    'default_unit' => 'días',
                    'default_quantity' => 1,
                    'active' => true,
                ],
            );
        }
    }
}

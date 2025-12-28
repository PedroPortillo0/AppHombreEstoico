<?php

namespace App\Domain\Enums\NivelEstoico;

enum StoicLevel: string
{
    case PRINCIPIANTE = 'principiante';
    case BASICO_INTERMEDIO = 'basico_intermedio';
    case INTERMEDIO = 'intermedio';
    case INTERMEDIO_AVANZADO = 'intermedio_avanzado';
    case AVANZADO = 'avanzado';

    /**
     * Obtiene la etiqueta legible del nivel estoico
     */
    public function getLabel(): string
    {
        return match($this) {
            self::PRINCIPIANTE => 'Principiante',
            self::BASICO_INTERMEDIO => 'Básico Intermedio',
            self::INTERMEDIO => 'Intermedio',
            self::INTERMEDIO_AVANZADO => 'Intermedio Avanzado',
            self::AVANZADO => 'Avanzado',
        };
    }
}

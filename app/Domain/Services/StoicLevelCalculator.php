<?php

namespace App\Domain\Services;

use App\Domain\Enums\NivelEstoico\StoicLevel;

class StoicLevelCalculator
{
    private const LEVEL_THRESHOLDS = [
        StoicLevel::PRINCIPIANTE->value => 0,
        StoicLevel::BASICO_INTERMEDIO->value => 20,
        StoicLevel::INTERMEDIO->value => 50,
        StoicLevel::INTERMEDIO_AVANZADO->value => 100,
        StoicLevel::AVANZADO->value => 200,
    ];

    /**
     * Calcula el nivel estoico basado en los puntos
     */
    public function calculateLevel(int $points): StoicLevel
    {
        // Ordenar niveles de mayor a menor puntos requeridos
        $sortedLevels = [
            StoicLevel::AVANZADO->value => 200,
            StoicLevel::INTERMEDIO_AVANZADO->value => 100,
            StoicLevel::INTERMEDIO->value => 50,
            StoicLevel::BASICO_INTERMEDIO->value => 20,
            StoicLevel::PRINCIPIANTE->value => 0,
        ];

        foreach ($sortedLevels as $level => $threshold) {
            if ($points >= $threshold) {
                return StoicLevel::from($level);
            }
        }

        return StoicLevel::PRINCIPIANTE;
    }

    /**
     * Obtiene los puntos necesarios para el siguiente nivel
     */
    public function getPointsForNextLevel(int $currentPoints): ?array
    {
        $currentLevel = $this->calculateLevel($currentPoints);
        $currentThreshold = self::LEVEL_THRESHOLDS[$currentLevel->value];
        
        // Buscar el siguiente nivel
        $sortedLevels = [
            StoicLevel::PRINCIPIANTE->value => 0,
            StoicLevel::BASICO_INTERMEDIO->value => 20,
            StoicLevel::INTERMEDIO->value => 50,
            StoicLevel::INTERMEDIO_AVANZADO->value => 100,
            StoicLevel::AVANZADO->value => 200,
        ];

        foreach ($sortedLevels as $level => $threshold) {
            if ($threshold > $currentThreshold) {
                $nextLevel = StoicLevel::from($level);
                $pointsNeeded = $threshold - $currentPoints;
                return [
                    'next_level' => $nextLevel->value,
                    'next_level_label' => $nextLevel->getLabel(),
                    'points_needed' => $pointsNeeded,
                    'current_points' => $currentPoints,
                    'threshold' => $threshold
                ];
            }
        }

        // Ya está en el nivel máximo
        return null;
    }

    /**
     * Obtiene información completa del progreso del usuario
     */
    public function getProgressInfo(int $points): array
    {
        $currentLevel = $this->calculateLevel($points);
        $nextLevelInfo = $this->getPointsForNextLevel($points);

        return [
            'current_level' => $currentLevel->value,
            'current_level_label' => $currentLevel->getLabel(),
            'current_points' => $points,
            'next_level' => $nextLevelInfo,
        ];
    }
}

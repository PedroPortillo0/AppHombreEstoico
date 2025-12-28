<?php

namespace App\Application\UseCases;

use App\Domain\Enums\DatosPersonales\AgeRange;
use App\Domain\Enums\DatosPersonales\Gender;
use App\Domain\Enums\DatosPersonales\Country;
use App\Domain\Enums\Espiritualidad\ReligiousBelief;
use App\Domain\Enums\Espiritualidad\SpiritualPracticeLevel;
use App\Domain\Enums\Espiritualidad\SpiritualPracticeFrequency;
use App\Domain\Enums\DesafiosDiarios\DailyChallenge;
use App\Domain\Enums\CaminoEstoico\StoicPath;
use App\Domain\Enums\NivelEstoico\StoicLevel;

class GetQuizOptions
{
    public function execute(): array
    {
        return [
            'success' => true,
            'data' => [
                'age_ranges' => array_map(fn($case) => [
                    'value' => $case->value,
                    'label' => $case->getLabel()
                ], AgeRange::cases()),
                
                'genders' => [
                    'main' => Gender::getMainOptions(),
                    'additional' => Gender::getAdditionalOptions()
                ],
                
                
                'countries' => [
                    'main' => Country::getMainOptions(),
                    'all' => Country::getAllOptions()
                ],
                
                'religious_beliefs' => array_map(fn($case) => [
                    'value' => $case->value,
                    'label' => $case->getLabel()
                ], ReligiousBelief::cases()),
                
                'spiritual_practice_levels' => array_map(fn($case) => [
                    'value' => $case->value,
                    'label' => $case->getLabel()
                ], SpiritualPracticeLevel::cases()),
                
                'spiritual_practice_frequencies' => array_map(fn($case) => [
                    'value' => $case->value,
                    'label' => $case->getLabel()
                ], SpiritualPracticeFrequency::cases()),
                
                'daily_challenges' => array_map(fn($case) => [
                    'value' => $case->value,
                    'label' => $case->getLabel(),
                    'description' => $case->getDescription(),
                    'icon' => $case->getIcon(),
                    'color' => $case->getColor()
                ], DailyChallenge::cases()),
                
                'stoic_paths' => array_map(fn($case) => [
                    'value' => $case->value,
                    'label' => $case->getLabel(),
                    'description' => $case->getDescription(),
                    'icon' => $case->getIcon(),
                    'color' => $case->getColor()
                ], StoicPath::cases()),
                
                'stoic_levels' => array_map(fn($case) => [
                    'value' => $case->value,
                    'label' => $case->getLabel()
                ], StoicLevel::cases())
            ]
        ];
    }
}

<?php

namespace App\Application\UseCases;

use App\Models\UserChallengeCompletion;
use App\Models\UserQuizResponse;
use App\Domain\Services\StoicLevelCalculator;
use App\Domain\Ports\UserRepositoryInterface;
use Illuminate\Support\Str;
use Exception;

class CompleteChallenge
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private StoicLevelCalculator $levelCalculator
    ) {}

    public function execute(string $userId, array $challengeData): array
    {
        try {
            // Validar datos del desafío
            $this->validateChallengeData($challengeData);

            // Validar que el usuario no haya completado este desafío antes
            $existingCompletion = UserChallengeCompletion::where('user_id', $userId)
                ->where('name', $challengeData['name'])
                ->first();

            if ($existingCompletion) {
                throw new Exception('Ya has completado este desafío anteriormente. No puedes completarlo nuevamente.');
            }

            // Registrar la actividad completada
            $completion = UserChallengeCompletion::create([
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'name' => $challengeData['name'],
                'level' => $challengeData['level'],
                'objective' => $challengeData['objective'],
                'points' => 1, // Cada actividad vale 1 punto
                'completed_at' => now()
            ]);

            // Obtener usuario y actualizar puntos
            $user = $this->userRepository->findById($userId);
            if (!$user) {
                throw new Exception('Usuario no encontrado');
            }

            // Incrementar puntos
            $userModel = \App\Models\User::find($userId);
            $userModel->stoic_points = ($userModel->stoic_points ?? 0) + 1;
            $userModel->save();

            // Calcular nuevo nivel
            $newLevel = $this->levelCalculator->calculateLevel($userModel->stoic_points);
            
            // Actualizar nivel en quiz response si existe
            $quizResponse = UserQuizResponse::where('user_id', $userId)->first();
            $levelChanged = false;
            
            if ($quizResponse) {
                $oldLevel = $quizResponse->stoic_level;
                $quizResponse->stoic_level = $newLevel->value;
                $quizResponse->save();
                
                if ($oldLevel !== $newLevel->value) {
                    $levelChanged = true;
                }
            }

            // Obtener información de progreso
            $progressInfo = $this->levelCalculator->getProgressInfo($userModel->stoic_points);

            return [
                'success' => true,
                'message' => $levelChanged 
                    ? "¡Felicidades! Has subido de nivel a {$newLevel->getLabel()}" 
                    : 'Actividad completada exitosamente',
                'data' => [
                    'completion' => $completion,
                    'total_points' => $userModel->stoic_points,
                    'level_changed' => $levelChanged,
                    'current_level' => $newLevel->value,
                    'current_level_label' => $newLevel->getLabel(),
                    'progress' => $progressInfo
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function validateChallengeData(array $data): void
    {
        if (!isset($data['name']) || empty($data['name'])) {
            throw new Exception('El nombre del desafío es requerido');
        }
        
        if (!isset($data['level']) || empty($data['level'])) {
            throw new Exception('El nivel del desafío es requerido');
        }
        
        if (!isset($data['objective']) || empty($data['objective'])) {
            throw new Exception('El objetivo del desafío es requerido');
        }
        
        // Validar que el level sea válido
        $validLevels = ['principiante', 'basico_intermedio', 'intermedio', 'intermedio_avanzado', 'avanzado'];
        if (!in_array($data['level'], $validLevels)) {
            throw new Exception('El nivel del desafío no es válido');
        }
    }
}

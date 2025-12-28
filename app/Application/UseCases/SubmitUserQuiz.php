<?php

namespace App\Application\UseCases;

use App\Models\UserQuizResponse;
use App\Domain\Ports\UserRepositoryInterface;
use Illuminate\Support\Str;
use Exception;

class SubmitUserQuiz
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $userId, array $quizData): array
    {
        try {
            // Verificar si el usuario ya completó el quiz
            $existingResponse = UserQuizResponse::where('user_id', $userId)->first();
            if ($existingResponse) {
                throw new Exception('Ya has completado el quiz anteriormente. No es posible realizarlo de nuevo.');
            }

            // Validar que todos los campos requeridos estén presentes
            $this->validateQuizData($quizData);

            // Crear la respuesta del quiz
            $quizResponse = UserQuizResponse::create([
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'age_range' => $quizData['age_range'],
                'gender' => $quizData['gender'],
                'country' => $quizData['country'] ?? null,
                'religious_belief' => $quizData['religious_belief'],
                'spiritual_practice_level' => $quizData['spiritual_practice_level'],
                'spiritual_practice_frequency' => $quizData['spiritual_practice_frequency'],
                'daily_challenges' => $quizData['daily_challenges'],
                'stoic_paths' => $quizData['stoic_paths'],
                'stoic_level' => $quizData['stoic_level'] ?? null,
                'completed_at' => now()
            ]);

            // Marcar al usuario como completado en el quiz
            $user = $this->userRepository->findById($userId);
            if ($user) {
                $user->completarQuiz();
                $this->userRepository->save($user);
            }

            return [
                'success' => true,
                'message' => 'Quiz completado exitosamente',
                'data' => $quizResponse
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function validateQuizData(array $data): void
    {
        $requiredFields = [
            'age_range',
            'gender',
            'religious_belief',
            'spiritual_practice_level',
            'spiritual_practice_frequency',
            'daily_challenges',
            'stoic_paths'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("El campo '{$field}' es requerido");
            }
        }

        // Validar que daily_challenges sea un array y tenga al menos 2 elementos
        if (!is_array($data['daily_challenges']) || count($data['daily_challenges']) < 2) {
            throw new Exception('Debes seleccionar al menos 2 desafíos diarios');
        }

        // Validar que stoic_paths sea un array y tenga al menos 2 elementos
        if (!is_array($data['stoic_paths']) || count($data['stoic_paths']) < 2) {
            throw new Exception('Debes seleccionar al menos 2 caminos estoicos');
        }
    }
}

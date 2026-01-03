<?php

namespace App\Application\UseCases;

use App\Domain\Ports\UserRepositoryInterface;
use Exception;

class GetUserById
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $userId): array
    {
        try {
            if (empty(trim($userId))) {
                throw new Exception('ID de usuario es requerido');
            }

            // Validar formato del ID (debe ser UUID)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $userId)) {
                throw new Exception('ID de usuario inválido');
            }

            // Buscar usuario por ID
            $user = $this->userRepository->findById($userId);
            
            if (!$user) {
                throw new Exception('Usuario no encontrado');
            }

            return [
                'success' => true,
                'message' => 'Usuario encontrado exitosamente',
                'data' => $this->formatUserData($user)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function formatUserData($user): array
    {
        return [
            'id' => $user->getId(),
            'nombre' => $user->getNombre(),
            'apellidos' => $user->getApellidos(),
            'nombreCompleto' => $user->getNombreCompleto(),
            'email' => $user->getEmail(),
            'emailVerificado' => $user->isEmailVerificado(),
            'quizCompleted' => $user->isQuizCompleted(),
            'googleId' => $user->getGoogleId(),
            'avatar' => $user->getAvatar(),
            'authProvider' => $user->getAuthProvider(),
            'isAdmin' => $user->isAdmin(),
            'fechaCreacion' => $user->getFechaCreacion()->format('Y-m-d H:i:s')
        ];
    }
}

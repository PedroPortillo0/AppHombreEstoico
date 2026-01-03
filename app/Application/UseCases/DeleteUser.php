<?php

namespace App\Application\UseCases;

use App\Domain\Ports\UserRepositoryInterface;
use Exception;

class DeleteUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $userId): array
    {
        try {
            // Validar que el ID no esté vacío
            if (empty(trim($userId))) {
                throw new Exception('ID de usuario es requerido');
            }

            // Validar formato del ID (debe ser UUID)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $userId)) {
                throw new Exception('ID de usuario inválido');
            }

            // Verificar que el usuario existe
            $user = $this->userRepository->findById($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }

            // Eliminar el usuario
            $this->userRepository->delete($userId);

            return [
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar usuario: ' . $e->getMessage()
            ];
        }
    }
}

<?php

namespace App\Application\UseCases;

use App\Domain\Ports\UserRepositoryInterface;
use App\Models\VerificationCode;
use Exception;

class VerifyEmailWithCode
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $userId, string $code): array
    {
        try {
            // Validar que userId y code no estén vacíos
            if (empty(trim($userId))) {
                throw new Exception('ID de usuario requerido');
            }

            if (empty(trim($code))) {
                throw new Exception('Código de verificación requerido');
            }

            // Validar formato del código (debe ser 6 dígitos)
            if (!preg_match('/^[0-9]{6}$/', $code)) {
                throw new Exception('El código debe contener exactamente 6 dígitos numéricos');
            }

            // Validar formato del ID (debe ser UUID)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $userId)) {
                throw new Exception('ID de usuario inválido');
            }

            // Buscar el usuario
            $user = $this->userRepository->findById($userId);
            if (!$user) {
                throw new Exception('Usuario no encontrado');
            }

            // Verificar si el email ya está verificado
            if ($user->isEmailVerificado()) {
                return [
                    'success' => true,
                    'message' => 'El email ya ha sido verificado previamente'
                ];
            }

            // Buscar código válido
            $verificationCode = VerificationCode::findValidCode($userId, $code);
            if (!$verificationCode) {
                throw new Exception('Código inválido o expirado');
            }

            // Marcar código como usado
            $verificationCode->markAsUsed();

            // Marcar email como verificado
            $this->userRepository->update($user->getId(), ['email_verificado' => true]);

            return [
                'success' => true,
                'message' => 'Email verificado exitosamente'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

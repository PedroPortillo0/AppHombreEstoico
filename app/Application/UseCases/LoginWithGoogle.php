<?php

namespace App\Application\UseCases;

use App\Domain\Entities\User;
use App\Domain\ValueObjects\Email;
use App\Domain\Ports\UserRepositoryInterface;
use App\Domain\Ports\TokenServiceInterface;
use Illuminate\Support\Str;
use Exception;

class LoginWithGoogle
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TokenServiceInterface $tokenService
    ) {}

    /**
     * Maneja el login/registro con Google
     * 
     * @param array $googleUserData Datos del usuario de Google (id, email, name, avatar)
     * @return array
     */
    public function execute(array $googleUserData): array
    {
        try {
            // Validar datos mínimos requeridos
            if (empty($googleUserData['id']) || empty($googleUserData['email'])) {
                throw new Exception('Datos de Google incompletos');
            }

            // Buscar usuario por Google ID
            $user = $this->userRepository->findByGoogleId($googleUserData['id']);

            if (!$user) {
                // Si no existe por Google ID, buscar por email
                $user = $this->userRepository->findByEmail($googleUserData['email']);

                if ($user) {
                    // Usuario existe con email pero sin Google ID, actualizar
                    $this->userRepository->update($user->getId(), [
                        'google_id' => $googleUserData['id'],
                        'avatar' => $googleUserData['avatar'] ?? null,
                        'auth_provider' => 'google',
                        'email_verificado' => true // Google ya verifica los emails
                    ]);

                    // Obtener usuario actualizado
                    $user = $this->userRepository->findById($user->getId());
                } else {
                    // Crear nuevo usuario
                    $user = $this->createNewGoogleUser($googleUserData);
                }
            }

            // Generar token JWT
            $token = $this->tokenService->generate($user->getId());

            return [
                'success' => true,
                'message' => 'Login con Google exitoso',
                'data' => [
                    'user' => $user->toArray(),
                    'token' => $token
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Crea un nuevo usuario desde datos de Google
     */
    private function createNewGoogleUser(array $googleUserData): User
    {
        // Extraer nombre y apellidos del nombre completo
        $fullName = $googleUserData['name'] ?? '';
        $nameParts = explode(' ', trim($fullName), 2);
        $nombre = $nameParts[0] ?? 'Usuario';
        $apellidos = $nameParts[1] ?? 'Google';

        // Crear el usuario
        $user = new User(
            Str::uuid()->toString(), // id
            $nombre, // nombre
            $apellidos, // apellidos
            $googleUserData['email'], // email
            null, // password (Sin contraseña para usuarios de Google)
            true, // emailVerificado (Email verificado automáticamente)
            false, // quizCompleted (Nuevo usuario, no ha completado el quiz)
            null, // fechaCreacion (se asigna automáticamente)
            $googleUserData['id'], // googleId
            $googleUserData['avatar'] ?? null, // avatar
            'google', // authProvider
            false // isAdmin
        );

        // Guardar en la base de datos
        return $this->userRepository->save($user);
    }
}


<?php

namespace App\Application\UseCases;

use App\Domain\Entities\User;
use App\Domain\ValueObjects\Email;
use App\Domain\Ports\UserRepositoryInterface;
use App\Domain\Ports\TokenServiceInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
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

            // Validar formato del email
            if (!filter_var($googleUserData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email con formato inválido');
            }

            // Validar que el Google ID no sea demasiado largo (máximo 255 caracteres)
            if (strlen($googleUserData['id']) > 255) {
                throw new Exception('Google ID inválido');
            }

            // Validar que el email no sea demasiado largo (máximo 255 caracteres)
            if (strlen($googleUserData['email']) > 255) {
                throw new Exception('Email demasiado largo');
            }

            // Validar URL del avatar si está presente
            if (!empty($googleUserData['avatar']) && !filter_var($googleUserData['avatar'], FILTER_VALIDATE_URL)) {
                // Si el avatar no es una URL válida, ignorarlo (no es crítico)
                $googleUserData['avatar'] = null;
            }

            // Buscar usuario por Google ID
            $user = $this->userRepository->findByGoogleId($googleUserData['id']);
            $isNewUser = false;

            if (!$user) {
                // Si no existe por Google ID, buscar por email
                $user = $this->userRepository->findByEmail($googleUserData['email']);

                if ($user) {
                    // Usuario existe con email pero sin Google ID, actualizar
                    // Validar que el avatar sea una URL válida si está presente
                    $avatar = $googleUserData['avatar'] ?? null;
                    if (!empty($avatar) && !filter_var($avatar, FILTER_VALIDATE_URL)) {
                        $avatar = null;
                    }

                    $this->userRepository->update($user->getId(), [
                        'google_id' => $googleUserData['id'],
                        'avatar' => $avatar,
                        'auth_provider' => 'google',
                        'email_verificado' => true // Google ya verifica los emails
                    ]);

                    // Obtener usuario actualizado
                    $user = $this->userRepository->findById($user->getId());
                    
                    // Validar que el usuario se actualizó correctamente
                    if (!$user) {
                        throw new Exception('Error al actualizar usuario existente');
                    }
                    // Usuario existente, no es nuevo
                    $isNewUser = false;
                } else {
                    // Crear nuevo usuario
                    try {
                        $user = $this->createNewGoogleUser($googleUserData);
                        $isNewUser = true;
                    } catch (Exception $e) {
                        Log::error('Error al crear usuario con Google', [
                            'google_id' => $googleUserData['id'],
                            'email' => $googleUserData['email'],
                            'name' => $googleUserData['name'] ?? 'N/A',
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'trace' => $e->getTraceAsString()
                        ]);
                        // Preservar el mensaje original del error
                        throw new Exception('Error al crear cuenta: ' . $e->getMessage());
                    }
                }
            }

            // Generar token JWT
            $token = $this->tokenService->generate($user->getId());

            return [
                'success' => true,
                'message' => 'Login con Google exitoso',
                'data' => [
                    'user' => $user->toArray(),
                    'token' => $token,
                    'is_new_user' => $isNewUser
                ]
            ];

        } catch (Exception $e) {
            Log::error('Error en LoginWithGoogle', [
                'google_id' => $googleUserData['id'] ?? 'N/A',
                'email' => $googleUserData['email'] ?? 'N/A',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
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
        // Validar formato del email antes de crear el usuario
        if (!filter_var($googleUserData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email con formato inválido');
        }

        // Extraer nombre y apellidos del nombre completo
        $fullName = $googleUserData['name'] ?? '';
        $nameParts = explode(' ', trim($fullName), 2);
        $nombre = $nameParts[0] ?? 'Usuario';
        $apellidos = $nameParts[1] ?? 'Google';

        // Validar que el nombre y apellidos no estén vacíos
        if (empty(trim($nombre))) {
            $nombre = 'Usuario';
        }
        if (empty(trim($apellidos))) {
            $apellidos = 'Google';
        }

        // Validar longitud máxima de nombre y apellidos (255 caracteres)
        $nombre = mb_substr(trim($nombre), 0, 255);
        $apellidos = mb_substr(trim($apellidos), 0, 255);

        // Validar longitud mínima
        if (strlen($nombre) < 2) {
            $nombre = 'Usuario';
        }
        if (strlen($apellidos) < 2) {
            $apellidos = 'Google';
        }

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


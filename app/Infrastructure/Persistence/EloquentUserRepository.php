<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\User;
use App\Domain\Ports\UserRepositoryInterface;
use App\Models\User as UserModel;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function save(User $user): User
    {
        try {
            // Verificar si el usuario ya existe por ID
            $userModel = UserModel::find($user->getId());
            
            if ($userModel) {
                // Actualizar usuario existente
                $userModel->update([
                    'nombre' => $user->getNombre(),
                    'apellidos' => $user->getApellidos(),
                    'email' => $user->getEmail(),
                    'password' => $user->getPassword(),
                    'email_verificado' => $user->isEmailVerificado(),
                    'quiz_completed' => $user->isQuizCompleted(),
                    'google_id' => $user->getGoogleId(),
                    'avatar' => $user->getAvatar(),
                    'auth_provider' => $user->getAuthProvider(),
                    'is_admin' => $user->isAdmin(),
                ]);
            } else {
                // Verificar si el google_id ya existe (si el usuario tiene google_id)
                if ($user->getGoogleId()) {
                    $existingGoogleId = UserModel::where('google_id', $user->getGoogleId())->first();
                    if ($existingGoogleId) {
                        // Si el google_id ya existe, actualizar ese usuario
                        $userModel = $existingGoogleId;
                        $userModel->update([
                            'nombre' => $user->getNombre(),
                            'apellidos' => $user->getApellidos(),
                            'email' => $user->getEmail(),
                            'password' => $user->getPassword(),
                            'email_verificado' => $user->isEmailVerificado(),
                            'quiz_completed' => $user->isQuizCompleted(),
                            'avatar' => $user->getAvatar(),
                            'auth_provider' => $user->getAuthProvider(),
                            'is_admin' => $user->isAdmin(),
                        ]);
                        $userModel->refresh();
                        return $this->toDomainEntity($userModel);
                    }
                }

                // Verificar si el email ya existe en otro usuario antes de crear
                $existingEmail = UserModel::where('email', $user->getEmail())->first();
                if ($existingEmail) {
                    // Si el email existe en otro usuario, verificar si está verificado
                    if ($existingEmail->email_verificado) {
                        throw new Exception('El email ya está registrado y verificado');
                    }
                    // Si no está verificado, actualizar ese usuario existente (mantener su ID original)
                    $userModel = $existingEmail;
                    $userModel->update([
                        'nombre' => $user->getNombre(),
                        'apellidos' => $user->getApellidos(),
                        'email' => $user->getEmail(),
                        'password' => $user->getPassword(),
                        'email_verificado' => $user->isEmailVerificado(),
                        'quiz_completed' => $user->isQuizCompleted(),
                        'google_id' => $user->getGoogleId(),
                        'avatar' => $user->getAvatar(),
                        'auth_provider' => $user->getAuthProvider(),
                        'is_admin' => $user->isAdmin(),
                    ]);
                } else {
                    // Crear nuevo usuario - incluir stoic_points explícitamente
                    $userModel = UserModel::create([
                        'id' => $user->getId(),
                        'nombre' => $user->getNombre(),
                        'apellidos' => $user->getApellidos(),
                        'email' => $user->getEmail(),
                        'password' => $user->getPassword(),
                        'email_verificado' => $user->isEmailVerificado(),
                        'quiz_completed' => $user->isQuizCompleted(),
                        'google_id' => $user->getGoogleId(),
                        'avatar' => $user->getAvatar(),
                        'auth_provider' => $user->getAuthProvider(),
                        'is_admin' => $user->isAdmin(),
                        'stoic_points' => 0, // Incluir explícitamente
                        'created_at' => $user->getFechaCreacion()
                    ]);
                }
            }

            // Recargar el modelo desde la base de datos para obtener todos los campos actualizados
            $userModel->refresh();

            return $this->toDomainEntity($userModel);

        } catch (Exception $e) {
            // Log del error para debugging
            Log::error('Error al guardar usuario', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Si el error menciona "Duplicate entry", verificar si es por email o google_id
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'email') || str_contains($e->getMessage(), 'google_id')) {
                // Verificar si es por google_id duplicado
                if ($user->getGoogleId()) {
                    $existingGoogleId = UserModel::where('google_id', $user->getGoogleId())->first();
                    if ($existingGoogleId) {
                        // Si el google_id ya existe, actualizar ese usuario
                        $existingGoogleId->update([
                            'nombre' => $user->getNombre(),
                            'apellidos' => $user->getApellidos(),
                            'email' => $user->getEmail(),
                            'password' => $user->getPassword(),
                            'email_verificado' => $user->isEmailVerificado(),
                            'quiz_completed' => $user->isQuizCompleted(),
                            'avatar' => $user->getAvatar(),
                            'auth_provider' => $user->getAuthProvider(),
                            'is_admin' => $user->isAdmin(),
                        ]);
                        $existingGoogleId->refresh();
                        return $this->toDomainEntity($existingGoogleId);
                    }
                }

                // Verificar si es por email duplicado
                $existingEmail = UserModel::where('email', $user->getEmail())->first();
                if ($existingEmail && $existingEmail->email_verificado) {
                    throw new Exception('El email ya está registrado y verificado');
                }
                // Si no está verificado, permitir la actualización (no lanzar error)
                if ($existingEmail && !$existingEmail->email_verificado) {
                    // Intentar actualizar el usuario existente
                    $existingEmail->update([
                        'nombre' => $user->getNombre(),
                        'apellidos' => $user->getApellidos(),
                        'password' => $user->getPassword(),
                        'email_verificado' => $user->isEmailVerificado(),
                        'quiz_completed' => $user->isQuizCompleted(),
                        'google_id' => $user->getGoogleId(),
                        'avatar' => $user->getAvatar(),
                        'auth_provider' => $user->getAuthProvider(),
                        'is_admin' => $user->isAdmin(),
                    ]);
                    $existingEmail->refresh();
                    return $this->toDomainEntity($existingEmail);
                }
                throw new Exception('El email ya está registrado');
            }
            throw new Exception('Error al guardar usuario: ' . $e->getMessage());
        }
    }

    public function findByEmail(string $email): ?User
    {
        try {
            $userModel = UserModel::where('email', $email)->first();
            return $userModel ? $this->toDomainEntity($userModel) : null;

        } catch (Exception $e) {
            throw new Exception('Error al buscar usuario por email: ' . $e->getMessage());
        }
    }

    public function findById(string $id): ?User
    {
        try {
            $userModel = UserModel::where('id', $id)->first();
            return $userModel ? $this->toDomainEntity($userModel) : null;

        } catch (Exception $e) {
            throw new Exception('Error al buscar usuario por ID: ' . $e->getMessage());
        }
    }

    public function findByGoogleId(string $googleId): ?User
    {
        try {
            $userModel = UserModel::where('google_id', $googleId)->first();
            return $userModel ? $this->toDomainEntity($userModel) : null;

        } catch (Exception $e) {
            throw new Exception('Error al buscar usuario por Google ID: ' . $e->getMessage());
        }
    }

    public function update(string $id, array $userData): ?User
    {
        try {
            $userModel = UserModel::where('id', $id)->first();
            
            if (!$userModel) {
                return null;
            }

            $userModel->update($userData);
            return $this->toDomainEntity($userModel);

        } catch (Exception $e) {
            throw new Exception('Error al actualizar usuario: ' . $e->getMessage());
        }
    }

    public function delete(string $id): bool
    {
        try {
            return UserModel::where('id', $id)->delete() > 0;

        } catch (Exception $e) {
            throw new Exception('Error al eliminar usuario: ' . $e->getMessage());
        }
    }

    public function exists(string $email): bool
    {
        try {
            return UserModel::where('email', $email)->exists();

        } catch (Exception $e) {
            throw new Exception('Error al verificar existencia del usuario: ' . $e->getMessage());
        }
    }

    private function toDomainEntity(UserModel $userModel): User
    {
        return new User(
            $userModel->id,
            $userModel->nombre,
            $userModel->apellidos,
            $userModel->email,
            $userModel->password,
            $userModel->email_verificado,
            $userModel->quiz_completed ?? false,
            new DateTime($userModel->created_at),
            $userModel->google_id,
            $userModel->avatar,
            $userModel->auth_provider ?? 'local',
            $userModel->is_admin ?? false
        );
    }

    public function getAllWithPagination(int $page, int $limit): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // Obtener total de usuarios
            $total = UserModel::count();
            
            // Obtener usuarios con paginación
            $userModels = UserModel::orderBy('created_at', 'desc')
                                  ->offset($offset)
                                  ->limit($limit)
                                  ->get();
            
            // Convertir a entidades de dominio
            $users = $userModels->map(function($userModel) {
                return $this->toDomainEntity($userModel);
            })->toArray();

            return [
                'users' => $users,
                'total' => $total
            ];

        } catch (Exception $e) {
            throw new Exception('Error al obtener usuarios: ' . $e->getMessage());
        }
    }
}

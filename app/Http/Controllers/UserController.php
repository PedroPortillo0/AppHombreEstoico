<?php

namespace App\Http\Controllers;

use App\Application\UseCases\RegisterUser;
use App\Application\UseCases\LoginUser;
use App\Application\UseCases\VerifyEmail;
use App\Application\UseCases\VerifyEmailWithCode;
use App\Application\UseCases\RequestPasswordReset;
use App\Application\UseCases\ResetPassword;
use App\Application\UseCases\GetUserById;
use App\Application\UseCases\GetAllUsers;
use App\Application\UseCases\DeleteUser;
use App\Models\UserQuizResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct(
        private RegisterUser $registerUser,
        private LoginUser $loginUser,
        private VerifyEmail $verifyEmail,
        private VerifyEmailWithCode $verifyEmailWithCode,
        private RequestPasswordReset $requestPasswordReset,
        private ResetPassword $resetPassword,
        private GetUserById $getUserById,
        private GetAllUsers $getAllUsers,
        private DeleteUser $deleteUser
        ) {}

        /**
         * Obtener usuario autenticado desde el middleware
         */
        private function getAuthenticatedUser(Request $request)
        {
            return $request->attributes->get('authenticated_user');
        }

        /**
         * Obtener payload del token desde el middleware
         */
        private function getTokenPayload(Request $request): ?array
        {
            return $request->attributes->get('token_payload');
        }

        public function register(Request $request): JsonResponse
    {
        // Validación de entrada
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:255',
            'apellidos' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6|same:password'
        ], [
            'nombre.required' => 'El nombre es requerido',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres',
            'apellidos.required' => 'Los apellidos son requeridos',
            'apellidos.min' => 'Los apellidos deben tener al menos 2 caracteres',
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe tener un formato válido',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'confirm_password.required' => 'La confirmación de contraseña es requerida',
            'confirm_password.min' => 'La confirmación de contraseña debe tener al menos 6 caracteres',
            'confirm_password.same' => 'Las contraseñas no coinciden'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->registerUser->execute($request->all());

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    public function login(Request $request): JsonResponse
    {
        // Validación de entrada
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:1'
        ], [
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe tener un formato válido',
            'email.max' => 'El email no puede exceder 255 caracteres',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña no puede estar vacía'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->loginUser->execute($request->only(['email', 'password']));

        return response()->json($result, $result['success'] ? 200 : 401);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (empty($token) || !is_string($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token de verificación requerido'
            ], 400);
        }

        $result = $this->verifyEmail->execute($token);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function verifyEmailWithCode(Request $request): JsonResponse
    {
        // Validación de entrada
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string|uuid',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/'
        ], [
            'user_id.required' => 'El ID de usuario es requerido',
            'user_id.uuid' => 'El ID de usuario debe ser un UUID válido',
            'code.required' => 'El código de verificación es requerido',
            'code.size' => 'El código debe tener exactamente 6 dígitos',
            'code.regex' => 'El código debe contener solo números'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->verifyEmailWithCode->execute(
            $request->input('user_id'),
            $request->input('code')
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        // Validación de entrada
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255'
        ], [
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe tener un formato válido',
            'email.max' => 'El email no puede exceder 255 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->requestPasswordReset->execute($request->input('email'));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        // Validación de entrada
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            'new_password' => 'required|string|min:6|max:255'
        ], [
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe tener un formato válido',
            'email.max' => 'El email no puede exceder 255 caracteres',
            'code.required' => 'El código de verificación es requerido',
            'code.size' => 'El código debe tener exactamente 6 dígitos',
            'code.regex' => 'El código debe contener solo números',
            'new_password.required' => 'La nueva contraseña es requerida',
            'new_password.min' => 'La nueva contraseña debe tener al menos 6 caracteres',
            'new_password.max' => 'La nueva contraseña no puede exceder 255 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->resetPassword->execute(
            $request->input('email'),
            $request->input('code'),
            $request->input('new_password')
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function getUser(string $id): JsonResponse
    {
        // Validar formato del ID (debe ser UUID)
        if (empty($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ], 400);
        }

        $result = $this->getUserById->execute($id);
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function getAllUsers(Request $request): JsonResponse
    {
        // Validar parámetros de paginación
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100'
        ], [
            'page.integer' => 'La página debe ser un número entero',
            'page.min' => 'La página debe ser mayor a 0',
            'limit.integer' => 'El límite debe ser un número entero',
            'limit.min' => 'El límite debe ser mayor a 0',
            'limit.max' => 'El límite no puede exceder 100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        // Obtener parámetros de paginación de la query string
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 10);

        $result = $this->getAllUsers->execute($page, $limit);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }

        public function deleteUser(string $id): JsonResponse
        {
            // Validar formato del ID (debe ser UUID)
            if (empty($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de usuario inválido'
                ], 400);
            }

            $result = $this->deleteUser->execute($id);
            
            return response()->json($result, $result['success'] ? 200 : 404);
        }

        /**
         * Obtener información del usuario autenticado
         */
        public function me(Request $request): JsonResponse
        {
            $user = $this->getAuthenticatedUser($request);
            $payload = $this->getTokenPayload($request);
            
            // Validar que el usuario y payload no sean null
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            if (!$payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido'
                ], 401);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Usuario autenticado obtenido exitosamente',
                'data' => [
                    'user' => $user->toArray(),
                    'token_info' => [
                        'user_id' => $payload['user_id'] ?? null,
                        'expires_at' => $payload['exp'] ?? null,
                        'issued_at' => $payload['iat'] ?? null
                    ]
                ]
            ], 200);
        }

        /**
         * Actualizar información del quiz del usuario autenticado
         * Permite editar todos los campos del quiz:
         * - age_range, gender, country, religious_belief
         * - spiritual_practice_level, spiritual_practice_frequency
         * - daily_challenges, stoic_paths, stoic_level
         */
        public function updateQuizInfo(Request $request): JsonResponse
        {
            $user = $this->getAuthenticatedUser($request);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.'
                ], 401);
            }

            // Validar que el usuario tenga un quiz completado
            $quizResponse = UserQuizResponse::where('user_id', $user->getId())->first();
            
            if (!$quizResponse) {
                return response()->json([
                    'success' => false,
                    'message' => 'No has completado el quiz. Completa el quiz primero para poder editar tu información.'
                ], 404);
            }

            // Validación de campos opcionales (solo los que se envíen)
            $validator = Validator::make($request->all(), [
                'age_range' => 'nullable|string|max:255',
                'gender' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'religious_belief' => 'nullable|string|max:255',
                'spiritual_practice_level' => 'nullable|string|max:255',
                'spiritual_practice_frequency' => 'nullable|string|max:255',
                'daily_challenges' => 'nullable|array|min:2',
                'daily_challenges.*' => 'string|max:255',
                'stoic_paths' => 'nullable|array|min:2',
                'stoic_paths.*' => 'string|max:255',
                'stoic_level' => 'nullable|string|in:principiante,basico_intermedio,intermedio,intermedio_avanzado,avanzado',
            ], [
                'age_range.string' => 'El rango de edad debe ser un texto válido',
                'gender.string' => 'El género debe ser un texto válido',
                'country.string' => 'El país debe ser un texto válido',
                'religious_belief.string' => 'La creencia religiosa debe ser un texto válido',
                'spiritual_practice_level.string' => 'El nivel de práctica espiritual debe ser un texto válido',
                'spiritual_practice_frequency.string' => 'La frecuencia de práctica espiritual debe ser un texto válido',
                'daily_challenges.array' => 'Los desafíos diarios deben ser un array',
                'daily_challenges.min' => 'Debes seleccionar al menos 2 desafíos diarios',
                'daily_challenges.*.string' => 'Cada desafío diario debe ser un texto válido',
                'stoic_paths.array' => 'Los caminos estoicos deben ser un array',
                'stoic_paths.min' => 'Debes seleccionar al menos 2 caminos estoicos',
                'stoic_paths.*.string' => 'Cada camino estoico debe ser un texto válido',
                'stoic_level.string' => 'El nivel estoico debe ser un texto válido',
                'stoic_level.in' => 'El nivel estoico debe ser uno de: principiante, basico_intermedio, intermedio, intermedio_avanzado, avanzado',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Actualizar solo los campos que se envíen
            $updateData = [];
            
            if ($request->has('age_range')) {
                $updateData['age_range'] = $request->input('age_range');
            }
            
            if ($request->has('gender')) {
                $updateData['gender'] = $request->input('gender');
            }
            
            if ($request->has('country')) {
                $updateData['country'] = $request->input('country');
            }
            
            if ($request->has('religious_belief')) {
                $updateData['religious_belief'] = $request->input('religious_belief');
            }

            if ($request->has('spiritual_practice_level')) {
                $updateData['spiritual_practice_level'] = $request->input('spiritual_practice_level');
            }

            if ($request->has('spiritual_practice_frequency')) {
                $updateData['spiritual_practice_frequency'] = $request->input('spiritual_practice_frequency');
            }

            if ($request->has('daily_challenges')) {
                $updateData['daily_challenges'] = $request->input('daily_challenges');
            }

            if ($request->has('stoic_paths')) {
                $updateData['stoic_paths'] = $request->input('stoic_paths');
            }

            if ($request->has('stoic_level')) {
                $updateData['stoic_level'] = $request->input('stoic_level');
            }

            // Si no se envía ningún campo para actualizar
            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay campos para actualizar. Envía al menos uno de los siguientes: age_range, gender, country, religious_belief, spiritual_practice_level, spiritual_practice_frequency, daily_challenges, stoic_paths, stoic_level'
                ], 400);
            }

            // Actualizar el quiz response
            $quizResponse->update($updateData);
            
            // Recargar el modelo para obtener los valores actualizados
            $quizResponse->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Información del quiz actualizada correctamente.',
                'data' => [
                    'age_range' => $quizResponse->age_range,
                    'gender' => $quizResponse->gender,
                    'country' => $quizResponse->country,
                    'religious_belief' => $quizResponse->religious_belief,
                    'spiritual_practice_level' => $quizResponse->spiritual_practice_level,
                    'spiritual_practice_frequency' => $quizResponse->spiritual_practice_frequency,
                    'daily_challenges' => $quizResponse->daily_challenges,
                    'stoic_paths' => $quizResponse->stoic_paths,
                    'stoic_level' => $quizResponse->stoic_level,
                ]
            ], 200);
        }
}

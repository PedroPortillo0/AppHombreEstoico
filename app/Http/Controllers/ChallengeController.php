<?php

namespace App\Http\Controllers;

use App\Application\UseCases\CompleteChallenge;
use App\Domain\Services\StoicLevelCalculator;
use App\Models\UserChallengeCompletion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ChallengeController extends Controller
{
    public function __construct(
        private CompleteChallenge $completeChallenge,
        private StoicLevelCalculator $levelCalculator
    ) {}

    /**
     * Registrar que un desafío fue completado
     */
    public function complete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'level' => 'required|string|in:principiante,basico_intermedio,intermedio,intermedio_avanzado,avanzado',
            'objective' => 'required|string',
        ], [
            'name.required' => 'El nombre del desafío es requerido',
            'level.required' => 'El nivel del desafío es requerido',
            'level.in' => 'El nivel debe ser uno de: principiante, basico_intermedio, intermedio, intermedio_avanzado, avanzado',
            'objective.required' => 'El objetivo del desafío es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $this->getAuthenticatedUserId($request);
        $result = $this->completeChallenge->execute($userId, $request->all());

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Obtener progreso del usuario
     */
    public function getProgress(Request $request): JsonResponse
    {
        $userId = $this->getAuthenticatedUserId($request);
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $points = $user->stoic_points ?? 0;
        $progressInfo = $this->levelCalculator->getProgressInfo($points);

        return response()->json([
            'success' => true,
            'data' => $progressInfo
        ], 200);
    }

    /**
     * Obtener historial de actividades completadas
     */
    public function getHistory(Request $request): JsonResponse
    {
        $userId = $this->getAuthenticatedUserId($request);
        
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);
        
        $completions = UserChallengeCompletion::where('user_id', $userId)
            ->orderBy('completed_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $completions
        ], 200);
    }

    /**
     * Obtener usuario autenticado desde el middleware
     */
    private function getAuthenticatedUserId(Request $request): string
    {
        $user = $request->attributes->get('authenticated_user');
        if (!$user) {
            throw new \Exception('Usuario no autenticado');
        }
        return $user->getId();
    }
}


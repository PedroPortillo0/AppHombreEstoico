<?php

namespace App\Http\Controllers;

use App\Application\UseCases\GetDailyQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyQuoteController extends Controller
{
    public function __construct(
        private GetDailyQuote $getDailyQuote
    ) {}

    /**
     * Obtiene la frase del día (versión simple para dashboard)
     * Si el usuario está autenticado y tiene quiz completo, devuelve frase personalizada
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDailyQuote(Request $request): JsonResponse
    {
        // Intentar obtener usuario autenticado (puede ser null si no está autenticado)
        $user = $request->attributes->get('authenticated_user');
        $userId = $user ? $user->getId() : null;

        $result = $this->getDailyQuote->execute(includeDetail: false, userId: $userId);
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Obtiene el detalle completo de la frase del día
     * Si el usuario está autenticado y tiene quiz completo, devuelve frase personalizada
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDailyQuoteDetail(Request $request): JsonResponse
    {
        // Intentar obtener usuario autenticado (puede ser null si no está autenticado)
        $user = $request->attributes->get('authenticated_user');
        $userId = $user ? $user->getId() : null;

        $result = $this->getDailyQuote->execute(includeDetail: true, userId: $userId);
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Obtiene todas las frases (para testing o admin)
     * 
     * @return JsonResponse
     */
    public function getAllQuotes(): JsonResponse
    {
        $result = $this->getDailyQuote->getAllQuotes();
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }

        /**
     * Crear una nueva frase del día
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'quote' => 'required|string|min:10',
                'author' => 'required|string|min:2',
                'category' => 'required|string|in:Virtud,Sabiduría,Justicia,Moderación,Coraje,Resiliencia,Aceptación,Control',
                'day_of_year' => 'required|integer|min:1|max:366|unique:daily_quotes,day_of_year',
                'is_active' => 'boolean'
            ]);

            $dailyQuote = \App\Models\DailyQuote::create([
                'quote' => $validated['quote'],
                'author' => $validated['author'],
                'category' => $validated['category'],
                'day_of_year' => $validated['day_of_year'],
                'is_active' => $validated['is_active'] ?? true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Frase creada exitosamente',
                'data' => $dailyQuote
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la frase: ' . $e->getMessage()
            ], 500);
        }
    }
}


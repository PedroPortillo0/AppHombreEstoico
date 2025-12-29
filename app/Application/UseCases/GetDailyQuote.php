<?php

namespace App\Application\UseCases;

use App\Domain\Ports\DailyQuoteRepositoryInterface;
use App\Application\UseCases\GeneratePersonalizedQuoteExplanation;
use App\Domain\Ports\UserRepositoryInterface;
use App\Models\User as UserModel;
use App\Models\UserQuizResponse;
use App\Models\UserPersonalizedQuote;
use Carbon\Carbon;
use Exception;

class GetDailyQuote
{
    private DailyQuoteRepositoryInterface $repository;
    private GeneratePersonalizedQuoteExplanation $personalizeQuote;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        DailyQuoteRepositoryInterface $repository,
        GeneratePersonalizedQuoteExplanation $personalizeQuote,
        UserRepositoryInterface $userRepository
    ) {
        $this->repository = $repository;
        $this->personalizeQuote = $personalizeQuote;
        $this->userRepository = $userRepository;
    }

    /**
     * Obtiene la frase del día basada en el día del año
     * Si el usuario tiene quiz completo, genera una frase personalizada con IA
     *
     * @param bool $includeDetail Si debe incluir información completa
     * @param string|null $userId ID del usuario autenticado (opcional)
     * @return array
     */
    public function execute(bool $includeDetail = false, ?string $userId = null): array
    {
        try {
            // Calcular el día del año (1-366)
            $dayOfYear = (int) date('z') + 1; // date('z') retorna 0-365, lo convertimos a 1-366

            // Buscar la frase para este día
            $quoteEntity = $this->repository->findByDayOfYear($dayOfYear);

            if (!$quoteEntity) {
                throw new Exception('No hay frase disponible para el día de hoy');
            }

            // Preparar datos de la frase del día
            $dailyQuoteData = [
                'quote' => $quoteEntity->getQuote(),
                'author' => $quoteEntity->getAuthor(),
                'category' => $quoteEntity->getCategory(),
            ];

            // Verificar si el usuario tiene quiz completo Y suscripción activa para generar personalización
            if ($userId) {
                // Usar el modelo Eloquent directamente para verificar la suscripción activa
                $userModel = UserModel::find($userId);
                
                // Debug logs
                \Illuminate\Support\Facades\Log::info('GetDailyQuote - Checking personalization requirements', [
                    'userId' => $userId,
                    'userFound' => $userModel ? 'yes' : 'no',
                    'quizCompleted' => $userModel ? $userModel->quiz_completed : 'N/A',
                    'hasActiveSubscription' => $userModel ? $userModel->hasActiveSubscription() : 'N/A'
                ]);
                
                // IMPORTANTE: Se requiere quiz completo Y suscripción activa para frases personalizadas
                if ($userModel && $userModel->quiz_completed && $userModel->hasActiveSubscription()) {
                    $today = Carbon::today()->toDateString();
                    
                    \Illuminate\Support\Facades\Log::info('GetDailyQuote - User qualifies for personalized quote', [
                        'userId' => $userId,
                        'date' => $today
                    ]);
                    
                    // Verificar si ya existe una frase personalizada para hoy
                    $existingPersonalizedQuote = UserPersonalizedQuote::where('user_id', $userId)
                        ->where('date', $today)
                        ->first();
                    
                    if ($existingPersonalizedQuote) {
                        // Si ya existe, devolverla sin generar nueva (ahorra tokens)
                        $responseData = [
                            'id' => $existingPersonalizedQuote->original_quote_id ?? $quoteEntity->getId(),
                            'quote' => $existingPersonalizedQuote->personalized_quote,
                            'author' => $existingPersonalizedQuote->original_author ?? $quoteEntity->getAuthor(),
                            'category' => $existingPersonalizedQuote->original_category ?? $quoteEntity->getCategory(),
                            'explanation' => $existingPersonalizedQuote->explanation,
                            'date' => $existingPersonalizedQuote->date->toDateString(),
                            'day_of_year' => $existingPersonalizedQuote->day_of_year,
                            'is_personalized' => true
                        ];
                        
                        // Si se pide detalle, incluir información adicional
                        if ($includeDetail) {
                            $responseData['is_active'] = $quoteEntity->isActive();
                        }
                        
                        return [
                            'success' => true,
                            'data' => $responseData
                        ];
                    }
                    
                    // Si no existe, generar una nueva con IA
                    $userQuiz = UserQuizResponse::where('user_id', $userId)->first();
                    
                    if ($userQuiz) {
                        try {
                            // Generar frase personalizada con IA
                            $personalizedResult = $this->personalizeQuote->execute($dailyQuoteData, $userQuiz);
                            
                            if ($personalizedResult['success']) {
                                // Guardar la frase personalizada en la base de datos
                                UserPersonalizedQuote::updateOrCreate(
                                    [
                                        'user_id' => $userId,
                                        'date' => $today,
                                    ],
                                    [
                                        'personalized_quote' => $personalizedResult['data']['personalized_quote'],
                                        'explanation' => $personalizedResult['data']['explanation'],
                                        'original_quote_id' => $quoteEntity->getId(),
                                        'day_of_year' => $dayOfYear,
                                        'original_author' => $quoteEntity->getAuthor(),
                                        'original_category' => $quoteEntity->getCategory(),
                                    ]
                                );
                                
                                // Retornar la frase personalizada con estructura consistente
                                return [
                                    'success' => true,
                                    'data' => [
                                        'id' => $quoteEntity->getId(),
                                        'quote' => $personalizedResult['data']['personalized_quote'],
                                        'author' => $personalizedResult['data']['original_author'],
                                        'category' => $personalizedResult['data']['original_category'],
                                        'explanation' => $personalizedResult['data']['explanation'],
                                        'date' => $today,
                                        'day_of_year' => $dayOfYear,
                                        'is_personalized' => true
                                    ]
                                ];
                            }
                        } catch (Exception $e) {
                            // Si hay error generando la personalización, loguear y continuar con frase normal
                            \Illuminate\Support\Facades\Log::error('Error generando frase personalizada: ' . $e->getMessage());
                            // Continuar para devolver frase normal
                        }
                    }
                }
            }

            // Si no tiene quiz completo o hay error, devolver frase normal
            // Preparar respuesta según si se pide detalle o no
            if ($includeDetail) {
                return [
                    'success' => true,
                    'data' => [
                        'id' => $quoteEntity->getId(),
                        'quote' => $quoteEntity->getQuote(),
                        'author' => $quoteEntity->getAuthor(),
                        'category' => $quoteEntity->getCategory(),
                        'date' => date('Y-m-d'),
                        'day_of_year' => $dayOfYear,
                        'is_active' => $quoteEntity->isActive(),
                        'is_personalized' => false
                    ]
                ];
            }

            // Respuesta simple para el dashboard
            return [
                'success' => true,
                'data' => [
                    'id' => $quoteEntity->getId(),
                    'quote' => $quoteEntity->getQuote(),
                    'author' => $quoteEntity->getAuthor(),
                    'category' => $quoteEntity->getCategory(),
                    'date' => date('Y-m-d'),
                    'is_personalized' => false
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener la frase del día: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene todas las frases (útil para testing o admin)
     * 
     * @return array
     */
    public function getAllQuotes(): array
    {
        try {
            $quotes = $this->repository->findAllActive();

            $quotesArray = array_map(function ($quote) {
                return $quote->toArray();
            }, $quotes);

            return [
                'success' => true,
                'data' => $quotesArray,
                'total' => count($quotesArray)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener las frases: ' . $e->getMessage()
            ];
        }
    }
}


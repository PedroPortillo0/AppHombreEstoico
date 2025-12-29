<?php

namespace App\Infrastructure\Services;

use App\Domain\Ports\AIServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AIService implements AIServiceInterface
{
    private string $provider;
    private string $apiKey;
    private string $baseUrl;
    private ?string $projectId;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'gemini');
        $this->apiKey = config('ai.api_key');
        $this->projectId = config('ai.project_id');

        if (empty($this->apiKey)) {
            throw new Exception('AI API Key no configurada. Verifica tu archivo .env');
        }

        // Establecer base_url con valor por defecto según el proveedor si no está configurado
        $this->baseUrl = config('ai.base_url');
        if (empty($this->baseUrl)) {
            $this->baseUrl = $this->getDefaultBaseUrl($this->provider);
        }
    }

    /**
     * Obtiene la URL base por defecto según el proveedor
     */
    private function getDefaultBaseUrl(string $provider): string
    {
        return match($provider) {
            'openai' => 'https://api.openai.com/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            default => 'https://api.openai.com/v1', // Fallback a OpenAI
        };
    }

    public function generateText(string $prompt, array $options = []): string
    {
        try {
            $response = $this->makeRequest($prompt, $options);
            return $this->extractTextFromResponse($response);
        } catch (Exception $e) {
            Log::error('Error generando texto con IA: ' . $e->getMessage());
            throw new Exception('Error al generar contenido con IA: ' . $e->getMessage());
        }
    }

    public function generateBatch(array $prompts, array $options = []): array
    {
        $results = [];
        
        foreach ($prompts as $index => $prompt) {
            try {
                $results[] = $this->generateText($prompt, $options);
                // Pequeña pausa para evitar rate limiting
                if ($index < count($prompts) - 1) {
                    usleep(500000); // 0.5 segundos
                }
            } catch (Exception $e) {
                Log::error("Error generando texto en batch (índice {$index}): " . $e->getMessage());
                $results[] = null; // O podrías lanzar excepción
            }
        }

        return $results;
    }

    private function makeRequest(string $prompt, array $options = []): array
    {
        $method = 'make' . ucfirst($this->provider) . 'Request';
        
        if (method_exists($this, $method)) {
            return $this->$method($prompt, $options);
        }

        // Fallback a método genérico
        return $this->makeGenericRequest($prompt, $options);
    }

    /**
     * Request específico para Google Gemini
     */
    private function makeGeminiRequest(string $prompt, array $options = []): array
    {
        // Usar gemini-2.0-flash como modelo por defecto (más reciente)
        $model = $options['model'] ?? 'gemini-2.0-flash';
        $url = $this->baseUrl . '/models/' . $model . ':generateContent';
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.9,
                'topK' => $options['top_k'] ?? 40,
                'topP' => $options['top_p'] ?? 0.95,
                'maxOutputTokens' => $options['max_tokens'] ?? 1024,
            ]
        ];

        // La API key debe enviarse como header X-goog-api-key, no como query parameter
        $http = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-goog-api-key' => $this->apiKey,
        ]);

        // En desarrollo, permitir desactivar verificación SSL si está configurado
        if (config('ai.verify_ssl', true) === false) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($url, $payload);

        if (!$response->successful()) {
            throw new Exception('Error en API de Gemini: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Request específico para OpenAI
     */
    private function makeOpenaiRequest(string $prompt, array $options = []): array
    {
        $url = $this->baseUrl . '/chat/completions';
        
        $payload = [
            'model' => $options['model'] ?? config('ai.openai_model'),
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.9,
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ];

        $http = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ]);

        // En desarrollo, permitir desactivar verificación SSL si está configurado
        if (config('ai.verify_ssl', true) === false) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($url, $payload);

        if (!$response->successful()) {
            throw new Exception('Error en API de OpenAI: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Request genérico para otras APIs (fallback)
     */
    private function makeGenericRequest(string $prompt, array $options = []): array
    {
        $url = $this->baseUrl . '/chat/completions';
        
        $payload = [
            'model' => $options['model'] ?? config('ai.openai_model'),
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.9,
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post($url, $payload);

        if (!$response->successful()) {
            throw new Exception('Error en API de IA: ' . $response->body());
        }

        return $response->json();
    }

    private function extractTextFromResponse(array $response): string
    {
        // Extracción para Gemini
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($response['candidates'][0]['content']['parts'][0]['text']);
        }

        // Extracción para OpenAI/otros formatos estándar
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }

        // Fallback: buscar cualquier campo 'text' o 'content'
        if (isset($response['text'])) {
            return trim($response['text']);
        }

        if (isset($response['content'])) {
            return trim($response['content']);
        }

        throw new Exception('No se pudo extraer el texto de la respuesta de la IA');
    }
}
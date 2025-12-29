<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminBookController extends Controller
{
    /**
     * Muestra el formulario para subir libros
     */
    public function index()
    {
        return view('admin.books.index');
    }

    /**
     * Procesa la subida del libro PDF
     */
    public function upload(Request $request)
    {
        try {
            // Validar tamaño máximo: 100MB
            $maxSize = 102400; // 100MB en kilobytes
            $validated = $request->validate([
                'book' => 'required|file|mimes:pdf|max:' . $maxSize,
            ]);

            $file = $request->file('book');
            
            // Validación adicional del tamaño del archivo
            $fileSizeMB = $file->getSize() / 1024 / 1024; // Convertir a MB
            if ($fileSizeMB > 100) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "El archivo es demasiado grande ({$fileSizeMB}MB). El tamaño máximo permitido es 100MB.");
            }
            
            // Obtener el token JWT del admin de la cookie
            $token = $request->cookie('auth_token');
            
            if (!$token) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'No se encontró el token de autenticación. Por favor, inicia sesión nuevamente.');
            }
            
            // Preparar el archivo para enviar a la API externa
            // IMPORTANTE: Enviar como multipart/form-data con el campo 'file' (NO JSON)
            // Incluir el token JWT en el header Authorization
            $response = Http::timeout(600) // 10 minutos de timeout para archivos grandes
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token
                ])
                ->asMultipart() // Asegurar que se envíe como multipart/form-data
                ->attach(
                    'file', // Campo requerido: 'file'
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName(),
                    ['Content-Type' => 'application/pdf']
                )
                ->post('https://web.estoico.app/ia/upload-document/admin');

            if ($response->successful()) {
                $responseData = $response->json();
                $message = $responseData['message'] ?? 'Libro subido exitosamente';
                
                return redirect()
                    ->route('admin.books.index')
                    ->with('success', $message);
            } else {
                // Manejar error 403 específicamente (Not authenticated)
                if ($response->status() === 403) {
                    Log::error('Error 403 al subir libro - No autenticado', [
                        'file_name' => $file->getClientOriginalName(),
                        'file_size_mb' => round($file->getSize() / 1024 / 1024, 2),
                        'server' => 'web.estoico.app',
                        'has_token' => !empty($token)
                    ]);
                    
                    return redirect()
                        ->back()
                        ->withInput()
                        ->with('error', 'Error 403: No autenticado. El token JWT no fue enviado correctamente o ha expirado. Por favor, inicia sesión nuevamente.');
                }
                
                // Manejar error 413 específicamente (Request Entity Too Large)
                if ($response->status() === 413) {
                    $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
                    
                    Log::error('Error 413 al subir libro', [
                        'file_size_mb' => $fileSizeMB,
                        'file_name' => $file->getClientOriginalName(),
                        'server' => 'web.estoico.app'
                    ]);
                    
                    $errorMsg = "Error 413: El servidor externo (web.estoico.app) rechazó el archivo de {$fileSizeMB}MB. ";
                    $errorMsg .= "El servidor nginx tiene un límite de tamaño muy pequeño configurado (aprox. 1MB). ";
                    $errorMsg .= "SOLUCIÓN: El administrador del servidor web.estoico.app debe agregar 'client_max_body_size 100M;' en la configuración de nginx y reiniciar el servicio.";
                    
                    return redirect()
                        ->back()
                        ->withInput()
                        ->with('error', $errorMsg);
                }
                
                // Intentar obtener el mensaje de error de diferentes formatos
                $responseBody = $response->body();
                $errorMessage = 'Error al subir el libro';
                
                if ($response->json()) {
                    $jsonResponse = $response->json();
                    $errorMessage = $jsonResponse['message'] 
                        ?? $jsonResponse['error'] 
                        ?? $jsonResponse['detail']
                        ?? 'Error al subir el libro';
                } else {
                    // Si no es JSON, usar el body completo si es corto
                    if (strlen($responseBody) < 200) {
                        $errorMessage = $responseBody;
                    }
                }
                
                Log::error('Error al subir libro', [
                    'status' => $response->status(),
                    'status_text' => $response->reason(),
                    'response' => $responseBody,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize()
                ]);
                
                $fullErrorMessage = "Error {$response->status()}: {$errorMessage}";
                
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $fullErrorMessage);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessage = 'Por favor, verifica que el archivo sea un PDF válido (máximo 100MB)';
            
            // Si el error es de tamaño, dar un mensaje más específico
            if (isset($errors['book'])) {
                foreach ($errors['book'] as $error) {
                    if (str_contains($error, 'max')) {
                        $errorMessage = 'El archivo es demasiado grande. El tamaño máximo permitido es 100MB.';
                        break;
                    }
                }
            }
            
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($errors)
                ->with('error', $errorMessage);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Error de conexión al subir libro: ' . $e->getMessage());
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error de conexión con el servidor. Por favor, intenta nuevamente.');
        } catch (\Exception $e) {
            Log::error('Error inesperado al subir libro', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error inesperado: ' . $e->getMessage());
        }
    }
}


<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Reflection;
use Carbon\Carbon;

class DiarioController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->attributes->get('authenticated_user');
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $data = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $tz = $user->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $date = $now->toDateString();

        // Crear una nueva reflexión (siempre crear, nunca actualizar)
        $reflection = Reflection::create([
            'user_id' => $user->getId(),
            'date' => $date,
            'text' => $data['text'],
        ]);

        // Devolver la hora registrada (created_at) en la zona del usuario
        $time = $reflection->created_at
            ? $reflection->created_at->setTimezone($tz)->format('H:i:s')
            : $now->format('H:i:s');

        return response()->json([
            'message' => 'Reflexión creada correctamente.',
            'data' => [
                'id' => $reflection->id,
                'user_id' => $reflection->user_id,
                'date' => $reflection->date,
                'text' => $reflection->text,
                'time' => $time,
            ]
        ], 201);
    }

    public function show(Request $request)
    {
        $user = $request->attributes->get('authenticated_user');
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $tz = $user->timezone ?? config('app.timezone', 'UTC');

        if ($request->query('all')) {
            $all = Reflection::where('user_id', $user->getId())
                ->orderBy('date', 'desc')
                ->get()
                ->map(function($r) use ($tz) {
                    return [
                        'id' => $r->id,
                        'date' => $r->date,
                        'text' => $r->text,
                        'time' => $r->created_at ? $r->created_at->setTimezone($tz)->format('H:i:s') : null,
                    ];
                });

            return response()->json(['data' => $all], 200);
        }

        $date = Carbon::now($tz)->toDateString();

        $reflection = Reflection::where('user_id', $user->getId())
            ->where('date', $date)
            ->first();

        if (! $reflection) {
            return response()->json([
                'data' => [
                    'user_id' => $user->getId(),
                    'date' => $date,
                    'text' => null,
                ]
            ], 200);
        }

        return response()->json([
            'data' => [
                'id' => $reflection->id,
                'date' => $reflection->date,
                'text' => $reflection->text,
                'time' => $reflection->created_at ? $reflection->created_at->setTimezone($tz)->format('H:i:s') : null,
            ]
        ], 200);
    }

    public function all(Request $request)
    {
        $user = $request->attributes->get('authenticated_user');
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $tz = $user->timezone ?? config('app.timezone', 'UTC');

        $all = Reflection::where('user_id', $user->getId())
            ->orderBy('date', 'desc')
            ->get()
            ->map(function($r) use ($tz) {
                return [
                    'id' => $r->id,
                    'date' => $r->date,
                    'text' => $r->text,
                    'time' => $r->created_at ? $r->created_at->setTimezone($tz)->format('H:i:s') : null,
                ];
            });

        return response()->json(['data' => $all], 200);
    }

    public function update(Request $request, $id)
    {
        $user = $request->attributes->get('authenticated_user');
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }
        $data = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $reflection = Reflection::where('id', $id)->where('user_id', $user->getId())->first();

        if (! $reflection) {
            return response()->json(['message' => 'Reflexión no encontrada.'], 404);
        }

        $reflection->text = $data['text'];
        $reflection->save();

        $tz = $user->timezone ?? config('app.timezone', 'UTC');

        return response()->json([
            'message' => 'Reflexión actualizada correctamente.',
            'data' => [
                'id' => $reflection->id,
                'user_id' => $reflection->user_id,
                'date' => $reflection->date,
                'text' => $reflection->text,
                'time' => $reflection->updated_at ? $reflection->updated_at->setTimezone($tz)->format('H:i:s') : null,
                'created_at' => $reflection->created_at ? $reflection->created_at->setTimezone($tz)->format('Y-m-d H:i:s') : null,
                'updated_at' => $reflection->updated_at ? $reflection->updated_at->setTimezone($tz)->format('Y-m-d H:i:s') : null,
            ]
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->attributes->get('authenticated_user');
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $reflection = Reflection::where('id', $id)->where('user_id', $user->getId())->first();

        if (! $reflection) {
            return response()->json(['message' => 'Reflexión no encontrada.'], 404);
        }

        $reflection->delete();

        return response()->json(['message' => 'Reflexión eliminada correctamente.'], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TutoringRequest;
use App\Models\TutoringAvailability;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TutoringController extends Controller
{
    // ==================== MÉTODOS PARA TEACHERS ====================

    /**
     * Lista las solicitudes de tutoría del profesor autenticado
     * GET /api/tutoring/requests?status=pending|accepted|all
     */
    public function getTeacherRequests(Request $request): JsonResponse
    {
        try {
            $teacherId = auth()->id();
            $status = $request->query('status', 'all');

            $query = TutoringRequest::where('teacher_id', $teacherId)
                ->with(['student:id,name,email']);

            // Mapear status del frontend al backend
            $statusMap = [
                'pending' => 'pending',
                'accepted' => 'confirmed',
                'rejected' => 'rejected',
                'completed' => 'completed'
            ];

            // Filtrar por status si no es 'all'
            if ($status !== 'all') {
                $mappedStatus = $statusMap[$status] ?? $status;
                $query->where('status', $mappedStatus);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            // Mapear el status de vuelta para el frontend
            $requests->transform(function ($request) {
                if ($request->status === 'confirmed') {
                    $request->status = 'accepted';
                } elseif ($request->status === 'cancelled') {
                    $request->status = 'rejected';
                }
                return $request;
            });

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Solicitudes obtenidas correctamente'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error en getTeacherRequests: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Acepta una solicitud de tutoría
     * POST /api/tutoring/requests/{id}/accept
     */
    public function acceptRequest(Request $request, $id): JsonResponse
    {
        try {
            $teacherId = auth()->id();
            $tutoringRequest = TutoringRequest::findOrFail($id);

            // Validar que solo el profesor asignado puede aceptar
            if ($tutoringRequest->teacher_id !== $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para aceptar esta solicitud'
                ], 403);
            }

            // Validar que el status sea pending
            if ($tutoringRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden aceptar solicitudes pendientes'
                ], 400);
            }

            // Validar meet_url si se envía
            $validated = $request->validate([
                'meet_url' => 'nullable|url'
            ]);

            // Actualizar la solicitud
            $tutoringRequest->status = 'confirmed';
            if (isset($validated['meet_url'])) {
                $tutoringRequest->meet_url = $validated['meet_url'];
            }
            $tutoringRequest->save();

            // Mapear status para el frontend
            $tutoringRequest->status = 'accepted';

            return response()->json([
                'success' => true,
                'data' => $tutoringRequest->load(['student:id,name,email']),
                'message' => 'Solicitud aceptada correctamente'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aceptar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechaza una solicitud de tutoría
     * POST /api/tutoring/requests/{id}/reject
     */
    public function rejectRequest(Request $request, $id): JsonResponse
    {
        try {
            $teacherId = auth()->id();
            $tutoringRequest = TutoringRequest::findOrFail($id);

            // Validar que solo el profesor asignado puede rechazar
            if ($tutoringRequest->teacher_id !== $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para rechazar esta solicitud'
                ], 403);
            }

            // Validar que el status sea pending
            if ($tutoringRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden rechazar solicitudes pendientes'
                ], 400);
            }

            // Validar rejection_reason (opcional, no se guarda en BD)
            $request->validate([
                'rejection_reason' => 'nullable|string|min:10'
            ]);

            // Actualizar la solicitud
            $tutoringRequest->status = 'rejected';
            $tutoringRequest->save();

            return response()->json([
                'success' => true,
                'data' => $tutoringRequest->load(['student:id,name,email']),
                'message' => 'Solicitud rechazada correctamente'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca la asistencia del estudiante
     * POST /api/tutoring/requests/{id}/mark-attendance
     */
    public function markAttendance(Request $request, $id): JsonResponse
    {
        try {
            $teacherId = auth()->id();
            $tutoringRequest = TutoringRequest::findOrFail($id);

            // Validar que solo el profesor asignado puede marcar asistencia
            if ($tutoringRequest->teacher_id !== $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para marcar la asistencia de esta solicitud'
                ], 403);
            }

            // Validar que el status sea confirmed
            if ($tutoringRequest->status !== 'confirmed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede marcar asistencia en solicitudes aceptadas'
                ], 400);
            }

            // Actualizar la solicitud (student_attended no existe en BD)
            $tutoringRequest->status = 'completed';
            $tutoringRequest->save();

            return response()->json([
                'success' => true,
                'data' => $tutoringRequest->load(['student:id,name,email']),
                'message' => 'Asistencia marcada correctamente'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la asistencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el historial de tutorías completadas/rechazadas del profesor
     * GET /api/tutoring/history
     */
    public function getTeacherHistory(): JsonResponse
    {
        try {
            $teacherId = auth()->id();

            $history = TutoringRequest::where('teacher_id', $teacherId)
                ->whereIn('status', ['completed', 'rejected'])
                ->with(['student:id,name,email'])
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Historial obtenido correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PARA STUDENTS ====================

    /**
     * Crea una nueva solicitud de tutoría
     * POST /api/tutoring/requests
     */
    public function createRequest(Request $request): JsonResponse
    {
        try {
            $studentId = auth()->id();

            // Validar los datos (subject, topic, notes no se guardan en BD)
            $validated = $request->validate([
                'teacher_id' => 'required|exists:users,id',
                'subject' => 'nullable|string|max:255',
                'topic' => 'nullable|string|max:255',
                'requested_date' => 'required|date|after:today',
                'requested_time' => 'required|date_format:H:i',
                'notes' => 'nullable|string'
            ]);

            // Combinar fecha y hora para start_time
            $startTime = $validated['requested_date'] . ' ' . $validated['requested_time'];
            // Asumir 1 hora de duración para end_time
            $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' +1 hour'));

            // Validar que el teacher_id tenga rol teacher
            $teacher = User::findOrFail($validated['teacher_id']);
            if (!$teacher->hasRole('teacher')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario seleccionado no es un profesor'
                ], 400);
            }

            // Validar que no haya solicitudes pendientes con el mismo profesor
            $existingRequest = TutoringRequest::where('student_id', $studentId)
                ->where('teacher_id', $validated['teacher_id'])
                ->where('status', 'pending')
                ->exists();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes una solicitud pendiente con este profesor'
                ], 400);
            }

            // Crear la solicitud (solo con campos que existen en BD)
            $tutoringRequest = TutoringRequest::create([
                'student_id' => $studentId,
                'teacher_id' => $validated['teacher_id'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'data' => $tutoringRequest->load(['teacher:id,name,email']),
                'message' => 'Solicitud creada correctamente'
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
                'message' => 'Error al crear la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todas las solicitudes del estudiante autenticado
     * GET /api/tutoring/my-requests
     */
    public function getStudentRequests(): JsonResponse
    {
        try {
            $studentId = auth()->id();

            $requests = TutoringRequest::where('student_id', $studentId)
                ->with(['teacher:id,name,email'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Mapear el status de vuelta para el frontend
            $requests->transform(function ($request) {
                if ($request->status === 'confirmed') {
                    $request->status = 'accepted';
                } elseif ($request->status === 'cancelled') {
                    $request->status = 'rejected';
                }
                return $request;
            });

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Solicitudes obtenidas correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista profesores con rol teacher
     * GET /api/tutoring/teachers
     */
    public function getAvailableTeachers(): JsonResponse
    {
        try {
            $teachers = User::role('teacher')
                ->select('id', 'name', 'email')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $teachers,
                'message' => 'Profesores obtenidos correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los profesores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS COMPARTIDOS ====================

    /**
     * Obtiene la disponibilidad de un profesor
     * GET /api/tutoring/availabilities/{teacherId}
     */
    public function getTeacherAvailability($teacherId): JsonResponse
    {
        try {
            // Validar que el profesor exista
            $teacher = User::findOrFail($teacherId);

            $availabilities = TutoringAvailability::where('user_id', $teacherId)
                ->orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
                ->orderBy('start_time', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $availabilities,
                'message' => 'Disponibilidad obtenida correctamente'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profesor no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la disponibilidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

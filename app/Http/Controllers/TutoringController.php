<?php

namespace App\Http\Controllers;

use IncadevUns\CoreDomain\Models\Appointment;
use IncadevUns\CoreDomain\Models\Availability;
use IncadevUns\CoreDomain\Enums\AppointmentStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TutoringController extends Controller
{
    /**
     * Helper para obtener el user_id del request
     * Acepta query parameter ?user_id= o header X-User-Id
     */
    private function getUserId(Request $request): ?int
    {
        $userId = $request->query('user_id') ?? $request->header('X-User-Id');
        return $userId ? (int) $userId : null;
    }

    // ==================== MÉTODOS PARA TEACHERS ====================

    /**
     * Lista las solicitudes de tutoría del profesor autenticado
     * GET /api/tutoring/requests?status=pending|accepted|all
     */
    public function getTeacherRequests(Request $request): JsonResponse
    {
        try {
            $teacherId = $this->getUserId($request);
            
            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id es requerido (query param o header X-User-Id)'
                ], 400);
            }

            $status = $request->query('status', 'all');

            $query = Appointment::where('teacher_id', $teacherId)
                ->with(['student:id,name,email']);

            // Mapear status del frontend al backend
            $statusMap = [
                'pending' => AppointmentStatus::Pending,
                'accepted' => AppointmentStatus::Confirmed,
                'rejected' => AppointmentStatus::Rejected,
                'completed' => AppointmentStatus::Completed
            ];

            // Filtrar por status si no es 'all'
            if ($status !== 'all') {
                $mappedStatus = $statusMap[$status] ?? $status;
                $query->where('status', $mappedStatus);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            // Transformar los datos para el frontend
            $formattedRequests = $requests->map(function ($appointment) {
                // Mapear el status Enum a strings que espera el frontend
                $status = match($appointment->status) {
                    AppointmentStatus::Confirmed => 'accepted',
                    AppointmentStatus::Cancelled => 'rejected',
                    default => $appointment->status->value
                };
                
                return [
                    'id' => $appointment->id,
                    'teacher_id' => $appointment->teacher_id,
                    'student_id' => $appointment->student_id,
                    'start_time' => $appointment->start_time,
                    'end_time' => $appointment->end_time,
                    'status' => $status,
                    'rejection_reason' => $appointment->rejection_reason,
                    'student_attended' => $appointment->student_attended,
                    'meet_url' => $appointment->meet_url,
                    'created_at' => $appointment->created_at,
                    'updated_at' => $appointment->updated_at,
                    'studentName' => $appointment->student ? $appointment->student->name : 'Desconocido',
                    'requested_date' => $appointment->start_time ? $appointment->start_time->format('Y-m-d') : null,
                    'requested_time' => $appointment->start_time ? $appointment->start_time->format('H:i') : null,
                    'student' => $appointment->student
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedRequests,
                'message' => 'Solicitudes obtenidas correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en getTeacherRequests: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUserId($request)
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
            $teacherId = $this->getUserId($request);
            $appointment = Appointment::findOrFail($id);

            // Validar que solo el profesor asignado puede aceptar
            if ($appointment->teacher_id != $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para aceptar esta solicitud'
                ], 403);
            }

            // Validar que el status sea pending
            if ($appointment->status !== AppointmentStatus::Pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden aceptar solicitudes pendientes'
                ], 400);
            }

            // Validar meet_url si se envía
            $validated = $request->validate([
                'meet_url' => 'nullable|string|max:500'
            ]);

            // Actualizar la solicitud
            $appointment->status = AppointmentStatus::Confirmed;
            if (isset($validated['meet_url'])) {
                $appointment->meet_url = $validated['meet_url'];
            }
            $appointment->save();

            return response()->json([
                'success' => true,
                'data' => $appointment->load(['student:id,name,email']),
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
            $teacherId = $this->getUserId($request);
            $appointment = Appointment::findOrFail($id);

            // Validar que solo el profesor asignado puede rechazar
            if ($appointment->teacher_id != $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para rechazar esta solicitud'
                ], 403);
            }

            // Validar que el status sea pending
            if ($appointment->status !== AppointmentStatus::Pending) {
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
            $appointment->status = AppointmentStatus::Rejected;
            $appointment->save();

            return response()->json([
                'success' => true,
                'data' => $appointment->load(['student:id,name,email']),
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
            $teacherId = $this->getUserId($request);
            $appointment = Appointment::findOrFail($id);

            // Validar que solo el profesor asignado puede marcar asistencia
            if ($appointment->teacher_id != $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para marcar la asistencia de esta solicitud'
                ], 403);
            }

            // Validar que el status sea confirmed
            if ($appointment->status !== AppointmentStatus::Confirmed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede marcar asistencia en solicitudes aceptadas'
                ], 400);
            }

            // Actualizar la solicitud (student_attended no existe en BD)
            $appointment->status = AppointmentStatus::Completed;
            $appointment->save();

            return response()->json([
                'success' => true,
                'data' => $appointment->load(['student:id,name,email']),
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
    public function getTeacherHistory(Request $request): JsonResponse
    {
        try {
            $teacherId = $this->getUserId($request);

            $history = Appointment::where('teacher_id', $teacherId)
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
            $studentId = $this->getUserId($request);
            
            if (!$studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de usuario no proporcionado'
                ], 400);
            }

            // Validar los datos (subject, topic, notes no se guardan en BD)
            $validated = $request->validate([
                'teacher_id' => 'required|exists:users,id',
                'subject' => 'nullable|string|max:255',
                'topic' => 'nullable|string|max:255',
                'requested_date' => 'required|date|after_or_equal:today',
                'requested_time' => 'required|date_format:H:i',
                'notes' => 'nullable|string'
            ]);

            // Combinar fecha y hora para start_time
            $startTime = $validated['requested_date'] . ' ' . $validated['requested_time'];
            // Asumir 1 hora de duración para end_time
            $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' +1 hour'));

            // Validar que el teacher_id tenga rol tutor
            $tutor = User::findOrFail($validated['teacher_id']);
            if (!$tutor->hasRole('tutor')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario seleccionado no es un tutor'
                ], 400);
            }

            // Validar que no haya solicitudes pendientes con el mismo profesor
            $existingRequest = Appointment::where('student_id', $studentId)
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
            $appointment = Appointment::create([
                'student_id' => $studentId,
                'teacher_id' => $validated['teacher_id'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'data' => $appointment->load(['teacher:id,name,email']),
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
    public function getStudentRequests(Request $request): JsonResponse
    {
        try {
            // Usar helper que acepta query param (?user_id=) o header (X-User-Id)
            $studentId = $this->getUserId($request);

            if (!$studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id es requerido (query param o header X-User-Id)'
                ], 400);
            }

            $requests = Appointment::where('student_id', $studentId)
                ->with(['teacher:id,name,email'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Transformar los datos para el frontend (igual que getTeacherRequests)
            $formattedRequests = $requests->map(function ($appointment) {
                // Mapear el status Enum a strings que espera el frontend
                $status = $appointment->status;
                if (is_object($status) && method_exists($status, 'value')) {
                    $status = $status->value;
                }
                if ($status === 'confirmed') {
                    $status = 'accepted';
                } elseif ($status === 'cancelled') {
                    $status = 'rejected';
                }

                return [
                    'id' => $appointment->id,
                    'teacher_id' => $appointment->teacher_id,
                    'student_id' => $appointment->student_id,
                    'start_time' => $appointment->start_time,
                    'end_time' => $appointment->end_time,
                    'status' => $status,
                    'rejection_reason' => $appointment->rejection_reason,
                    'student_attended' => $appointment->student_attended,
                    'meet_url' => $appointment->meet_url,
                    'created_at' => $appointment->created_at,
                    'updated_at' => $appointment->updated_at,
                    'teacherName' => $appointment->teacher ? $appointment->teacher->name : 'Desconocido',
                    'requested_date' => $appointment->start_time ? $appointment->start_time->format('Y-m-d') : null,
                    'requested_time' => $appointment->start_time ? $appointment->start_time->format('H:i') : null,
                    'teacher' => $appointment->teacher
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedRequests,
                'message' => 'Solicitudes obtenidas correctamente!'
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
     * Lista tutores con rol tutor
     * GET /api/tutoring/teachers
     */
    public function getAvailableTeachers(): JsonResponse
    {
        try {
            $tutors = User::role('tutor')
                ->select('id', 'name', 'email')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tutors,
                'message' => 'Tutores obtenidos correctamente'
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
     * Obtiene la disponibilidad de un tutor
     * GET /api/tutoring/availabilities/{teacherId}
     */
    public function getTeacherAvailability($teacherId): JsonResponse
    {
        try {
            // Validar que el tutor exista
            $tutor = User::findOrFail($teacherId);

            $availabilities = Availability::where('user_id', $teacherId)
                ->orderBy('day_of_week', 'asc')
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

    /**
     * Obtiene la disponibilidad del profesor autenticado
     * GET /api/tutoring/my-availability?user_id=X
     */
    public function getMyAvailability(Request $request): JsonResponse
    {
        try {
            $teacherId = $this->getUserId($request);
            
            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id es requerido (query param o header X-User-Id)'
                ], 400);
            }

            $availabilities = Availability::where('user_id', $teacherId)
                ->orderBy('day_of_week', 'asc')
                ->orderBy('start_time', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $availabilities,
                'message' => 'Disponibilidad obtenida correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la disponibilidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea una nueva disponibilidad para el profesor autenticado
     * POST /api/tutoring/availability
     */
    public function createAvailability(Request $request): JsonResponse
    {
        try {
            $teacherId = $this->getUserId($request);
            
            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id es requerido (query param o header X-User-Id)'
                ], 400);
            }

            $validated = $request->validate([
                'day_of_week' => 'required|integer|between:0,6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time'
            ]);

            // Verificar que no exista un horario duplicado
            $exists = Availability::where('user_id', $teacherId)
                ->where('day_of_week', $validated['day_of_week'])
                ->where('start_time', $validated['start_time'])
                ->where('end_time', $validated['end_time'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una disponibilidad con ese horario'
                ], 400);
            }

            $availability = Availability::create([
                'user_id' => $teacherId,
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time']
            ]);

            return response()->json([
                'success' => true,
                'data' => $availability,
                'message' => 'Disponibilidad creada correctamente'
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
                'message' => 'Error al crear la disponibilidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina una disponibilidad del profesor autenticado
     * DELETE /api/tutoring/availability/{id}?user_id=X
     */
    public function deleteAvailability(Request $request, $id): JsonResponse
    {
        try {
            $teacherId = $this->getUserId($request);
            
            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id es requerido (query param o header X-User-Id)'
                ], 400);
            }
            $availability = Availability::findOrFail($id);

            // Validar que la disponibilidad pertenece al profesor autenticado
            if ($availability->user_id != $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar esta disponibilidad'
                ], 403);
            }

            $availability->delete();

            return response()->json([
                'success' => true,
                'message' => 'Disponibilidad eliminada correctamente'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Disponibilidad no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la disponibilidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

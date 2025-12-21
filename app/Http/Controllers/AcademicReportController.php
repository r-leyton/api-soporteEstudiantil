<?php

namespace App\Http\Controllers;


use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\EnrollmentPayment;
use Illuminate\Http\Request;
use IncadevUns\CoreDomain\Models\Enrollment as Enrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;

class AcademicReportController extends Controller
{

    private function getUserId(Request $request): ?int
    {
        $userId = $request->query('user_id') ?? $request->header('X-User-Id');
        return $userId ? (int) $userId : null;
    }

    public function enrolledCoursesReport(Request $request)
    {
        try {
            // ETAPA 1: Validación (sin exists:users,id para evitar problemas de sincronización de BD)
            $validated = $request->validate([
                'student_id' => 'required|integer'
            ]);

            $studentId = $validated['student_id'];

            // ETAPA 2: Consultar matrículas con relación user
            $enrollments = Enrollment::where('user_id', $studentId)
                ->with(['group.courseVersion.course', 'result', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no tiene matrículas registradas',
                    'student_id' => $studentId
                ], 404);
            }

            // ETAPA 3: Obtener estudiante desde la relación del enrollment
            $student = $enrollments->first()->user;
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado',
                    'student_id' => $studentId
                ], 404);
            }

            // ETAPA 4: Generar PDF
            $data = [
                'student' => $student,
                'enrollments' => $enrollments,
                'report_date' => now()->format('d/m/Y'),
            ];

            $pdf = Pdf::loadView('reports.enrolled-courses', $data);

            return $pdf->download('cursos-matriculados-' . $student->dni . '.pdf');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte: ' . $e->getMessage(),
                'error_type' => get_class($e)
            ], 500);
        }
    }

    public function singleCourseGradesReport(Request $request)
    {
        // Línea 1: Validar que vengan ambos IDs en el request (sin exists:users,id)
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'group_id' => 'required|integer|exists:groups,id'
        ]);

        // Línea 2: Obtener los IDs desde el request
        $studentId = $validated['student_id'];
        $groupId = $validated['group_id'];


        // Línea 4: Consultar la matrícula con relación user
        $enrollment = Enrollment::where('user_id', $studentId)
            ->where('group_id', $groupId)
            ->with([
                'group.courseVersion.course',
                'grades.exam.module',
                'result',
                'user'
            ])
            ->firstOrFail();

        // Línea 5: Obtener estudiante desde la relación
        $student = $enrollment->user;

        // Línea 6: Preparar los datos para la vista PDF
        $data = [
            'enrollment' => $enrollment,
            'student' => $student,
            'report_date' => now()->format('d/m/Y'),
        ];

        // Línea 7: Generar el PDF
        $pdf = Pdf::loadView('reports.single-course-grades', $data);

        // Línea 8: Crear nombre del archivo y descargar
        $courseName = \Illuminate\Support\Str::slug($enrollment->group->courseVersion->course->name);
        return $pdf->download('notas-' . $courseName . '-' . $student->dni . '.pdf');
    }
    public function studentAcademicSummary(Request $request)
    {
        // Línea 1: El frontend debe enviar el student_id en el request (sin exists:users,id)
        $validated = $request->validate([
            'student_id' => 'required|integer'
        ]);

        // Línea 2: Obtener el ID del estudiante desde el request
        $studentId = $validated['student_id'];

        // Línea 4: Consultar todas las matrículas con relación user
        $enrollments = Enrollment::where('user_id', $studentId)
            ->with(['group.courseVersion.course', 'result', 'user'])
            ->get();

        if ($enrollments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no tiene matrículas registradas'
            ], 404);
        }

        // Línea 5: Calcular estadísticas
        $completedCourses = $enrollments->where('result.status', 'approved')->count();
        $inProgressCourses = $enrollments->where('academic_status', 'active')->count();

        // Línea 6: Obtener estudiante desde la relación
        $student = $enrollments->first()->user;

        // Línea 7: Preparar los datos para la vista PDF
        $data = [
            'student' => $student,
            'enrollments' => $enrollments,
            'stats' => [
                'total_courses' => $enrollments->count(),
                'completed_courses' => $completedCourses,
                'in_progress_courses' => $inProgressCourses,
            ],
            'report_date' => now()->format('d/m/Y'),
        ];

        // Línea 8: Generar el PDF
        $pdf = Pdf::loadView('reports.academic-summary', $data);

        // Línea 9: Descargar con nombre personalizado
        return $pdf->download('resumen-academico-' . $student->dni . '.pdf');
    }

    /**
     * API: Obtener todos los grupos del estudiante con información básica
     */
    public function getStudentGroups(Request $request)
    {
            $studentId = $this->getUserId($request);
            if (!$studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id es requerido (query param o header X-User-Id)'
                ], 400);
            }
        
        try {
              
            $enrollments = Enrollment::where('user_id', $studentId)
                ->with([
                    'group.courseVersion.course',
                    'result',
                    'grades.exam.module',
                    'user'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            $groups = $enrollments->map(function ($enrollment) {
                $averageGrade = $enrollment->grades->avg('grade');
                $completedExams = $enrollment->grades->count();

                return [
                    'enrollment_id' => $enrollment->id, 
                    'group_id' => $enrollment->group->id,
                    'group_name' => $enrollment->group->name,
                    'course_name' => $enrollment->group->courseVersion->course->name,
                    'start_date' => $enrollment->group->start_date,
                    'end_date' => $enrollment->group->end_date,
                    'group_status' => $enrollment->group->status->value,
                    'academic_status' => $enrollment->academic_status->value,
                    'final_grade' => $enrollment->result ? $enrollment->result->final_grade : null,
                    'result_status' => $enrollment->result ? $enrollment->result->status : null,
                    'average_grade' => $averageGrade ? round($averageGrade, 1) : null,
                    'completed_exams' => $completedExams,
                    'has_report_data' => $completedExams > 0 || $enrollment->result != null
                ];
            });

            return response()->json([
                'success' => true,
                'student_id' => $studentId,
                'total_groups' => $groups->count(),
                'groups' => $groups
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
/**
 * API: Obtener resumen académico completo del estudiante
 */
public function getAcademicSummary(Request $request)
{
    try {
        $validated = $request->validate([
            'student_id' => 'required|integer'
        ]);

        $studentId = $validated['student_id'];


        $enrollments = Enrollment::where('user_id', $studentId)
            ->with([
                'group.courseVersion.course',
                'result',
                'grades',
                'user'
            ])
            ->get();

        $student = $enrollments->isNotEmpty() ? $enrollments->first()->user : null;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no tiene matrículas registradas',
                'can_generate_report' => false
            ], 404);
        }

        // Calcular estadísticas
        $completedCourses = $enrollments->where('result.status', 'approved')->count();
        $inProgressCourses = $enrollments->where('academic_status', 'active')->count();
        $failedCourses = $enrollments->where('result.status', 'failed')->count();

        // Calcular promedio general
        $completedWithGrades = $enrollments->where('result.final_grade', '>', 0);
        $overallAverage = $completedWithGrades->avg('result.final_grade');

        // Cursos recientes
        $recentCourses = $enrollments->sortByDesc('created_at')->take(5)->map(function ($enrollment) {
            return [
                'course_name' => $enrollment->group->courseVersion->course->name,
                'group_name' => $enrollment->group->name,
                'status' => $enrollment->result ? $enrollment->result->status : $enrollment->academic_status->value,
                'final_grade' => $enrollment->result ? $enrollment->result->final_grade : null,
                'completion_date' => $enrollment->group->end_date
            ];
        });

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $student->id,
                'dni' => $student->dni,
                'fullname' => $student->fullname,
                'email' => $student->email
            ],
            'academic_stats' => [
                'total_courses' => $enrollments->count(),
                'completed_courses' => $completedCourses,
                'in_progress_courses' => $inProgressCourses,
                'failed_courses' => $failedCourses,
                'overall_average' => $overallAverage ? round($overallAverage, 1) : null,
                'completion_rate' => $enrollments->count() > 0 ? 
                    round(($completedCourses / $enrollments->count()) * 100, 1) : 0
            ],
            'recent_courses' => $recentCourses,
            'can_generate_report' => $enrollments->count() > 0
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * API: Obtener notas detalladas de un grupo específico
     */
    public function getGroupGrades(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|integer',
                'group_id' => 'required|integer|exists:groups,id'
            ]);

            $studentId = $validated['student_id'];
            $groupId = $validated['group_id'];

            // Consultar matrícula con relación user
            $enrollment = Enrollment::where('user_id', $studentId)
                ->where('group_id', $groupId)
                ->with([
                    'group.courseVersion.course',
                    'grades.exam.module',
                    'result',
                    'attendances.classSession.module',
                    'user'
                ])
                ->firstOrFail();

            $grades = $enrollment->grades->map(function ($grade) {
                return [
                    'exam_id' => $grade->exam->id,
                    'exam_title' => $grade->exam->title,
                    'module_name' => $grade->exam->module->title ?? 'Sin módulo',
                    'grade' => (float) $grade->grade,
                    'feedback' => $grade->feedback,
                    'exam_date' => $grade->exam->start_date,
                    'grade_letter' => $this->getGradeLetter($grade->grade)
                ];
            });

            $attendanceStats = $this->calculateAttendanceStats($enrollment->attendances);

            return response()->json([
                'success' => true,
                'student' => [
                    'id' => $enrollment->user->id,
                    'dni' => $enrollment->user->dni,
                    'fullname' => $enrollment->user->fullname
                ],
                'course_info' => [
                    'course_name' => $enrollment->group->courseVersion->course->name,
                    'group_name' => $enrollment->group->name,
                    'start_date' => $enrollment->group->start_date,
                    'end_date' => $enrollment->group->end_date
                ],
                'academic_status' => $enrollment->academic_status->value,
                'final_result' => $enrollment->result ? [
                    'final_grade' => $enrollment->result->final_grade,
                    'status' => $enrollment->result->status,
                    'attendance_percentage' => $enrollment->result->attendance_percentage
                ] : null,
                'grades_summary' => [
                    'total_grades' => $grades->count(),
                    'average_grade' => $grades->count() > 0 ? round($grades->avg('grade'), 1) : null,
                    'highest_grade' => $grades->max('grade'),
                    'lowest_grade' => $grades->min('grade')
                ],
                'attendance_summary' => $attendanceStats,
                'detailed_grades' => $grades,
                'can_generate_report' => $grades->count() > 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    private function getGradeLetter($grade)
    {
        if ($grade >= 18) return 'A+';
        if ($grade >= 16) return 'A';
        if ($grade >= 14) return 'B';
        if ($grade >= 12) return 'C';
        if ($grade >= 10.5) return 'D';
        return 'F';
    }

    /**
     * Calcular estadísticas de asistencia
     */
    private function calculateAttendanceStats($attendances)
    {
        $total = $attendances->count();
        $present = $attendances->where('status', 'present')->count();
        $absent = $attendances->where('status', 'absent')->count();
        $late = $attendances->where('status', 'late')->count();

        return [
            'total_sessions' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
        ];
    }
}

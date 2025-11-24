<?php

namespace App\Http\Controllers;


use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\EnrollmentPayment;
use Illuminate\Http\Request;
use IncadevUns\CoreDomain\Models\Enrollment as Enrollment;
use Barryvdh\DomPDF\Facade\Pdf;

class AcademicReportController extends Controller
{

    public function enrolledCoursesReport(Request $request)
    {
        try {
            // ETAPA 1: Validación
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:users,id'
            ]);

            $studentId = $validated['student_id'];

            // ETAPA 2: Consultar matrículas
            $enrollments = Enrollment::where('user_id', $studentId)
                ->with(['group.courseVersion.course', 'result'])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no tiene matrículas registradas',
                    'student_id' => $studentId
                ], 404);
            }

            // ETAPA 3: Obtener estudiante
            $student = \App\Models\User::find($studentId);
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
        // Línea 1: Validar que vengan ambos IDs en el request
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'group_id' => 'required|integer|exists:groups,id'
        ]);

        // Línea 2: Obtener los IDs desde el request
        $studentId = $validated['student_id'];
        $groupId = $validated['group_id'];


        // Línea 4: Consultar la matrícula - CORREGIR RELACIÓN 'result'
        $enrollment = Enrollment::where('user_id', $studentId)
            ->where('group_id', $groupId)
            ->with([
                'group.courseVersion.course',
                'grades.exam.module',
                'result'  // ← CORREGIDO: 'result' no 'enrollmentResults'
            ])
            ->firstOrFail();

        // Línea 5: Obtener los datos completos del estudiante
        $student = \App\Models\User::findOrFail($studentId);

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
        // Línea 1: El frontend debe enviar el student_id en el request
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:users,id'
        ]);

        // Línea 2: Obtener el ID del estudiante desde el request
        $studentId = $validated['student_id'];

        // Línea 4: Consultar todas las matrículas - CORREGIR RELACIÓN 'result'
        $enrollments = Enrollment::where('user_id', $studentId)
            ->with(['group.courseVersion.course', 'result']) // ← CORREGIDO: 'result'
            ->get();

        // Línea 5: Calcular estadísticas - CORREGIR ACCESO A RELACIÓN
        $completedCourses = $enrollments->where('result.status', 'approved')->count();
        $inProgressCourses = $enrollments->where('academic_status', 'active')->count();

        // Línea 6: Obtener los datos completos del estudiante
        $student = \App\Models\User::findOrFail($studentId);

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
}

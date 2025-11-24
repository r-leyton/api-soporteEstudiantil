<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resumen Académico - {{ $student->fullname }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }

        /* ================= HEADER ================= */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2c5aa0;
        }

        .institution {
            font-size: 20px;
            font-weight: bold;
            color: #2c5aa0;
            letter-spacing: 0.4px;
        }

        .title {
            font-size: 16px;
            margin-top: 5px;
            font-weight: bold;
            color: #333;
        }

        /* ================= STUDENT INFO ================= */
        .student-info {
            margin-bottom: 25px;
            background: #eef4ff;
            padding: 15px 20px;
            border-left: 4px solid #2c5aa0;
            border-radius: 6px;
        }

        .info-row {
            margin-bottom: 5px;
            font-size: 13px;
        }

        /* ================= STATISTICS ================= */
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin: 25px 0;
            width: 100%;
        }

        .stat-card {
            flex: 1;
            background: #e6f2ff;
            padding: 15px;
            margin: 0 8px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #bfd9ff;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            letter-spacing: 0.3px;
            color: #666;
        }

        /* ================= TABLE ================= */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }

        .table th {
            background-color: #2c5aa0;
            color: white;
            padding: 9px;
            border: 1px solid #1e3d6f;
            font-size: 11px;
            text-transform: uppercase;
        }

        .table td {
            padding: 8px;
            border: 1px solid #ccc;
        }

        /* Zebra rows */
        .table tr:nth-child(even) {
            background-color: #f6f8fc;
        }

        .text-center {
            text-align: center;
        }

        /* ================= STATUS BADGES ================= */
        .status-approved {
            color: #28a745;
            font-weight: bold;
        }

        .status-failed {
            color: #dc3545;
            font-weight: bold;
        }

        .status-progress {
            color: #0d8ecf;
            font-weight: bold;
        }

        /* ================= FINAL SUMMARY ================= */
        .report-summary {
            margin-top: 25px;
            padding: 15px 20px;
            background: #fff9e0;
            border-left: 4px solid #e3c338;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.5;
        }

        /* ================= FOOTER ================= */
        .footer {
            margin-top: 35px;
            text-align: center;
            font-size: 10px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="institution">INSTITUTO DE CAPACITACIÓN</div>
        <div class="title">RESUMEN ACADÉMICO GENERAL</div>
    </div>

    <div class="student-info">
        <div class="info-row"><strong>Estudiante:</strong> {{ $student->fullname }}</div>
        <div class="info-row"><strong>DNI:</strong> {{ $student->dni }}</div>
        <div class="info-row"><strong>Fecha de Reporte:</strong> {{ $report_date }}</div>
    </div>

    <!-- ESTADÍSTICAS -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number">{{ $stats['total_courses'] }}</div>
            <div class="stat-label">TOTAL CURSOS</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $stats['completed_courses'] }}</div>
            <div class="stat-label">CURSOS COMPLETADOS</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $stats['in_progress_courses'] }}</div>
            <div class="stat-label">CURSOS EN PROGRESO</div>
        </div>
    </div>

    <!-- TABLA -->
    <table class="table">
        <thead>
            <tr>
                <th>Curso</th>
                <th>Grupo</th>
                <th class="text-center">Periodo</th>
                <th class="text-center">Nota Final</th>
                <th class="text-center">Asistencia</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($enrollments as $enrollment)
            <tr>
                <td>{{ $enrollment->group->courseVersion->course->name }}</td>
                <td>{{ $enrollment->group->name }}</td>
                <td class="text-center">
                    {{ \Carbon\Carbon::parse($enrollment->group->start_date)->format('m/Y') }} -
                    {{ \Carbon\Carbon::parse($enrollment->group->end_date)->format('m/Y') }}
                </td>
                <td class="text-center">
                    @if($enrollment->enrollmentResults)
                        <strong>{{ number_format($enrollment->enrollmentResults->final_grade, 1) }}</strong>
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">
                    @if($enrollment->enrollmentResults)
                        {{ number_format($enrollment->enrollmentResults->attendance_percentage, 1) }}%
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">
                    @if($enrollment->enrollmentResults)
                        @if($enrollment->enrollmentResults->status === 'approved')
                            <span class="status-approved">APROBADO</span>
                        @else
                            <span class="status-failed">REPROBADO</span>
                        @endif
                    @elseif($enrollment->academic_status === 'active')
                        <span class="status-progress">EN PROGRESO</span>
                    @else
                        INACTIVO
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- RESUMEN -->
    <div class="report-summary">
        <strong>RESUMEN:</strong><br>
        • Cursos Totales: {{ $stats['total_courses'] }}<br>
        • Cursos Completados: {{ $stats['completed_courses'] }}<br>
        • Cursos en Progreso: {{ $stats['in_progress_courses'] }}<br>
        • Promedio General:
        @php
            $completedWithGrades = $enrollments->where('enrollmentResults')->where('enrollmentResults.final_grade', '>', 0);
            $average = $completedWithGrades->avg('enrollmentResults.final_grade');
        @endphp
        @if($average)
            <strong>{{ number_format($average, 1) }}</strong>
        @else
            -
        @endif
    </div>

    <div class="footer">
        Documento generado automáticamente el {{ $report_date }} - Sistema Académico
    </div>

</body>
</html>

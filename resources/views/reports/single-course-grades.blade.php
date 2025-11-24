<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Notas - {{ $enrollment->group->courseVersion->course->name }}</title>
    <style>
        /* ==============================
            ESTILOS GENERALES
        =============================== */
        body { 
            font-family: DejaVu Sans, sans-serif; 
            font-size: 12px; 
            color: #333;
        }

        .header {
            text-align: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #003366; 
            padding-bottom: 15px;
        }

        .institution {
            font-size: 18px; 
            font-weight: bold;
            color: #003366;
            letter-spacing: 0.5px;
        }

        .course-title {
            font-size: 15px; 
            color: #0055a5; 
            margin: 8px 0;
            font-weight: bold;
        }

        /* ==============================
            INFORMACIÓN DEL ESTUDIANTE
        =============================== */
        .student-info {
            margin-bottom: 20px; 
            background: #f1f6ff; 
            padding: 15px; 
            border-radius: 6px;
            border: 1px solid #d3e3ff;
        }

        .info-row {
            margin-bottom: 4px; 
        }

        /* ==============================
            TABLA
        =============================== */
        table {
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
        }

        th {
            background-color: #003366; 
            color: white; 
            padding: 8px; 
            border: 1px solid #002244;
            font-size: 11px;
        }

        td {
            padding: 7px; 
            border: 1px solid #ccc;
            font-size: 11px;
        }

        .text-center { 
            text-align: center; 
        }

        /* ==============================
            ESTILOS PARA NOTAS
        =============================== */
        .grade-excellent { 
            color: #1f8b30; 
            font-weight: bold; 
        }

        .grade-good { 
            color: #007b9e; 
        }

        .grade-poor { 
            color: #c91a1a; 
            font-weight: bold; 
        }

        /* ==============================
            RESULTADO FINAL
        =============================== */
        .final-result {
            margin-top: 20px;
            background: #e8ffec;
            border: 1px solid #b4e3bf;
            padding: 14px;
            border-radius: 6px;
            line-height: 1.5;
        }

        /* ==============================
            FOOTER
        =============================== */
        .footer {
            margin-top: 30px; 
            text-align: center; 
            font-size: 10px; 
            color: #666;
        }
    </style>
</head>
<body>

    <!-- ========== ENCABEZADO ========== -->
    <div class="header">
        <div class="institution">INSTITUTO DE CAPACITACIÓN</div>
        <div class="course-title">
            NOTAS - {{ strtoupper($enrollment->group->courseVersion->course->name) }}
        </div>
        <div>Grupo: {{ $enrollment->group->name }}</div>
    </div>

    <!-- ========== DATOS DEL ESTUDIANTE ========== -->
    <div class="student-info">
        <div class="info-row"><strong>Estudiante:</strong> {{ $student->fullname }}</div>
        <div class="info-row"><strong>DNI:</strong> {{ $student->dni }}</div>
        <div class="info-row"><strong>Fecha de Reporte:</strong> {{ $report_date }}</div>
    </div>

    <!-- ========== TABLA DE NOTAS ========== -->
    <table>
        <thead>
            <tr>
                <th>Evaluación</th>
                <th>Módulo</th>
                <th class="text-center">Fecha</th>
                <th class="text-center">Nota</th>
                <th>Comentarios</th>
            </tr>
        </thead>
        <tbody>
            @forelse($enrollment->grades as $grade)
            <tr>
                <td>{{ $grade->exam->title ?? 'Evaluación' }}</td>
                <td>{{ $grade->exam->module->title ?? '-' }}</td>
                <td class="text-center">
                    @if($grade->exam && $grade->exam->start_time)
                        {{ \Carbon\Carbon::parse($grade->exam->start_time)->format('d/m/Y') }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">
                    <span class="
                        grade-{{ 
                            $grade->grade >= 14 
                                ? 'excellent' 
                                : ($grade->grade >= 11 
                                    ? 'good' 
                                    : 'poor') 
                        }}">
                        {{ number_format($grade->grade, 1) }}
                    </span>
                </td>
                <td>{{ $grade->feedback ?: '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">
                    No hay notas registradas aún
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ========== RESULTADO FINAL ========== -->
    @if($enrollment->enrollmentResults)
    <div class="final-result">
        <strong>RESULTADO FINAL:</strong><br>
        Nota Final: 
        <span class="grade-excellent">
            {{ number_format($enrollment->enrollmentResults->final_grade, 1) }}
        </span><br>
        Estado: {{ strtoupper($enrollment->enrollmentResults->status) }}
    </div>
    @endif

    <!-- ========== FOOTER ========== -->
    <div class="footer">
        Documento generado automáticamente el {{ $report_date }} - Sistema Académico
    </div>

</body>
</html>

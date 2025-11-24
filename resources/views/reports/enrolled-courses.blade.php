<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Cursos Matriculados - {{ $student->fullname }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            background-color: #ffffff;
            color: #333;
            margin: 20px;
        }

        /* =================== HEADER =================== */
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
            letter-spacing: 0.5px;
        }

        .title {
            font-size: 16px;
            margin-top: 5px;
            font-weight: bold;
        }

        /* =================== STUDENT INFO =================== */
        .student-info {
            margin-bottom: 25px;
            background: #eef4ff;
            padding: 15px 20px;
            border-left: 4px solid #2c5aa0;
            border-radius: 5px;
        }

        .info-row {
            margin-bottom: 6px;
            font-size: 13px;
        }

        /* =================== TABLE =================== */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
        }

        .table th {
            background-color: #2c5aa0;
            color: white;
            padding: 10px;
            border: 1px solid #1e3d6f;
            text-transform: uppercase;
            font-size: 11px;
        }

        .table td {
            padding: 8px;
            border: 1px solid #cccccc;
        }

        /* Zebra effect */
        .table tr:nth-child(even) {
            background-color: #f6f8fc;
        }

        .text-center {
            text-align: center;
        }

        /* =================== STATUS BADGES =================== */
        .status-active {
            color: #28a745;
            font-weight: bold;
        }

        .status-completed {
            color: #0d8ecf;
            font-weight: bold;
        }

        .status-other {
            color: #6c757d;
            font-weight: bold;
        }

        /* =================== FOOTER =================== */
        .footer {
            margin-top: 35px;
            text-align: center;
            font-size: 10px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="institution">INSTITUTO DE CAPACITACIÓN</div>
        <div class="title">CURSOS MATRICULADOS</div>
    </div>

    <div class="student-info">
        <div class="info-row"><strong>Estudiante:</strong> {{ $student->fullname }}</div>
        <div class="info-row"><strong>DNI:</strong> {{ $student->dni }}</div>
        <div class="info-row"><strong>Fecha de Reporte:</strong> {{ $report_date }}</div>
        <div class="info-row"><strong>Total de Cursos:</strong> {{ $enrollments->count() }}</div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Curso</th>
                <th>Grupo</th>
                <th class="text-center">Fecha Inicio</th>
                <th class="text-center">Fecha Fin</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Nota Final</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($enrollments as $enrollment)
                <tr>
                    <td>{{ $enrollment->group->courseVersion->course->name }}</td>
                    <td>{{ $enrollment->group->name }}</td>
                    <td class="text-center">
                        {{ \Carbon\Carbon::parse($enrollment->group->start_date)->format('d/m/Y') }}
                    </td>
                    <td class="text-center">
                        {{ \Carbon\Carbon::parse($enrollment->group->end_date)->format('d/m/Y') }}
                    </td>
                    <td class="text-center">
                        @if ($enrollment->group->status->value === 'active' && $enrollment->academic_status->value === 'active')
                            <span class="status-active">ACTIVO</span>
                        @elseif($enrollment->result && $enrollment->result->status === 'approved')
                            <span class="status-completed">COMPLETADO</span>
                        @elseif($enrollment->academic_status->value === 'active')
                            <span class="status-active">ACTIVO</span>
                        @else
                            <span class="status-other">INACTIVO</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if ($enrollment->enrollmentResults)
                            {{ number_format($enrollment->enrollmentResults->final_grade, 1) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Documento generado automáticamente el {{ $report_date }} - Sistema Académico
    </div>
</body>

</html>

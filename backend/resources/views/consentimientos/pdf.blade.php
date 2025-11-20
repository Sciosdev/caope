<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimientos del expediente {{ $expediente->no_control }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 32px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 24px 0 8px; text-transform: uppercase; letter-spacing: 0.08em; }
        p { margin: 0 0 8px; }
        .text-sm { font-size: 11px; }
        .text-xs { font-size: 10px; }
        .muted { color: #6b7280; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .grid { display: flex; flex-wrap: wrap; margin: 0 -8px 16px; }
        .grid .col { flex: 1 1 50%; padding: 0 8px; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #9ca3af; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        td { font-size: 11px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .badge-danger { background: #dc2626; color: #ffffff; }
        .badge-secondary { background: #4b5563; color: #f9fafb; }
        .badge-success { background: #16a34a; color: #ffffff; }
        .badge-warning { background: #facc15; color: #1f2937; }
        .footer { margin-top: 32px; font-size: 10px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
    <header class="mb-4">
        <h1>Resumen de consentimientos</h1>
        <p class="text-sm muted">Expediente {{ $expediente->no_control }} &mdash; generado el {{ $fechaEmision->format('d/m/Y H:i') }}</p>
    </header>

    <section class="mb-4">
        <div class="grid">
            <div class="col">
                <p class="mb-2"><strong>Paciente:</strong> {{ $expediente->paciente }}</p>
                <p class="mb-2"><strong>Carrera:</strong> {{ $expediente->carrera }}</p>
                <p class="mb-2"><strong>Turno:</strong> {{ $expediente->turno }}</p>
            </div>
            <div class="col">
                <p class="mb-2"><strong>Tutor asignado:</strong> {{ optional($expediente->tutor)->name ?? 'No asignado' }}</p>
                <p class="mb-2"><strong>Coordinador:</strong> {{ optional($expediente->coordinador)->name ?? 'No asignado' }}</p>
                <p class="mb-2"><strong>Fecha de apertura:</strong> {{ optional($expediente->apertura)->format('d/m/Y') }}</p>
            </div>
        </div>
    </section>

    @if ($textoIntroduccion !== '')
        <section class="mb-4">
            <p class="text-sm">{{ $textoIntroduccion }}</p>
        </section>
    @endif

    <section>
        <h2>Consentimientos registrados</h2>
        @if ($consentimientos->isEmpty())
            <p class="text-sm">No hay consentimientos registrados para este expediente.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 6%;">#</th>
                        <th style="width: 38%;">Tipo</th>
                        <th style="width: 18%;">Requerido</th>
                        <th style="width: 18%;">Estado</th>
                        <th style="width: 20%;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($consentimientos as $consentimiento)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $consentimiento->tratamiento }}</td>
                        <td>
                            <span class="badge {{ $consentimiento->requerido ? 'badge-danger' : 'badge-secondary' }}">
                                {{ $consentimiento->requerido ? 'Obligatorio' : 'Opcional' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $consentimiento->aceptado ? 'badge-success' : 'badge-warning' }}">
                                {{ $consentimiento->aceptado ? 'Aceptado' : 'Pendiente' }}
                            </span>
                        </td>
                        <td>{{ optional($consentimiento->fecha)->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @if ($textoCierre !== '')
        <footer class="footer">
            <p>{{ $textoCierre }}</p>
        </footer>
    @endif
</body>
</html>

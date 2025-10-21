@php($actorName = $actor?->name ?? 'el sistema')
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Hola {{ $tutor?->name ?? 'docente' }},
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    El expediente <strong>{{ $expediente->no_control }}</strong> correspondiente a
    <strong>{{ $expediente->paciente }}</strong> te fue asignado por {{ $actorName }}.
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Puedes revisar los detalles del expediente iniciando sesión en la plataforma.
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Gracias por tu dedicación.
</p>

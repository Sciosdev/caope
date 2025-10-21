@php($actorName = $actor?->name ?? 'el sistema')
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Hola {{ $destinatario?->name ?? 'equipo' }},
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    El expediente <strong>{{ $expediente->no_control }}</strong> correspondiente a
    <strong>{{ $expediente->paciente }}</strong> fue cerrado por {{ $actorName }}.
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Gracias por completar el proceso y mantener la información al día.
</p>

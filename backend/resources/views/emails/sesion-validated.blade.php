@php($actorName = $actor?->name ?? 'el sistema')
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Hola {{ $destinatario?->name ?? 'equipo' }},
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    La sesión registrada el {{ optional($sesion->fecha)->format('d/m/Y') ?? 'día indicado' }}
    fue validada por {{ $actorName }}.
</p>
@if(! empty($observaciones))
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Notas de validación:
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px; white-space: pre-line;">
    {{ $observaciones }}
</p>
@endif
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Gracias por mantener actualizado el expediente {{ $sesion->expediente?->no_control ?? '' }}.
</p>

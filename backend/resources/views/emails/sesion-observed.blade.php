@php($actorName = $actor?->name ?? 'el sistema')
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Hola {{ $destinatario?->name ?? 'equipo' }},
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    La sesión registrada el {{ optional($sesion->fecha)->format('d/m/Y') ?? 'día indicado' }}
    fue marcada con observaciones por {{ $actorName }}.
</p>
@if($observaciones !== '')
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Observaciones:
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px; white-space: pre-line;">
    {{ $observaciones }}
</p>
@endif
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Revisa el expediente {{ $sesion->expediente?->no_control ?? 'correspondiente' }} para atender los comentarios.
</p>

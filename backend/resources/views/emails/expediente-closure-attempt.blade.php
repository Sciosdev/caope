@php($actorName = $actor?->name ?? 'el sistema')
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Hola {{ $destinatario?->name ?? 'equipo' }},
</p>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    El expediente <strong>{{ $expediente->no_control }}</strong> no pudo cerrarse porque se detectaron las siguientes observaciones
    durante la revisión realizada por {{ $actorName }}:
</p>
<ul style="font-family: Arial, sans-serif; font-size: 14px;">
    @foreach($errores as $mensaje)
        <li>{{ $mensaje }}</li>
    @endforeach
</ul>
<p style="font-family: Arial, sans-serif; font-size: 14px;">
    Te sugerimos atender los puntos anteriores y volver a intentar el cierre una vez solventados.
</p>

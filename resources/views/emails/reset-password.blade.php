@component('mail::message')
# Hola {{ $name }}

Has solicitado restablecer tu contraseña de **Plant Doctor**.

Haz clic en el botón de abajo para elegir una contraseña nueva:

@component('mail::button', ['url' => $url])
Restablecer contraseña
@endcomponent

@if($showWebFallback)
¿Prefieres la web? Abre este enlace en tu navegador: [Abrir en el navegador]({{ $webUrl }})
@else
Si el botón no funciona, copia y pega este enlace en tu navegador:
@endif

{{ $url }}

El enlace caduca en **60 minutos** y solo puede usarse una vez.

Si tú no solicitaste este cambio, ignora este correo: tu contraseña permanecerá igual.

Gracias,<br>
{{ config('app.name') }}
@endcomponent

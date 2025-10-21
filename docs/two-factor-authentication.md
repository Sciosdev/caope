# Autenticación en dos pasos (2FA)

Este documento describe cómo habilitar la autenticación en dos pasos con Laravel Fortify dentro de la plataforma y qué hacer si un usuario pierde acceso a su dispositivo autenticador.

## Activación de 2FA

1. Inicia sesión y navega a **Perfil** > **Autenticación en dos pasos**.
2. Pulsa **Habilitar autenticación en dos pasos**. Se generará un código QR y una clave de configuración.
3. Escanea el código QR con tu aplicación autenticadora (Google Authenticator, Authy, etc.) o introduce la clave de configuración manualmente.
4. Guarda los **códigos de recuperación** que se muestran. Estos permitirán acceder a tu cuenta si pierdes el dispositivo.
5. Introduce un código de verificación generado por la app autenticadora en el formulario de confirmación y selecciona **Confirmar configuración**. Una vez validado, 2FA quedará activo.

## Gestión continua

- Puedes regenerar nuevos códigos de recuperación en cualquier momento mediante el botón **Regenerar códigos de recuperación**.
- Para desactivar 2FA selecciona **Deshabilitar autenticación en dos pasos**. Se solicitará confirmación de contraseña antes de ejecutar la acción.

## Contingencia

1. **Uso de códigos de recuperación**: Si pierdes el dispositivo autenticador, utiliza uno de los códigos de recuperación almacenados al momento de activar 2FA para iniciar sesión. El sistema marcará ese código como usado inmediatamente.
2. **Sin códigos disponibles**: Si no cuentas con códigos de recuperación, contacta al equipo de soporte/administración. Ellos deberán revocar manualmente 2FA del usuario desde la consola de administración (eliminando los campos `two_factor_*` del registro) o mediante `php artisan tinker`, y verificar tu identidad siguiendo el procedimiento interno.
3. Tras recuperar el acceso, repite el proceso de activación para volver a contar con 2FA y generar nuevos códigos de recuperación.

> **Importante:** Los códigos de recuperación son únicos y se invalidan después de usarse. Manténlos en un lugar seguro pero accesible.

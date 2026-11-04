# Documentos de sesiones

Los archivos que se adjuntan al registrar o editar una sesión pertenecen a esa
sesión, no al listado general de anexos del expediente ni a Documentos
administrativos.

## Cómo consultarlos

1. Abrir el expediente y entrar en **Sesiones**.
2. Elegir **Documentos (n)** en la sesión correspondiente. El enlace lleva
   directamente a **Documentos de la sesión**, incluso si la sesión ya fue
   validada.
3. Usar **Ver** o **Descargar** junto al nombre del archivo. PDF, JPEG y PNG
   tienen vista previa; otros formatos ofrecen descarga sin visores externos.

El listado paginado de sesiones también incluye este acceso. Cuando no hay
adjuntos, el detalle lo indica expresamente.

## Carga y permisos

- Para subir archivos se usa **Documentos de la sesión**, fuera del editor de
  notas. Se admiten hasta 5 archivos por envío, de hasta 10 MB cada uno.
- Los archivos se mantienen privados. La vista previa y la descarga comprueban
  tanto su pertenencia a la sesión y al expediente como el permiso clínico de
  la cuenta. No se aplica la regla de documentos administrativos compartidos
  con todos los roles.
- Administración y PAPS aprobado conservan su acceso. Coordinadores acceden
  por las estrategias del expediente; docentes y estrategas, por su asignación
  como tutor. El facilitador debe seguir asignado al expediente y haber
  realizado la sesión.
- Una URL guardada no conserva acceso después de perder la asignación. Validar
  una sesión no permite editarla, pero no elimina el acceso autorizado a sus
  documentos.
- La vista previa comprueba el MIME real y solo muestra PDF, JPEG y PNG.
  HTML, SVG y documentos de oficina no se incrustan. Las respuestas de archivo
  incluyen `private`, `no-store`, `nosniff` y una política de sandbox.
- No se mueven ni duplican los documentos existentes. Un archivo que ya no
  existe devuelve 404; el indicador cuenta registros de adjuntos, no verifica
  físicamente cada archivo al cargar el listado.

## Verificación local

`tests/Feature/Sesiones/SesionAttachmentDiscoveryTest.php` cubre el recorrido de
carga y consulta, los permisos, enlaces, estados vacíos, sesiones validadas,
revocación de acceso, MIME y archivos faltantes. `SesionRevisionTest.php`
conserva la cobertura de revisión, almacenamiento privado y migración de
adjuntos anteriores. Las pruebas usan datos sintéticos y discos de prueba.

Estos cambios requieren publicación y despliegue antes de estar disponibles
en producción.

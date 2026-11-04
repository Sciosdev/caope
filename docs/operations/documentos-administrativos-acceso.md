# Consulta de documentos administrativos

Los documentos publicados (`aprobado_en` no nulo) se comparten con cualquier
cuenta autenticada y activa, independientemente de sus roles. La navegación
incluye **Documentos** en escritorio y móvil, con acciones **Ver** y
**Descargar**. No se crean enlaces públicos ni se amplía el acceso a anexos,
consentimientos o expedientes clínicos.

## Publicación y administración

- Administración conserva carga, edición, autorización y eliminación.
- PAPS aprobado puede enviar documentos para autorización. Los demás perfiles,
  incluido PAPS sin aprobación, no reciben permisos de carga o administración.
- Una cuenta activa PAPS sin aprobación puede leer el material ya publicado
  como lector ordinario; eso no la autoriza a actuar como PAPS.
- Los documentos pendientes no aparecen a lectores ni se entregan por su ID.
  Solo administración puede consultarlos antes de autorizarlos.
- Invitados e inactivos no acceden al listado, vista previa ni descarga.

## Vista previa y descarga

La vista previa detecta el MIME real del archivo y solo sirve PDF, JPEG y PNG
inline. No confía en el MIME enviado durante la carga. Para otros formatos se
muestra una página con la opción de descargar; no se usan visores externos.
Los archivos permanecen en su disco privado y se sirven mediante la aplicación,
con encabezados privados, `no-store`, `nosniff` y sandbox.

Los nombres de descarga conservan acentos y extensión, pero eliminan controles
y separadores de ruta. Si el archivo ya no existe, se responde 404.

Verificación: `tests/Feature/Admin/DocumentoAdministrativoAllRolesTest.php` y
`DocumentoAdministrativoAccessTest.php`.

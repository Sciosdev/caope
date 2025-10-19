# Flujo de ramas

Este repositorio sigue un flujo basado en dos ramas permanentes:

- `main`: rama protegida que refleja el código listo para liberarse. Requiere revisiones
  obligatorias antes de aceptar cambios.
- `develop`: rama de integración continua donde se fusionan las funcionalidades
  completadas antes de promoverlas a `main`.

## Ramas de funcionalidades

1. Parte desde la rama `develop` actualizada.
2. Crea una rama con el prefijo `feature/` que describa la funcionalidad, por ejemplo
   `feature/expedientes-filtros-avanzados`.
3. Trabaja y realiza commits siguiendo la convención [Conventional Commits](https://www.conventionalcommits.org/).
4. Sincroniza frecuentemente tu rama con `develop` para minimizar conflictos.

## Integración mediante Pull Requests

1. Abre un Pull Request desde tu rama `feature/*` hacia `develop`.
2. Solicita al menos una revisión. La rama `main` está configurada para rechazar
   actualizaciones que no incluyan la evidencia de revisión.
3. Atiende los comentarios y realiza commits adicionales según sea necesario.
4. Una vez aprobado, realiza el merge usando la estrategia estándar "Squash & Merge"
   o "Merge Commit" según convenga, asegurando que el mensaje final cumpla con
   Conventional Commits.
5. Promueve los cambios de `develop` a `main` únicamente mediante Pull Requests
   revisados cuando se tenga un corte listo para liberar.

## Correcciones urgentes

Para hotfixes críticos crea ramas `hotfix/*` basadas en `main` y sigue el mismo
proceso de Pull Request con revisión obligatoria antes de fusionar. Posteriormente
sincroniza `develop` con el parche aplicado en `main`.

# Guía de commits y Pull Requests

Esta guía complementa el [flujo de ramas](./branching.md) y establece las reglas
para contribuir al repositorio.

## Convenciones de commits

- Sigue el estándar [Conventional Commits](https://www.conventionalcommits.org/)
  para que los mensajes sean consistentes y legibles.
- Usa prefijos como `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, entre otros.
- Limita el título a 72 caracteres cuando sea posible y agrega descripciones
  adicionales en el cuerpo si la historia lo requiere.
- Relaciona el commit con el issue correspondiente utilizando referencias como
  `Refs #123` o `Fixes #123` en el cuerpo cuando aplique.

## Pull Requests

1. Asegúrate de que tu rama esté actualizada con `develop` antes de abrir el PR.
2. Rellena la plantilla proporcionada en `.github/pull_request_template.md`:
   - Resume los cambios.
   - Marca las validaciones ejecutadas.
   - Incluye la referencia al issue o tarea.
3. Solicita revisiones al menos a una persona del equipo.
4. No fusionar el PR si las comprobaciones automáticas fallan.
5. Prefiere "Squash & Merge" para mantener un historial limpio, a menos que el
   contexto requiera conservar commits individuales.

## Automatizaciones

El repositorio cuenta con un flujo de GitHub Actions que ejecuta
[Commitlint](https://commitlint.js.org/) y rechaza commits que no cumplan con la
convención. Si deseas validarlo localmente puedes instalar `@commitlint/cli` de
forma opcional y ejecutar:

```bash
npx commitlint --edit HEAD
```

Antes de subir cambios también valida que no se hayan introducido cadenas en
inglés ejecutando:

```bash
composer lint:locale
```

El comando utiliza `rg` (ripgrep) para revisar los directorios de código y
fallará en caso de encontrar alguna coincidencia. Asegúrate de corregir las
advertencias antes de abrir un Pull Request.

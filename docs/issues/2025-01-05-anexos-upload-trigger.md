# Botón de anexos sincronizado con FilePond

- **Contexto:** la vista de anexos tenía un botón secundario para subir archivos que quedaba deshabilitado cuando no había cargas pendientes.
- **Ajuste:** ahora el botón abre el diálogo de archivos cuando no existen pendientes y dispara `pond.processFiles()` cuando sí las hay, usando `isProcessingUploads` para bloquear interacciones durante la carga.
- **Extras:** el botón expone `data-action="select|process"` y `data-has-pending-uploads="true|false"` para estilos, y `pendingUploads` se recalcula también con el evento `updatefiles`.
- **Pruebas:** flujo manual en vista de anexos + `npm run build`.

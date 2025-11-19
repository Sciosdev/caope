<?php

return [
    'anexos' => [
        'counter_label' => ':count registros',
        'untitled' => 'Sin título',
        'generic_item' => 'anexo',
        'preview_alt' => 'Vista previa de :title',
        'no_preview' => 'Sin vista previa',
        'placeholder' => '—',
        'metadata' => [
            'type' => 'Tipo:',
            'size' => 'Tamaño:',
            'uploaded_by' => 'Subido por:',
            'date' => 'Fecha:',
            'size_value' => ':size KB',
        ],
        'actions' => [
            'download' => 'Descargar',
            'delete' => 'Eliminar',
        ],
        'delete_placeholder' => 'este anexo',
        'upload_button' => 'Subir archivos',
        'pond' => [
            'idle' => 'Arrastra y suelta tus archivos o <span class="filepond--label-action">explora</span>',
            'process_button' => 'Cargar',
            'process_button_processing' => 'Cargando…',
            'tap_to_cancel' => 'Cancelar',
            'tap_to_retry' => 'Reintentar',
        ],
        'errors' => [
            'generic_title' => 'Error',
            'upload_failed' => 'No fue posible subir el archivo.',
            'upload_unexpected' => 'Ocurrió un error al subir el archivo.',
            'revert_failed' => 'No fue posible revertir la carga del archivo.',
        ],
    ],
    'messages' => [
        'store_success' => 'Expediente creado correctamente.',
        'update_success' => 'Expediente actualizado correctamente.',
        'student_save_error' => 'No pudimos guardar tu expediente. Revisa la información e inténtalo nuevamente o contacta a tu tutor si el problema persiste.',
        'unexpected_save_error' => 'Ocurrió un error al guardar el expediente. Inténtalo nuevamente o contacta al soporte si el problema persiste.',
    ],
    'validation' => [
        'no_control_format' => 'El No. de Control debe cumplir con el formato esperado. Ejemplo: :example.',
    ],
];

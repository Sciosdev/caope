import { create, registerPlugin } from 'filepond';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import 'filepond/dist/filepond.min.css';

registerPlugin(FilePondPluginFileValidateType, FilePondPluginFileValidateSize);

const getAnexosTranslations = () => window?.translations?.expedientes?.anexos ?? {};

const formatTranslation = (template, replacements = {}) => {
    return Object.entries(replacements).reduce((carry, [placeholder, value]) => {
        const pattern = new RegExp(`:${placeholder}`, 'g');

        return carry.replace(pattern, value);
    }, template);
};

const resolveTranslationValue = (source, keys) => {
    return keys.reduce((carry, key) => {
        if (carry && typeof carry === 'object' && key in carry) {
            return carry[key];
        }

        return undefined;
    }, source);
};

const translateWith = (source, key, defaultValue, replacements = {}) => {
    const value = typeof key === 'string' ? resolveTranslationValue(source, key.split('.')) : undefined;
    const template = typeof value === 'string' ? value : defaultValue;
    const normalizedTemplate = typeof template === 'string' ? template : '';

    return formatTranslation(normalizedTemplate, replacements);
};

document.addEventListener('DOMContentLoaded', () => {
    const uploader = document.querySelector('[data-anexos-uploader]');

    if (!uploader) {
        return;
    }

    const translations = getAnexosTranslations();
    const t = (key, defaultValue, replacements = {}) => translateWith(translations, key, defaultValue, replacements);

    const uploadUrl = uploader.dataset.uploadUrl || '';
    const csrfToken = uploader.dataset.csrfToken || '';
    const tableBody = document.querySelector(uploader.dataset.tableTarget || '');
    const emptyState = document.querySelector(uploader.dataset.emptyTarget || '');
    const tableWrapper = document.querySelector(uploader.dataset.tableWrapper || '');
    const galleryWrapper = document.querySelector(uploader.dataset.galleryWrapper || '');
    const galleryGrid = document.querySelector(uploader.dataset.galleryTarget || '');
    const counter = document.querySelector('[data-anexos-counter]');
    const canDelete = uploader.dataset.canDelete === 'true';
    const hasActions = tableBody?.dataset.hasActions === 'true';
    const deleteUrls = new Map();

    if (!uploadUrl) {
        return;
    }

    const parseAcceptedTypes = (value) => {
        const knownTypes = {
            pdf: 'application/pdf',
            jpg: 'image/jpeg',
            jpeg: 'image/jpeg',
            png: 'image/png',
            doc: 'application/msword',
            docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            xls: 'application/vnd.ms-excel',
            xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ppt: 'application/vnd.ms-powerpoint',
            pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            txt: 'text/plain',
            csv: 'text/csv',
            zip: 'application/zip',
        };

        const normalizedTypes = new Set();

        return value
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item.length > 0)
            .map((item) => {
                if (item.includes('/')) {
                    return item;
                }

                const normalized = item.replace(/^\./, '').toLowerCase();

                return knownTypes[normalized] ?? `.${normalized}`;
            })
            .filter((item) => {
                if (normalizedTypes.has(item)) {
                    return false;
                }

                normalizedTypes.add(item);

                return true;
            });
    };

    const rawAcceptedTypes = uploader.dataset.acceptedTypes || '';
    const rawMaxSize = uploader.dataset.maxSize ?? '';
    const acceptedTypes = parseAcceptedTypes(rawAcceptedTypes);
    const maxFileSizeKilobytes = Number.parseInt(rawMaxSize, 10);
    const maxFileSizeMegabytes = Number.isFinite(maxFileSizeKilobytes)
        ? Number.parseFloat((maxFileSizeKilobytes / 1024).toFixed(1))
        : null;
    const normalizedMaxFileSize =
        Number.isFinite(maxFileSizeMegabytes) && maxFileSizeMegabytes > 0
            ? `${Number.isInteger(maxFileSizeMegabytes) ? maxFileSizeMegabytes : maxFileSizeMegabytes.toString()}MB`
            : null;

    console.info('[Anexos] Uploader config from dataset', {
        acceptedTypes: rawAcceptedTypes,
        parsedAcceptedFileTypes: acceptedTypes,
        maxSize: rawMaxSize,
        parsedMaxFileSizeKb: Number.isFinite(maxFileSizeKilobytes) ? maxFileSizeKilobytes : null,
        parsedMaxFileSizeMb: Number.isFinite(maxFileSizeMegabytes) ? maxFileSizeMegabytes : null,
        filePondOptions: {
            acceptedFileTypes: acceptedTypes,
            maxFileSize: normalizedMaxFileSize,
        },
    });

    const formatBytesToKilobytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0.0';
        }

        return (bytes / 1024).toFixed(1);
    };

    const placeholderText = t('placeholder', '—');
    const updateCounter = () => {
        if (!counter) {
            return;
        }

        let total = 0;

        if (tableBody) {
            total = tableBody.querySelectorAll('tr').length;
        } else if (galleryGrid) {
            total = galleryGrid.querySelectorAll('[data-anexo-id]').length;
        }

        counter.textContent = t('counter_label', ':count registros', { count: total });
    };

    const toggleEmptyState = () => {
        const hasRows = tableBody?.querySelector('tr') !== null || galleryGrid?.querySelector('[data-anexo-id]') !== null;

        if (tableWrapper) {
            tableWrapper.classList.toggle('d-none', !hasRows);
        }

        if (galleryWrapper) {
            galleryWrapper.classList.toggle('d-none', !hasRows);
        }

        if (emptyState) {
            emptyState.classList.toggle('d-none', hasRows);
        }

        updateCounter();
    };

    const createCell = (text) => {
        const cell = document.createElement('td');
        cell.textContent = text ?? placeholderText;

        return cell;
    };

    const createTitleCell = (anexo) => {
        const cell = document.createElement('td');
        const title = anexo?.titulo ?? t('untitled', 'Sin título');

        if (anexo?.download_url) {
            const link = document.createElement('a');
            link.href = anexo.download_url;
            link.textContent = title;
            link.className = 'text-decoration-none';
            link.rel = 'noopener';

            cell.appendChild(link);

            return cell;
        }

        cell.textContent = title;

        return cell;
    };

    const createActionCell = (anexo) => {
        const cell = document.createElement('td');
        cell.classList.add('text-end');

        if (!hasActions) {
            return cell;
        }

        const hasDownload = Boolean(anexo?.download_url);
        const canDeleteCurrent = Boolean(canDelete && anexo?.delete_url);

        if (!hasDownload && !canDeleteCurrent) {
            const placeholder = document.createElement('span');
            placeholder.className = 'text-muted small';
            placeholder.textContent = placeholderText;
            cell.appendChild(placeholder);

            return cell;
        }

        if (hasDownload) {
            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';

            const downloadButton = document.createElement('a');
            downloadButton.href = anexo.download_url;
            downloadButton.className = 'btn btn-outline-secondary';
            downloadButton.textContent = t('actions.download', 'Descargar');
            group.appendChild(downloadButton);

            if (canDeleteCurrent) {
                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'btn btn-outline-danger';
                deleteButton.textContent = t('actions.delete', 'Eliminar');
                deleteButton.setAttribute('data-bs-toggle', 'modal');
                deleteButton.setAttribute('data-bs-target', '#anexoDeleteModal');
                deleteButton.dataset.deleteUrl = anexo.delete_url;
                deleteButton.dataset.anexoTitle = anexo.titulo ?? t('delete_placeholder', 'este anexo');
                group.appendChild(deleteButton);
            }

            cell.appendChild(group);

            return cell;
        }

        if (canDeleteCurrent) {
            const deleteOnlyButton = document.createElement('button');
            deleteOnlyButton.type = 'button';
            deleteOnlyButton.className = 'btn btn-outline-danger btn-sm';
            deleteOnlyButton.textContent = t('actions.delete', 'Eliminar');
            deleteOnlyButton.setAttribute('data-bs-toggle', 'modal');
            deleteOnlyButton.setAttribute('data-bs-target', '#anexoDeleteModal');
            deleteOnlyButton.dataset.deleteUrl = anexo.delete_url;
            deleteOnlyButton.dataset.anexoTitle = anexo.titulo ?? t('delete_placeholder', 'este anexo');
            cell.appendChild(deleteOnlyButton);

            return cell;
        }

        return cell;
    };

    const isImageType = (tipo) => {
        if (!tipo) {
            return false;
        }

        const value = String(tipo).toLowerCase();

        return value.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(value);
    };

    const createGalleryCard = (anexo) => {
        const col = document.createElement('div');
        col.className = 'col';
        col.dataset.anexoId = String(anexo.id);

        const card = document.createElement('div');
        card.className = 'card h-100 shadow-sm';
        col.appendChild(card);

        const ratio = document.createElement('div');
        ratio.className = 'ratio ratio-4x3 bg-light border-bottom';
        card.appendChild(ratio);

        if (isImageType(anexo.tipo) && anexo.preview_url) {
            const img = document.createElement('img');
            img.src = anexo.preview_url;
            img.alt = t('preview_alt', 'Vista previa de :title', {
                title: anexo.titulo ?? t('generic_item', 'anexo'),
            });
            img.className = 'img-fluid w-100 h-100 object-fit-cover rounded-top';
            ratio.appendChild(img);
        } else {
            const placeholder = document.createElement('div');
            placeholder.className = 'd-flex h-100 align-items-center justify-content-center text-muted flex-column';

            const label = document.createElement('span');
            label.className = 'fw-semibold';
            label.textContent = t('no_preview', 'Sin vista previa');
            placeholder.appendChild(label);

            const typeLabel = document.createElement('small');
            typeLabel.className = 'text-muted';
            typeLabel.textContent = String(anexo.tipo ?? '').toUpperCase() || placeholderText;
            placeholder.appendChild(typeLabel);

            ratio.appendChild(placeholder);
        }

        const body = document.createElement('div');
        body.className = 'card-body d-flex flex-column';
        card.appendChild(body);

        const title = document.createElement('h6');
        title.className = 'card-title text-truncate';
        const cardTitle = anexo.titulo ?? t('untitled', 'Sin título');
        title.title = cardTitle;
        title.textContent = cardTitle;
        body.appendChild(title);

        const metaList = document.createElement('ul');
        metaList.className = 'list-unstyled small text-muted mb-3';
        const metadata = [
            [t('metadata.type', 'Tipo:'), anexo.tipo || placeholderText],
            [
                t('metadata.size', 'Tamaño:'),
                t('metadata.size_value', ':size KB', {
                    size: anexo.tamano_legible ?? formatBytesToKilobytes(anexo.tamano ?? 0),
                }),
            ],
            [t('metadata.uploaded_by', 'Subido por:'), anexo.subido_por || placeholderText],
            [t('metadata.date', 'Fecha:'), anexo.fecha || placeholderText],
        ];

        metadata.forEach(([label, value]) => {
            const item = document.createElement('li');
            const labelSpan = document.createElement('span');
            labelSpan.className = 'text-dark';
            labelSpan.textContent = label;
            item.appendChild(labelSpan);
            item.append(` ${value}`);
            metaList.appendChild(item);
        });

        body.appendChild(metaList);

        const actions = document.createElement('div');
        actions.className = 'mt-auto d-flex flex-wrap gap-2';
        body.appendChild(actions);

        if (anexo.download_url) {
            const downloadButton = document.createElement('a');
            downloadButton.href = anexo.download_url;
            downloadButton.className = 'btn btn-outline-secondary btn-sm';
            downloadButton.textContent = t('actions.download', 'Descargar');
            actions.appendChild(downloadButton);
        }

        if (canDelete && anexo.delete_url) {
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-outline-danger btn-sm';
            deleteButton.textContent = t('actions.delete', 'Eliminar');
            deleteButton.setAttribute('data-bs-toggle', 'modal');
            deleteButton.setAttribute('data-bs-target', '#anexoDeleteModal');
            deleteButton.dataset.deleteUrl = anexo.delete_url;
            deleteButton.dataset.anexoTitle = anexo.titulo ?? t('delete_placeholder', 'este anexo');
            actions.appendChild(deleteButton);
        }

        return col;
    };

    const addCardToGallery = (anexo) => {
        if (!galleryGrid || !anexo || !anexo.id) {
            return;
        }

        const existingCard = galleryGrid.querySelector(`[data-anexo-id="${anexo.id}"]`);

        if (existingCard) {
            existingCard.remove();
        }

        const card = createGalleryCard(anexo);
        galleryGrid.prepend(card);
    };

    const addRowToTable = (anexo) => {
        if (!tableBody || !anexo || !anexo.id) {
            return;
        }

        const row = document.createElement('tr');
        row.dataset.anexoId = String(anexo.id);

        row.appendChild(createTitleCell(anexo));
        row.appendChild(createCell(anexo.tipo || placeholderText));
        row.appendChild(
            createCell(
                t('metadata.size_value', ':size KB', {
                    size: anexo.tamano_legible ?? formatBytesToKilobytes(anexo.tamano ?? 0),
                }),
            ),
        );
        row.appendChild(createCell(anexo.subido_por || placeholderText));
        row.appendChild(createCell(anexo.fecha || placeholderText));

        if (hasActions) {
            row.appendChild(createActionCell(anexo));
        }

        tableBody.prepend(row);
        addCardToGallery(anexo);
        toggleEmptyState();
    };

    const removeRow = (anexoId) => {
        if (!anexoId) {
            return;
        }

        if (tableBody) {
            const row = Array.from(tableBody.querySelectorAll('tr')).find((item) => item.dataset.anexoId === String(anexoId));

            if (row) {
                row.remove();
            }
        }

        if (galleryGrid) {
            const card = Array.from(galleryGrid.querySelectorAll('[data-anexo-id]')).find(
                (item) => item.dataset.anexoId === String(anexoId),
            );

            if (card) {
                card.remove();
            }
        }

        toggleEmptyState();
    };

    const pond = create(uploader, {
        allowMultiple: true,
        maxFileSize: normalizedMaxFileSize,
        acceptedFileTypes: acceptedTypes,
        labelIdle: t('pond.idle', 'Arrastra y suelta tus archivos o <span class="filepond--label-action">explora</span>'),
        instantUpload: false,
        labelButtonProcessItem: t('pond.process_button', 'Cargar'),
        labelButtonProcessItemProcessing: t('pond.process_button_processing', 'Cargando…'),
        labelTapToCancel: t('pond.tap_to_cancel', 'Cancelar'),
        labelTapToRetry: t('pond.tap_to_retry', 'Reintentar'),
        credits: false,
        server: {
            process: (fieldName, file, metadata, load, error, progress, abort) => {
                console.info('[Anexos] Iniciando subida', {
                    fileName: file?.name,
                    fileType: file?.type,
                    fileSize: file?.size,
                    acceptedFileTypes: acceptedTypes,
                    maxFileSize: normalizedMaxFileSize,
                });

                const formData = new FormData();
                formData.append('archivo', file, file.name);

                const request = new XMLHttpRequest();
                request.open('POST', uploadUrl);
                request.responseType = 'json';
                request.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                request.setRequestHeader('Accept', 'application/json');

                request.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        progress(event.lengthComputable, event.loaded, event.total);
                    }
                };

                request.onload = () => {
                    const status = request.status;
                    const response = request.response ?? {};

                    console.info('[Anexos] Respuesta de subida', {
                        status,
                        response,
                    });

                    if (status >= 200 && status < 300) {
                        const anexoId = String(response.id ?? '');

                        if (anexoId) {
                            deleteUrls.set(anexoId, response.delete_url);
                            addRowToTable(response);
                        }

                        load(anexoId || response.message || 'ok');
                        return;
                    }

                    const message = response?.message || t('errors.upload_failed', 'No fue posible subir el archivo.');
                    error(message);
                };

                request.onerror = () => {
                    error(t('errors.upload_unexpected', 'Ocurrió un error al subir el archivo.'));
                };

                request.onabort = () => {
                    abort();
                };

                request.send(formData);

                return {
                    abort: () => {
                        request.abort();
                    },
                };
            },
            revert: (uniqueId, load, error) => {
                const deleteUrl = deleteUrls.get(String(uniqueId));

                if (!deleteUrl) {
                    load();
                    return;
                }

                fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error(t('errors.generic_title', 'Error'));
                        }

                        deleteUrls.delete(String(uniqueId));
                        removeRow(uniqueId);
                        load();
                    })
                    .catch(() => {
                        error(t('errors.revert_failed', 'No fue posible revertir la carga del archivo.'));
                    });
            },
        },
    });

    const uploadTrigger = document.querySelector('[data-anexos-upload-trigger]');
    const pendingUploads = new Set();
    let isProcessingUploads = false;

    const updateUploadTriggerState = () => {
        if (!uploadTrigger) {
            return;
        }

        const hasPendingUploads = pendingUploads.size > 0;

        uploadTrigger.disabled = isProcessingUploads;
        uploadTrigger.setAttribute('aria-disabled', isProcessingUploads ? 'true' : 'false');
        uploadTrigger.dataset.action = hasPendingUploads ? 'process' : 'select';
        uploadTrigger.dataset.hasPendingUploads = hasPendingUploads ? 'true' : 'false';
    };

    const refreshPendingUploads = () => {
        pendingUploads.clear();

        pond.getFiles().forEach((fileItem) => {
            const serverId = fileItem?.serverId;
            const hasServerId = typeof serverId === 'string' ? serverId.length > 0 : Boolean(serverId);

            if (!hasServerId && fileItem?.id) {
                pendingUploads.add(fileItem.id);
            }
        });

        updateUploadTriggerState();
    };

    if (uploadTrigger) {
        uploadTrigger.addEventListener('click', (event) => {
            if (isProcessingUploads) {
                event.preventDefault();
                return;
            }

            if (pendingUploads.size === 0) {
                event.preventDefault();

                if (typeof uploader?.click === 'function') {
                    uploader.click();
                }

                return;
            }

            event.preventDefault();

            isProcessingUploads = true;
            updateUploadTriggerState();

            pond.processFiles()
                .then(() => {
                    isProcessingUploads = false;
                    updateUploadTriggerState();
                })
                .catch(() => {
                    isProcessingUploads = false;
                    updateUploadTriggerState();

                    if (pendingUploads.size > 0) {
                        const message = t('errors.upload_unexpected', 'Ocurrió un error al subir el archivo.');
                        if (typeof window !== 'undefined' && typeof window.alert === 'function') {
                            window.alert(message);
                        }
                    }
                });
        });

        pond.on('addfile', (error, fileItem) => {
            console.info('[Anexos] addfile', {
                error: error
                    ? error?.message || error?.body || error?.main || String(error)
                    : null,
                fileName: fileItem?.filename,
                fileType: fileItem?.fileType,
                fileSize: fileItem?.fileSize,
                fileExtension: fileItem?.fileExtension,
                status: fileItem?.status,
            });

            refreshPendingUploads();
        });

        pond.on('removefile', () => {
            refreshPendingUploads();
        });

        pond.on('processfile', () => {
            refreshPendingUploads();
        });

        pond.on('updatefiles', () => {
            refreshPendingUploads();
        });

        pond.on('processfiles', () => {
            isProcessingUploads = false;
            refreshPendingUploads();
        });

        refreshPendingUploads();
    }

    pond.setOptions({
        allowProcess: true,
        allowRevert: true,
        allowReplace: false,
    });

    toggleEmptyState();
});

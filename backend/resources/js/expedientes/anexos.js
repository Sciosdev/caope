import { create, registerPlugin } from 'filepond';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import 'filepond/dist/filepond.min.css';

registerPlugin(FilePondPluginFileValidateType, FilePondPluginFileValidateSize);

document.addEventListener('DOMContentLoaded', () => {
    const uploader = document.querySelector('[data-anexos-uploader]');

    if (!uploader) {
        return;
    }

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
        };

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
            });
    };

    const acceptedTypes = parseAcceptedTypes(uploader.dataset.acceptedTypes || '');
    const maxFileSize = Number.parseInt(uploader.dataset.maxSize ?? '', 10);

    const formatBytesToKilobytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0.0';
        }

        return (bytes / 1024).toFixed(1);
    };

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

        counter.textContent = `${total} registros`;
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
        cell.textContent = text ?? '—';

        return cell;
    };

    const createTitleCell = (anexo) => {
        const cell = document.createElement('td');
        const title = anexo?.titulo ?? 'Sin título';

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
            placeholder.textContent = '—';
            cell.appendChild(placeholder);

            return cell;
        }

        if (hasDownload) {
            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';

            const downloadButton = document.createElement('a');
            downloadButton.href = anexo.download_url;
            downloadButton.className = 'btn btn-outline-secondary';
            downloadButton.textContent = 'Descargar';
            group.appendChild(downloadButton);

            if (canDeleteCurrent) {
                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'btn btn-outline-danger';
                deleteButton.textContent = 'Eliminar';
                deleteButton.setAttribute('data-bs-toggle', 'modal');
                deleteButton.setAttribute('data-bs-target', '#anexoDeleteModal');
                deleteButton.dataset.deleteUrl = anexo.delete_url;
                deleteButton.dataset.anexoTitle = anexo.titulo ?? 'este anexo';
                group.appendChild(deleteButton);
            }

            cell.appendChild(group);

            return cell;
        }

        if (canDeleteCurrent) {
            const deleteOnlyButton = document.createElement('button');
            deleteOnlyButton.type = 'button';
            deleteOnlyButton.className = 'btn btn-outline-danger btn-sm';
            deleteOnlyButton.textContent = 'Eliminar';
            deleteOnlyButton.setAttribute('data-bs-toggle', 'modal');
            deleteOnlyButton.setAttribute('data-bs-target', '#anexoDeleteModal');
            deleteOnlyButton.dataset.deleteUrl = anexo.delete_url;
            deleteOnlyButton.dataset.anexoTitle = anexo.titulo ?? 'este anexo';
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
            img.alt = `Vista previa de ${anexo.titulo ?? 'anexo'}`;
            img.className = 'img-fluid w-100 h-100 object-fit-cover rounded-top';
            ratio.appendChild(img);
        } else {
            const placeholder = document.createElement('div');
            placeholder.className = 'd-flex h-100 align-items-center justify-content-center text-muted flex-column';

            const label = document.createElement('span');
            label.className = 'fw-semibold';
            label.textContent = 'Sin vista previa';
            placeholder.appendChild(label);

            const typeLabel = document.createElement('small');
            typeLabel.className = 'text-muted';
            typeLabel.textContent = String(anexo.tipo ?? '').toUpperCase() || '—';
            placeholder.appendChild(typeLabel);

            ratio.appendChild(placeholder);
        }

        const body = document.createElement('div');
        body.className = 'card-body d-flex flex-column';
        card.appendChild(body);

        const title = document.createElement('h6');
        title.className = 'card-title text-truncate';
        title.title = anexo.titulo ?? 'Sin título';
        title.textContent = anexo.titulo ?? 'Sin título';
        body.appendChild(title);

        const metaList = document.createElement('ul');
        metaList.className = 'list-unstyled small text-muted mb-3';
        const metadata = [
            ['Tipo:', anexo.tipo || '—'],
            ['Tamaño:', `${anexo.tamano_legible ?? formatBytesToKilobytes(anexo.tamano ?? 0)} KB`],
            ['Subido por:', anexo.subido_por || '—'],
            ['Fecha:', anexo.fecha || '—'],
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
            downloadButton.textContent = 'Descargar';
            actions.appendChild(downloadButton);
        }

        if (canDelete && anexo.delete_url) {
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-outline-danger btn-sm';
            deleteButton.textContent = 'Eliminar';
            deleteButton.setAttribute('data-bs-toggle', 'modal');
            deleteButton.setAttribute('data-bs-target', '#anexoDeleteModal');
            deleteButton.dataset.deleteUrl = anexo.delete_url;
            deleteButton.dataset.anexoTitle = anexo.titulo ?? 'este anexo';
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
        row.appendChild(createCell(anexo.tipo || '—'));
        row.appendChild(createCell(`${anexo.tamano_legible ?? formatBytesToKilobytes(anexo.tamano ?? 0)} KB`));
        row.appendChild(createCell(anexo.subido_por || '—'));
        row.appendChild(createCell(anexo.fecha || '—'));

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
            const card = Array.from(galleryGrid.querySelectorAll('[data-anexo-id]')).find((item) => item.dataset.anexoId === String(anexoId));

            if (card) {
                card.remove();
            }
        }

        toggleEmptyState();
    };

    const pond = create(uploader, {
        allowMultiple: true,
        maxFileSize: Number.isFinite(maxFileSize) && maxFileSize > 0 ? `${maxFileSize}KB` : null,
        acceptedFileTypes: acceptedTypes,
        labelIdle: 'Arrastra y suelta tus archivos o <span class="filepond--label-action">explora</span>',
        credits: false,
        server: {
            process: (fieldName, file, metadata, load, error, progress, abort) => {
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

                    if (status >= 200 && status < 300) {
                        const anexoId = String(response.id ?? '');

                        if (anexoId) {
                            deleteUrls.set(anexoId, response.delete_url);
                            addRowToTable(response);
                        }

                        load(anexoId || response.message || 'ok');
                        return;
                    }

                    const message = response?.message || 'No fue posible subir el archivo.';
                    error(message);
                };

                request.onerror = () => {
                    error('Ocurrió un error al subir el archivo.');
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
                            throw new Error('Error');
                        }

                        deleteUrls.delete(String(uniqueId));
                        removeRow(uniqueId);
                        load();
                    })
                    .catch(() => {
                        error('No fue posible revertir la carga del archivo.');
                    });
            },
        },
    });

    pond.setOptions({
        allowProcess: true,
        allowRevert: true,
        allowReplace: false,
    });

    toggleEmptyState();
});

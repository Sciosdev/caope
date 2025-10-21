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
        if (!counter || !tableBody) {
            return;
        }

        const total = tableBody.querySelectorAll('tr').length;
        counter.textContent = `${total} registros`;
    };

    const toggleEmptyState = () => {
        if (!tableBody) {
            return;
        }

        const hasRows = tableBody.querySelector('tr') !== null;

        if (tableWrapper) {
            tableWrapper.classList.toggle('d-none', !hasRows);
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

    const createActionCell = (deleteUrl) => {
        const cell = document.createElement('td');
        cell.classList.add('text-end');

        if (!hasActions) {
            return cell;
        }

        if (!canDelete || !deleteUrl) {
            const placeholder = document.createElement('span');
            placeholder.className = 'text-muted small';
            placeholder.textContent = '—';
            cell.appendChild(placeholder);

            return cell;
        }

        const form = document.createElement('form');
        form.action = deleteUrl;
        form.method = 'post';
        form.setAttribute('data-anexo-delete-form', '');

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = csrfToken;
        form.appendChild(tokenInput);

        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        form.appendChild(methodInput);

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = 'btn btn-link btn-sm text-danger p-0';
        button.textContent = 'Eliminar';
        form.appendChild(button);

        cell.appendChild(form);

        return cell;
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
            row.appendChild(createActionCell(anexo.delete_url));
        }

        tableBody.prepend(row);
        toggleEmptyState();
    };

    const removeRow = (anexoId) => {
        if (!tableBody || !anexoId) {
            return;
        }

        const row = Array.from(tableBody.querySelectorAll('tr')).find((item) => item.dataset.anexoId === String(anexoId));

        if (row) {
            row.remove();
            toggleEmptyState();
        }
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

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-anexo-delete-form]')) {
            return;
        }

        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        const formData = new FormData(form);

        fetch(form.action, {
            method: form.method?.toUpperCase() === 'GET' ? 'GET' : 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: form.method?.toUpperCase() === 'GET' ? null : formData,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Error');
                }

                const row = form.closest('tr');

                if (row?.dataset.anexoId) {
                    deleteUrls.delete(row.dataset.anexoId);
                    removeRow(row.dataset.anexoId);
                }
            })
            .catch(() => {
                window.alert('No fue posible eliminar el anexo. Intenta nuevamente.');
            })
            .finally(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
    }, true);
});

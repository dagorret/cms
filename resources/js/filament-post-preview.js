(function registerPostPreview(global) {
    const fields = [
        'title',
        'body',
        'type',
        'site_id',
        'category_id',
        'published_at',
        'keywords',
        'has_math',
    ];

    function notifyError(message) {
        if (typeof global.FilamentNotification === 'function') {
            new global.FilamentNotification()
                .title('No se pudo generar la vista previa')
                .body(message)
                .danger()
                .send();

            return;
        }

        console.error(message);
    }

    async function currentEditorBody() {
        const editor = document.querySelector('[data-faro-editorjs="body"]');

        if (!editor || typeof editor.faroSyncEditorJs !== 'function') {
            return undefined;
        }

        return editor.faroSyncEditorJs();
    }

    function currentFormPayload(wire) {
        const state = wire?.data ?? {};

        return fields.reduce((payload, field) => {
            if (Object.prototype.hasOwnProperty.call(state, field)) {
                payload[field] = state[field];
            }

            return payload;
        }, {});
    }

    async function open(wire, endpoint) {
        const previewWindow = global.open('about:blank', '_blank');

        if (!previewWindow) {
            notifyError('El navegador bloqueó la nueva pestaña. Permití ventanas emergentes para el panel.');
            return;
        }

        // Conservamos la referencia para poder navegar la pestaña y cortamos el acceso inverso.
        try {
            previewWindow.opener = null;
            previewWindow.document.title = 'Generando vista previa…';
            previewWindow.document.body.textContent = 'Generando vista previa…';
        } catch {
            // La pestaña sigue siendo navegable aunque el navegador restrinja about:blank.
        }

        try {
            const payload = currentFormPayload(wire);
            const editorBody = await currentEditorBody();

            if (editorBody !== undefined) {
                payload.body = editorBody;
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok || typeof result.url !== 'string') {
                const validationMessage = result?.errors
                    ? Object.values(result.errors).flat().join(' ')
                    : null;

                throw new Error(validationMessage || result?.message || `Error HTTP ${response.status}`);
            }

            previewWindow.location.replace(result.url);
        } catch (error) {
            previewWindow.close();
            notifyError(error instanceof Error ? error.message : 'Error desconocido.');
        }
    }

    global.FaroPostPreview = { open };
})(window);

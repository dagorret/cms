import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/filament-post-preview.js', import.meta.url), 'utf8');

test('abre la pestaña antes de esperar y envía el Editor.js actual no guardado', async () => {
    let opened = false;
    let fetched = false;
    let requestedBody = null;
    let navigatedTo = null;
    const previewWindow = {
        opener: {},
        document: { title: '', body: { textContent: '' } },
        location: { replace: (url) => { navigatedTo = url; } },
        close: () => assert.fail('No debe cerrar una preview exitosa.'),
    };
    const unsavedBody = {
        blocks: [{ type: 'markdown', data: { source: '## Cambio todavía no guardado\n\nCórdoba 🚀' } }],
    };
    const editor = {
        faroSyncEditorJs: async () => {
            assert.equal(opened, true, 'La pestaña debe abrirse durante el gesto del usuario.');
            return unsavedBody;
        },
    };
    const context = {
        console,
        window: {
            open: () => {
                opened = true;
                return previewWindow;
            },
            FilamentNotification: null,
        },
        document: {
            querySelector: (selector) => {
                if (selector === '[data-faro-editorjs="body"]') return editor;
                if (selector === 'meta[name="csrf-token"]') return { getAttribute: () => 'token' };
                return null;
            },
        },
        fetch: async (_url, options) => {
            fetched = true;
            requestedBody = JSON.parse(options.body);
            return {
                ok: true,
                json: async () => ({ url: '/dash/post-preview?t=123' }),
            };
        },
    };

    vm.runInNewContext(source, context);

    await context.window.FaroPostPreview.open({
        data: {
            title: 'Título sin guardar',
            body: { blocks: [{ type: 'paragraph', data: { text: 'Persistido' } }] },
            slug: 'no-debe-enviarse',
        },
    }, '/dash/post-preview');

    assert.equal(opened, true);
    assert.equal(fetched, true);
    assert.equal(requestedBody.title, 'Título sin guardar');
    assert.deepEqual(requestedBody.body, unsavedBody);
    assert.equal(Object.hasOwn(requestedBody, 'slug'), false);
    assert.equal(navigatedTo, '/dash/post-preview?t=123');
    assert.equal(previewWindow.opener, null);
});

test('cierra la pestaña y notifica cuando falla la generación', async () => {
    let closed = false;
    let notificationSent = false;
    class Notification {
        title() { return this; }
        body() { return this; }
        danger() { return this; }
        send() { notificationSent = true; return this; }
    }
    const context = {
        console,
        window: {
            open: () => ({
                opener: {},
                document: { title: '', body: { textContent: '' } },
                location: { replace: () => {} },
                close: () => { closed = true; },
            }),
            FilamentNotification: Notification,
        },
        document: {
            querySelector: (selector) => selector.includes('csrf')
                ? { getAttribute: () => 'token' }
                : null,
        },
        fetch: async () => ({ ok: false, status: 500, json: async () => ({ message: 'falló' }) }),
    };

    vm.runInNewContext(source, context);
    await context.window.FaroPostPreview.open({ data: {} }, '/dash/post-preview');

    assert.equal(closed, true);
    assert.equal(notificationSent, true);
});

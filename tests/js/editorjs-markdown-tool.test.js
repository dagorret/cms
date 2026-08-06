import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

class FakeElement {
    constructor(tagName) {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.className = '';
        this.value = '';
        this.placeholder = '';
        this.readOnly = false;
        this.scrollHeight = 320;
        this.style = {};
        this.attributes = {};
        this.listeners = new Map();
    }

    appendChild(child) {
        this.children.push(child);
        return child;
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    addEventListener(name, listener) {
        this.listeners.set(name, listener);
    }

    removeEventListener(name) {
        this.listeners.delete(name);
    }

    querySelector(selector) {
        return selector === '.faro-markdown-tool__input' ? this.children[0] : null;
    }
}

function loadTool() {
    const window = {};
    const document = {
        createElement: (tagName) => new FakeElement(tagName),
    };
    const source = readFileSync(new URL('../../resources/js/editorjs-markdown-tool.js', import.meta.url), 'utf8');

    vm.runInNewContext(source, { window, document, globalThis: window, queueMicrotask });

    return window;
}

test('registra el tool Markdown con toolbox y sanitización de fuente literal', () => {
    const window = loadTool();
    const Tool = window.FaroMarkdownTool;

    assert.equal(Tool.toolbox.title, 'Markdown');
    assert.match(Tool.toolbox.icon, /<svg/);
    assert.deepEqual({ ...Tool.sanitize }, { source: true });
    assert.equal(Tool.isReadOnlySupported, true);
    assert.equal(window.filamentEditorJsTools.markdown.class, Tool);
});

test('renderiza una fuente vacía y guarda saltos de línea sin convertirla', () => {
    const Tool = loadTool().FaroMarkdownTool;
    const tool = new Tool();
    const wrapper = tool.render();

    assert.equal(wrapper.children[0].value, '');
    wrapper.children[0].value = '## Hola\n\n1. Uno\n2. Dos';
    assert.deepEqual({ ...tool.save(wrapper) }, { source: '## Hola\n\n1. Uno\n2. Dos' });
    assert.equal(tool.validate(tool.save(wrapper)), true);
});

test('restaura la fuente existente y respeta read-only', () => {
    const Tool = loadTool().FaroMarkdownTool;
    const source = 'Texto **existente**\n\n$D > 0$';
    const tool = new Tool({ data: { source }, readOnly: true });
    const input = tool.render().children[0];

    assert.equal(input.value, source);
    assert.equal(input.readOnly, true);
    assert.equal(input.attributes['aria-label'], 'Fuente Markdown');
});

test('conserva bloques largos, Unicode, fórmulas y código cercado', () => {
    const Tool = loadTool().FaroMarkdownTool;
    const source = `---\ntitle: "Importación futura"\n---\n\n${'áéíóú 日本語 🚀\n'.repeat(500)}\n$$\nA x = b\n$$\n\n\`\`\`php\necho 'FARO';\n\`\`\``;
    const tool = new Tool({ data: { source } });
    const wrapper = tool.render();

    assert.equal(tool.save(wrapper).source, source);
    assert.match(tool.save(wrapper).source, /^---\ntitle:/);
    assert.match(tool.save(wrapper).source, /A x = b/);
    assert.match(tool.save(wrapper).source, /```php/);
});

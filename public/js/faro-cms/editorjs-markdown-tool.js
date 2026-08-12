(function registerFaroMarkdownTool(global) {
    class MarkdownTool {
        static get toolbox() {
            return {
                title: 'Markdown',
                icon: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2 4.25h14v9.5H2v-9.5Zm2.2 7V7l2.1 2.35L8.4 7v4.25M11 9h2V7l2.3 2.5L13 12v-2h-2V9Z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            };
        }

        static get isReadOnlySupported() {
            return true;
        }

        static get sanitize() {
            // Editor.js debe conservar la fuente literalmente; el renderer PHP aplica la politica HTML.
            return { source: true };
        }

        constructor({ data = {}, readOnly = false, api = null, config = {}, block = null } = {}) {
            this.api = api;
            this.block = block;
            this.config = config;
            this.readOnly = Boolean(readOnly);
            this.data = {
                source: typeof data.source === 'string' ? data.source : '',
            };
            this.input = null;
            this.handleInput = this.resize.bind(this);
        }

        render() {
            const wrapper = document.createElement('div');
            wrapper.className = 'faro-markdown-tool';

            const input = document.createElement('textarea');
            input.className = 'faro-markdown-tool__input';
            input.value = this.data.source;
            input.placeholder = this.config.placeholder || 'Escribí o pegá Markdown…';
            input.readOnly = this.readOnly;
            input.setAttribute('aria-label', 'Fuente Markdown');
            input.setAttribute('spellcheck', this.config.spellcheck === false ? 'false' : 'true');
            input.addEventListener('input', this.handleInput);

            wrapper.appendChild(input);
            this.input = input;

            queueMicrotask(() => this.resize());

            return wrapper;
        }

        save(blockContent) {
            const input = this.input || blockContent?.querySelector?.('.faro-markdown-tool__input');

            return {
                source: typeof input?.value === 'string' ? input.value : this.data.source,
            };
        }

        validate(savedData) {
            return savedData !== null && typeof savedData?.source === 'string';
        }

        resize() {
            if (!this.input || this.readOnly) return;

            const textarea = this.input;
            const selectionStart = textarea.selectionStart;
            const selectionEnd = textarea.selectionEnd;

            textarea.style.height = 'auto';
            textarea.style.height = `${Math.max(textarea.scrollHeight, 224)}px`;

            if (selectionStart !== null && selectionEnd !== null) {
                textarea.setSelectionRange(selectionStart, selectionEnd);
            }
        }

        destroy() {
            this.input?.removeEventListener('input', this.handleInput);
        }
    }

    global.FaroMarkdownTool = MarkdownTool;
    global.filamentEditorJsTools = global.filamentEditorJsTools || {};
    global.filamentEditorJsTools.markdown = {
        class: MarkdownTool,
    };
})(typeof window !== 'undefined' ? window : globalThis);

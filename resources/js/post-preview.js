import 'katex/dist/katex.min.css';
import renderMathInElement from 'katex/contrib/auto-render';

function renderPreviewMath() {
    renderMathInElement(document.body, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
        ],
        throwOnError: false,
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderPreviewMath, { once: true });
} else {
    renderPreviewMath();
}

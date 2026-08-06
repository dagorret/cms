import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const mathjax = require('mathjax');

const expressions = {
    inline: String.raw`D > 0`,
    pmatrix: String.raw`\begin{pmatrix}a & b \\ c & d\end{pmatrix}`,
    bmatrix: String.raw`\begin{bmatrix}a & b \\ c & d\end{bmatrix}`,
    cases: String.raw`f(x)=\begin{cases}x & x>0 \\ 0 & x=0\end{cases}`,
    align: String.raw`\begin{align}a &= b \\ c &= d\end{align}`,
    aligned: String.raw`\begin{aligned}a &= b \\ c &= d\end{aligned}`,
    equation: String.raw`\begin{equation}a=b\end{equation}`,
    split: String.raw`\begin{equation}\begin{split}a &= b \\ &= c\end{split}\end{equation}`,
    gather: String.raw`\begin{gather}a=b \\ c=d\end{gather}`,
    matrix: String.raw`\begin{matrix}a & b \\ c & d\end{matrix}`,
    vmatrix: String.raw`\begin{vmatrix}a & b \\ c & d\end{vmatrix}`,
    Vmatrix: String.raw`\begin{Vmatrix}a & b \\ c & d\end{Vmatrix}`,
};

test('MathJax 3 renderiza los entornos LaTeX requeridos', async () => {
    const engine = await mathjax.init({
        loader: { load: ['input/tex', 'output/svg', '[tex]/ams'] },
        tex: { packages: { '[+]': ['ams'] } },
    });

    for (const [name, source] of Object.entries(expressions)) {
        const node = engine.tex2svg(source, { display: name !== 'inline' });
        const html = engine.startup.adaptor.outerHTML(node);

        assert.match(html, /<mjx-container class="MathJax" jax="SVG"/u, `${name} debe producir SVG`);
    }
});

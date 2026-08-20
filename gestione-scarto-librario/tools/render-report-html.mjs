import fs from 'node:fs';
import path from 'node:path';

const [, , inputPath, outputPath, language = 'it'] = process.argv;
if (!inputPath || !outputPath) {
    throw new Error('Usage: node tools/render-report-html.mjs input.md output.html [it|en]');
}

const markdown = fs.readFileSync(inputPath, 'utf8').replace(/\r\n?/g, '\n');
const lines = markdown.split('\n');
const subtitleIndex = lines.findIndex((line) => line.startsWith('## '));
const labels = language === 'en'
    ? { contents: 'Contents', internal: 'Internal working document', generated: 'Technical dossier' }
    : { contents: 'Indice', internal: 'Documento interno di lavoro', generated: 'Dossier tecnico' };

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function slugify(value, used) {
    const base = value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '') || 'section';
    let slug = base;
    let suffix = 2;
    while (used.has(slug)) slug = `${base}-${suffix++}`;
    used.add(slug);
    return slug;
}

function inline(value) {
    const code = [];
    let text = String(value).replace(/`([^`]+)`/g, (_, content) => {
        code.push(`<code>${escapeHtml(content)}</code>`);
        return `@@CODE${code.length - 1}@@`;
    });
    text = escapeHtml(text)
        .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, '<a href="$2">$1</a>')
        .replace(/&lt;(https?:\/\/[^&]+)&gt;/g, '<a href="$1">$1</a>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>');
    return text.replace(/@@CODE(\d+)@@/g, (_, index) => code[Number(index)]);
}

const usedIds = new Set();
const headingIds = new Map();
const toc = [];
for (let index = 0; index < lines.length; index++) {
    const match = lines[index].match(/^(#{2,3})\s+(.+)$/);
    if (!match || index === subtitleIndex) continue;
    const id = slugify(match[2], usedIds);
    headingIds.set(index, id);
    toc.push({ level: match[1].length, text: match[2], id });
}

const title = (lines.find((line) => line.startsWith('# ')) || '# Technical dossier').slice(2);
const subtitle = subtitleIndex >= 0 ? lines[subtitleIndex].slice(3) : '';
const noteIndex = lines.findIndex((line) => line.startsWith('> '));
const coverStart = subtitleIndex + 1;
const coverEnd = noteIndex >= 0 ? noteIndex : coverStart;
const coverMeta = lines.slice(coverStart, coverEnd).filter((line) => line.trim() !== '');

const body = [];
let paragraph = [];
let listType = null;
let inCode = false;
let codeLines = [];

function closeParagraph() {
    if (!paragraph.length) return;
    body.push(`<p>${inline(paragraph.join(' '))}</p>`);
    paragraph = [];
}

function closeList() {
    if (!listType) return;
    body.push(`</${listType}>`);
    listType = null;
}

for (let index = noteIndex >= 0 ? noteIndex : coverEnd; index < lines.length; index++) {
    const line = lines[index];
    if (inCode) {
        if (line.startsWith('```')) {
            body.push(`<pre><code>${escapeHtml(codeLines.join('\n'))}</code></pre>`);
            inCode = false;
            codeLines = [];
        } else {
            codeLines.push(line);
        }
        continue;
    }
    if (line.startsWith('```')) {
        closeParagraph();
        closeList();
        inCode = true;
        continue;
    }
    if (/^\|.*\|$/.test(line) && index + 1 < lines.length && /^\|(?:\s*:?-+:?\s*\|)+$/.test(lines[index + 1])) {
        closeParagraph();
        closeList();
        const headers = line.slice(1, -1).split('|').map((cell) => cell.trim());
        body.push('<table><thead><tr>' + headers.map((cell) => `<th>${inline(cell)}</th>`).join('') + '</tr></thead><tbody>');
        index += 2;
        while (index < lines.length && /^\|.*\|$/.test(lines[index])) {
            const cells = lines[index].slice(1, -1).split('|').map((cell) => cell.trim());
            body.push('<tr>' + cells.map((cell) => `<td>${inline(cell)}</td>`).join('') + '</tr>');
            index++;
        }
        body.push('</tbody></table>');
        index--;
        continue;
    }
    const heading = line.match(/^(#{2,4})\s+(.+)$/);
    if (heading) {
        closeParagraph();
        closeList();
        const level = heading[1].length;
        const id = headingIds.get(index) || slugify(heading[2], usedIds);
        body.push(`<h${level} id="${id}">${inline(heading[2])}</h${level}>`);
        continue;
    }
    if (line.startsWith('> ')) {
        closeParagraph();
        closeList();
        body.push(`<blockquote>${inline(line.slice(2))}</blockquote>`);
        continue;
    }
    const bullet = line.match(/^[-*]\s+(.+)$/);
    const number = line.match(/^\d+\.\s+(.+)$/);
    if (bullet || number) {
        closeParagraph();
        const type = number ? 'ol' : 'ul';
        if (listType !== type) {
            closeList();
            body.push(`<${type}>`);
            listType = type;
        }
        body.push(`<li>${inline((bullet || number)[1])}</li>`);
        continue;
    }
    if (/^---+$/.test(line.trim())) {
        closeParagraph();
        closeList();
        body.push('<hr>');
        continue;
    }
    if (line.trim() === '') {
        closeParagraph();
        closeList();
        continue;
    }
    paragraph.push(line.trim().replace(/\s{2}$/, ''));
}
closeParagraph();
closeList();
if (inCode) body.push(`<pre><code>${escapeHtml(codeLines.join('\n'))}</code></pre>`);

const tocHtml = toc
    .filter((item) => item.level === 2)
    .map((item) => `<li><a href="#${item.id}">${inline(item.text)}</a></li>`)
    .join('');
const metaHtml = coverMeta.map((line) => `<p>${inline(line.replace(/\s{2}$/, ''))}</p>`).join('');

const html = `<!doctype html>
<html lang="${language}">
<head>
<meta charset="utf-8">
<title>${escapeHtml(title)}</title>
<style>
@page { size: A4; margin: 18mm 17mm 19mm; }
* { box-sizing: border-box; }
html { color: #172033; font-family: "Segoe UI", "Aptos", sans-serif; font-size: 10.2pt; line-height: 1.48; }
body { margin: 0; }
.cover { min-height: 255mm; display: flex; flex-direction: column; justify-content: center; border-top: 12px solid #173b67; padding: 22mm 12mm; break-after: page; }
.cover::before { content: "BIBLIOTECA STATALE STELIO CRISE"; color: #9a6b14; font-size: 9pt; font-weight: 700; letter-spacing: .14em; margin-bottom: 20mm; }
.cover h1 { color: #173b67; font-family: Georgia, serif; font-size: 30pt; line-height: 1.08; margin: 0 0 7mm; max-width: 160mm; }
.cover h2 { color: #9a6b14; font-size: 17pt; font-weight: 500; margin: 0 0 18mm; }
.cover .meta { border-left: 3px solid #d8b15b; padding-left: 7mm; color: #37445a; }
.cover .meta p { margin: 1.7mm 0; }
.document-label { margin-top: auto; color: #69758a; font-size: 8.5pt; letter-spacing: .08em; text-transform: uppercase; }
.toc { break-after: page; padding-top: 8mm; }
.toc h2 { border: 0; font-size: 23pt; margin-bottom: 9mm; }
.toc ol { columns: 2; column-gap: 14mm; padding-left: 0; list-style: none; }
.toc li { break-inside: avoid; margin: 0 0 3mm; }
.toc a { color: #173b67; text-decoration: none; }
.page-header { position: fixed; top: -13mm; left: 0; right: 0; color: #657086; border-bottom: .3mm solid #d8dee8; padding-bottom: 2mm; font-size: 7.8pt; }
.page-footer { position: fixed; bottom: -13mm; left: 0; right: 0; color: #657086; border-top: .3mm solid #d8dee8; padding-top: 2mm; font-size: 7.8pt; text-align: right; }
h2, h3, h4 { color: #173b67; break-after: avoid; page-break-after: avoid; }
h2 { font-family: Georgia, serif; font-size: 18pt; line-height: 1.2; border-bottom: 1.2px solid #d8b15b; padding-bottom: 2.5mm; margin: 10mm 0 5mm; }
h3 { font-size: 12.5pt; margin: 7mm 0 2.5mm; }
h4 { font-size: 10.5pt; margin: 5mm 0 2mm; }
p { margin: 0 0 3.2mm; orphans: 3; widows: 3; }
ul, ol { margin: 2mm 0 4mm; padding-left: 7mm; }
li { margin: 0 0 1.4mm; }
a { color: #145b91; }
strong { color: #25334a; }
code { background: #eef2f7; border-radius: 2px; padding: .2mm 1mm; font-family: Consolas, monospace; font-size: 8.8pt; }
pre { background: #172033; color: #f5f7fb; padding: 5mm; border-radius: 3px; overflow-wrap: anywhere; white-space: pre-wrap; break-inside: avoid; }
pre code { background: transparent; color: inherit; padding: 0; }
blockquote { background: #f3f6fa; border-left: 3px solid #9a6b14; margin: 5mm 0 7mm; padding: 4mm 5mm; color: #33415a; }
table { width: 100%; border-collapse: collapse; margin: 4mm 0 7mm; font-size: 8.2pt; break-inside: auto; }
thead { display: table-header-group; }
tr { break-inside: avoid; }
th { background: #173b67; color: white; text-align: left; padding: 2.5mm; vertical-align: top; }
td { border: .3mm solid #d7dde7; padding: 2.2mm; vertical-align: top; }
tbody tr:nth-child(even) td { background: #f5f7fa; }
hr { border: 0; border-top: .3mm solid #d8dee8; margin: 8mm 0; }
@media print { a { text-decoration: none; } }
</style>
</head>
<body>
<section class="cover">
    <h1>${inline(title)}</h1>
    <h2>${inline(subtitle)}</h2>
    <div class="meta">${metaHtml}</div>
    <div class="document-label">${escapeHtml(labels.generated)} · ${escapeHtml(labels.internal)}</div>
</section>
<section class="toc"><h2>${escapeHtml(labels.contents)}</h2><ol>${tocHtml}</ol></section>
<div class="page-header">${escapeHtml(subtitle)} · v9.4.4 RC</div>
<div class="page-footer">${escapeHtml(labels.internal)} · 20/08/2026</div>
<main>${body.join('\n')}</main>
</body>
</html>`;

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, html);
console.log(outputPath);

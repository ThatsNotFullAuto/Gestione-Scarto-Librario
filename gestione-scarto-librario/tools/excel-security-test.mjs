import crypto from 'node:crypto';
import fs from 'node:fs';
import * as XLSX from 'xlsx';

const vendorPath = new URL('../vendor/xlsx-0.20.3.tgz', import.meta.url);
const expectedHash = '8dc73fc3b00203e72d176e85b50938627c7b086e607c682e8d3c22c02bb99fe8';
const actualHash = crypto.createHash('sha256').update(fs.readFileSync(vendorPath)).digest('hex');
if (actualHash !== expectedHash) throw new Error('Il pacchetto SheetJS locale non corrisponde alla provenienza registrata.');

const rows = [['ID', 'Titolo', 'Inventario'], ['1', '=HYPERLINK("https://example.org")', '100']];
for (let index = 2; index <= 50002; index += 1) rows.push([String(index), `Titolo ${index}`, String(index + 99)]);
const worksheet = XLSX.utils.aoa_to_sheet(rows);
const workbook = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(workbook, worksheet, 'Catalogo');
const serialized = XLSX.write(workbook, { type: 'buffer', bookType: 'xlsx' });
const parsed = XLSX.read(serialized, { type: 'buffer', sheetRows: 50001 });
const parsedRows = XLSX.utils.sheet_to_json(parsed.Sheets.Catalogo, { defval: '', raw: false });
if (parsedRows.length !== 50000) throw new Error(`Il limite Excel non è applicato: ${parsedRows.length} righe dati.`);
if (parsedRows[0].Titolo !== '=HYPERLINK("https://example.org")') throw new Error('Il valore simile a formula non è rimasto testo in ingresso.');

let malformedRejected = false;
try {
  const malformed = XLSX.read(Buffer.from('PK\x03\x04file-xlsx-non-valido'), { type: 'buffer' });
  malformedRejected = malformed.SheetNames.length === 0;
} catch {
  malformedRejected = true;
}
if (!malformedRejected) throw new Error('Un file Excel malformato è stato accettato come foglio valido.');

console.log('OK provenienza, limite righe e input Excel ostili');

import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { inflateRawSync } from 'node:zlib';
import { fileURLToPath } from 'node:url';

const pluginDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const packageData = JSON.parse(fs.readFileSync(path.join(pluginDir, 'package.json'), 'utf8'));
const zipName = `gestione-scarto-librario-${packageData.version}.zip`;
const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'scarto-release-'));

try {
  const archives = [];
  for (const run of ['first', 'second']) {
    const outputDir = path.join(tempRoot, run);
    execFileSync(process.execPath, [path.join(pluginDir, 'tools/build-release.mjs')], {
      cwd: pluginDir,
      env: { ...process.env, SCARTO_RELEASE_OUTPUT_DIR: outputDir },
      stdio: 'pipe',
    });
    archives.push(fs.readFileSync(path.join(outputDir, zipName)));
  }

  assert(archives[0].equals(archives[1]), 'Due build consecutive non producono lo stesso ZIP.');
  const entries = readZipEntries(archives[0]);
  const root = 'gestione-scarto-librario/';
  const manifestName = `${root}RELEASE-MANIFEST.json`;
  assert(entries.has(manifestName), 'RELEASE-MANIFEST.json assente.');
  assert([...entries.keys()].every(name => name.startsWith(root) && !name.slice(root.length).includes('..')), 'Radice ZIP o percorso non valido.');

  const manifest = JSON.parse(entries.get(manifestName).toString('utf8'));
  assert(manifest.version === packageData.version, 'Versione del manifesto non coerente.');
  const expectedNames = new Set([manifestName, ...Object.keys(manifest.files).map(name => `${root}${name}`)]);
  assert(entries.size === expectedNames.size, 'Lo ZIP contiene file non dichiarati nel manifesto.');

  for (const [relativeName, expectedHash] of Object.entries(manifest.files)) {
    const name = `${root}${relativeName}`;
    assert(entries.has(name), `File dichiarato ma assente: ${relativeName}`);
    const actualHash = crypto.createHash('sha256').update(entries.get(name)).digest('hex');
    assert(actualHash === expectedHash, `Checksum interno non valido: ${relativeName}`);
  }

  const forbidden = /(?:^|\/)(?:node_modules|src|tests|tools|security-tests|docs)(?:\/|$)|\.(?:xlsx?|sql|log|pem|key|bak)$/i;
  assert([...entries.keys()].every(name => !forbidden.test(name)), 'Lo ZIP contiene sorgenti di sviluppo o artefatti riservati.');

  const hash = crypto.createHash('sha256').update(archives[0]).digest('hex');
  console.log(`OK release offline: ${entries.size} file, ZIP deterministico, SHA-256 ${hash}`);
} finally {
  fs.rmSync(tempRoot, { recursive: true, force: true });
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function readZipEntries(buffer) {
  const endSignature = 0x06054b50;
  let endOffset = -1;
  for (let offset = buffer.length - 22; offset >= Math.max(0, buffer.length - 65557); offset--) {
    if (buffer.readUInt32LE(offset) === endSignature) {
      endOffset = offset;
      break;
    }
  }
  assert(endOffset >= 0, 'Directory centrale ZIP non trovata.');

  const totalEntries = buffer.readUInt16LE(endOffset + 10);
  let centralOffset = buffer.readUInt32LE(endOffset + 16);
  const entries = new Map();

  for (let index = 0; index < totalEntries; index++) {
    assert(buffer.readUInt32LE(centralOffset) === 0x02014b50, 'Voce ZIP centrale non valida.');
    const method = buffer.readUInt16LE(centralOffset + 10);
    const compressedSize = buffer.readUInt32LE(centralOffset + 20);
    const uncompressedSize = buffer.readUInt32LE(centralOffset + 24);
    const nameLength = buffer.readUInt16LE(centralOffset + 28);
    const extraLength = buffer.readUInt16LE(centralOffset + 30);
    const commentLength = buffer.readUInt16LE(centralOffset + 32);
    const localOffset = buffer.readUInt32LE(centralOffset + 42);
    const name = buffer.subarray(centralOffset + 46, centralOffset + 46 + nameLength).toString('utf8');

    assert(buffer.readUInt32LE(localOffset) === 0x04034b50, `Header locale non valido: ${name}`);
    const localNameLength = buffer.readUInt16LE(localOffset + 26);
    const localExtraLength = buffer.readUInt16LE(localOffset + 28);
    const dataOffset = localOffset + 30 + localNameLength + localExtraLength;
    const compressed = buffer.subarray(dataOffset, dataOffset + compressedSize);
    const content = method === 0 ? compressed : method === 8 ? inflateRawSync(compressed) : null;
    assert(content !== null, `Metodo di compressione non supportato: ${name}`);
    assert(content.length === uncompressedSize, `Dimensione non valida: ${name}`);
    assert(!entries.has(name), `Voce ZIP duplicata: ${name}`);
    entries.set(name, content);

    centralOffset += 46 + nameLength + extraLength + commentLength;
  }
  return entries;
}

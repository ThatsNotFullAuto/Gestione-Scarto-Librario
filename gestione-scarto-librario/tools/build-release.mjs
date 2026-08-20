import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ZipArchive } from 'archiver';

const pluginDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outputDir = process.env.SCARTO_RELEASE_OUTPUT_DIR
  ? path.resolve(process.env.SCARTO_RELEASE_OUTPUT_DIR)
  : path.resolve(pluginDir, '..');
const packageData = JSON.parse(fs.readFileSync(path.join(pluginDir, 'package.json'), 'utf8'));
const zipName = `gestione-scarto-librario-${packageData.version}.zip`;
const zipPath = path.join(outputDir, zipName);
const rootName = 'gestione-scarto-librario';
const releaseDate = new Date(packageData.releaseDate);

if (!Number.isFinite(releaseDate.getTime())) {
  throw new Error('package.json deve contenere una releaseDate ISO valida.');
}
fs.mkdirSync(outputDir, { recursive: true });

const files = [
  'gestione-scarto-librario.php',
  'uninstall.php',
  ...collectFiles('includes'),
  ...collectFiles('templates'),
  ...collectFiles('dist'),
  ...collectFiles('assets/fonts')
].sort();

const forbiddenEntryPatterns = [
  /(?:^|\/)\.env(?:\.|$)/i,
  /(?:^|\/)wp-config\.php$/i,
  /\.(?:xlsx?|sql|log|pem|key|pfx|p12|bak)$/i,
  /(?:^|\/)(?:backup|dump|password|credentials?|secrets?)[^/]*\.(?:json|txt|csv)$/i,
];
const textEntryPattern = /\.(?:php|js|mjs|css|html|json|txt)$/i;
const forbiddenContentPatterns = [
  /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  /define\s*\(\s*['"]DB_PASSWORD['"]\s*,\s*['"][^'"]+['"]\s*\)/i,
  /(?:client_secret|smtp_password|api_key)\s*[:=]\s*['"][^'"]{8,}['"]/i,
  /(?:[A-Z]:\\Users\\|\/mnt\/[a-z]\/Users\/)/,
];

for (const file of files) {
  if (file.startsWith('/') || file.includes('..') || forbiddenEntryPatterns.some(pattern => pattern.test(file))) {
    throw new Error(`File non distribuibile rilevato: ${file}`);
  }
  if (textEntryPattern.test(file)) {
    const content = fs.readFileSync(path.join(pluginDir, file), 'utf8');
    if (forbiddenContentPatterns.some(pattern => pattern.test(content))) {
      throw new Error(`Possibile segreto o percorso locale nel file distribuibile: ${file}`);
    }
  }
}

const mainPhp = fs.readFileSync(path.join(pluginDir, 'gestione-scarto-librario.php'), 'utf8');
const uninstallPhp = fs.readFileSync(path.join(pluginDir, 'uninstall.php'), 'utf8');
for (const [pattern, description] of [
  [new RegExp(`Version:\\s*${packageData.version.replaceAll('.', '\\.')}`), 'header del plugin'],
  [new RegExp(`SCARTO_VERSION',\\s*'${packageData.version.replaceAll('.', '\\.')}'`), 'costante del plugin'],
]) {
  if (!pattern.test(mainPhp)) throw new Error(`Versione incoerente: ${description}.`);
}
if (!new RegExp(`Version:\\s*${packageData.version.replaceAll('.', '\\.')}`).test(uninstallPhp)) {
  throw new Error('Versione incoerente: uninstall.php.');
}

function collectFiles(relativeDirectory) {
  const directory = path.join(pluginDir, relativeDirectory);
  if (!fs.existsSync(directory)) return [];

  const result = [];
  const walk = current => {
    for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
      const absolute = path.join(current, entry.name);
      if (entry.isDirectory()) walk(absolute);
      if (entry.isFile()) result.push(path.relative(pluginDir, absolute).replaceAll('\\', '/'));
    }
  };
  walk(directory);
  return result;
}

const manifest = {
  plugin: 'Gestione Scarto Librario',
  version: packageData.version,
  generatedAt: releaseDate.toISOString(),
  files: Object.fromEntries(files.map(file => [
    file,
    crypto.createHash('sha256').update(fs.readFileSync(path.join(pluginDir, file))).digest('hex')
  ]))
};

await new Promise((resolve, reject) => {
  const output = fs.createWriteStream(zipPath);
  const archive = new ZipArchive({ zlib: { level: 9 }, forceLocalTime: false });
  output.on('close', resolve);
  output.on('error', reject);
  archive.on('error', reject);
  archive.pipe(output);

  for (const file of files) {
    // Buffers avoid filesystem-stat metadata and stream timing affecting the archive bytes.
    archive.append(fs.readFileSync(path.join(pluginDir, file)), {
      name: `${rootName}/${file}`,
      date: releaseDate,
      mode: 0o644,
    });
  }
  archive.append(`${JSON.stringify(manifest, null, 2)}\n`, {
    name: `${rootName}/RELEASE-MANIFEST.json`,
    date: releaseDate,
    mode: 0o644,
  });
  archive.finalize();
});

const zipHash = crypto.createHash('sha256').update(fs.readFileSync(zipPath)).digest('hex');
fs.writeFileSync(`${zipPath}.sha256`, `${zipHash}  ${zipName}\n`);

console.log(`Creato ${zipPath}`);
console.log(`SHA-256 ${zipHash}`);

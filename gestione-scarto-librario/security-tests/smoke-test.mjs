import fs from 'node:fs';
import path from 'node:path';

const options = parseArguments(process.argv.slice(2));
const baseUrl = normalizeBaseUrl(options.baseUrl || process.env.SCARTO_BASE_URL);
const pageUrl = options.pageUrl || process.env.SCARTO_PAGE_URL || '';
const outputDir = path.resolve(options.output || process.env.SCARTO_REPORT_DIR || 'security-tests/reports');
const timeoutMs = Number(options.timeout || process.env.SCARTO_TIMEOUT_MS || 15000);
const allowHttpLocal = String(options.allowHttpLocal || '').toLowerCase() === 'true';

if (!baseUrl) {
  console.error('Uso: npm run test:security:smoke -- --base-url https://host/wp-json/scarto/v1 [--page-url https://host/pagina/]');
  process.exit(2);
}

const results = [];
const forbiddenKeys = new Set([
  'user_email',
  'user_indirizzo',
  'ip_address',
  'user_agent',
  'note',
  'motivazioni',
  'collocazione',
  'scatola',
  'password',
  'email_to'
]);

function parseArguments(args) {
  const parsed = {};
  for (let index = 0; index < args.length; index++) {
    const argument = args[index];
    if (!argument.startsWith('--')) continue;
    const [rawKey, inlineValue] = argument.slice(2).split('=', 2);
    const key = rawKey.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
    parsed[key] = inlineValue ?? args[++index];
  }
  return parsed;
}

function normalizeBaseUrl(value) {
  if (!value) return '';
  return value.replace(/\/+$/, '');
}

function addResult(name, status, detail) {
  results.push({ name, status, detail });
  const marker = status === 'pass' ? 'PASS' : status === 'warn' ? 'WARN' : 'FAIL';
  console.log(`${marker} ${name}: ${detail}`);
}

async function request(url, init = {}) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(url, {
      redirect: 'manual',
      ...init,
      headers: {
        'User-Agent': 'ScartoLibrario-Safe-Smoke-Test/1.0',
        ...(init.headers || {})
      },
      signal: controller.signal
    });
  } finally {
    clearTimeout(timer);
  }
}

async function readJson(response) {
  const text = await response.text();
  try {
    return { text, json: JSON.parse(text) };
  } catch {
    return { text, json: null };
  }
}

function findForbiddenKeys(value, location = '$', found = [], keys = forbiddenKeys) {
  if (Array.isArray(value)) {
    value.forEach((item, index) => findForbiddenKeys(item, `${location}[${index}]`, found, keys));
  } else if (value && typeof value === 'object') {
    for (const [key, child] of Object.entries(value)) {
      if (keys.has(key.toLowerCase())) found.push(`${location}.${key}`);
      findForbiddenKeys(child, `${location}.${key}`, found, keys);
    }
  }
  return found;
}

async function testPublicJson(name, route, assertions = () => [], keys = forbiddenKeys) {
  try {
    const response = await request(`${baseUrl}${route}`);
    const body = await readJson(response);
    if (response.status !== 200 || body.json === null) {
      addResult(name, 'fail', `HTTP ${response.status}; risposta JSON valida richiesta.`);
      return;
    }
    const forbidden = findForbiddenKeys(body.json, '$', [], keys);
    const assertionErrors = assertions(body.json);
    const errors = [...forbidden.map(key => `campo riservato ${key}`), ...assertionErrors];
    addResult(name, errors.length ? 'fail' : 'pass', errors.length ? errors.join('; ') : `HTTP 200, nessun campo riservato.`);
  } catch (error) {
    addResult(name, 'fail', error.message);
  }
}

async function run() {
  try {
    const parsed = new URL(baseUrl);
    const localHttpAllowed = allowHttpLocal
      && parsed.protocol === 'http:'
      && ['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname);
    addResult(
      'HTTPS',
      parsed.protocol === 'https:' || localHttpAllowed ? 'pass' : 'fail',
      parsed.protocol === 'https:' ? 'Endpoint HTTPS.' : (localHttpAllowed ? 'HTTP locale consentito solo per self-test.' : 'Endpoint non HTTPS.')
    );
  } catch {
    addResult('URL API', 'fail', 'URL non valido.');
  }

  await testPublicJson('Bootstrap pubblico', '/init', data => [
    ...(Array.isArray(data.books) ? [] : ['books non e un array']),
    ...(Array.isArray(data.books) && data.books.every(book => ['available', 'reserved', 'delivered'].includes(book?._availability)) ? [] : ['stato disponibilita assente o non valido']),
    ...(Array.isArray(data.books) && data.books.every(book => book?._availability !== 'reserved' || Number(book?.reservedUntil) > 0) ? [] : ['scadenza prenotazione assente']),
    ...(typeof data.apiVersion === 'string' ? [] : ['apiVersion assente'])
  ]);
  await testPublicJson('Catalogo pubblico', '/catalog?page=1&per_page=10', data => [
    ...(Array.isArray(data.books) ? [] : ['books non e un array']),
    ...(Array.isArray(data.books) && data.books.every(book => ['available', 'reserved', 'delivered'].includes(book?._availability)) ? [] : ['stato disponibilita assente o non valido'])
  ]);
  await testPublicJson('Disponibilita catalogo', '/catalog/availability', data => [
    ...(Array.isArray(data.states) ? [] : ['states non e un array']),
    ...(Array.isArray(data.states) && data.states.every(state =>
      typeof state?.id === 'string'
      && ['reserved', 'delivered'].includes(state?._availability)
      && (state._availability !== 'reserved' || Number(state?.reservedUntil) > 0)
    ) ? [] : ['snapshot disponibilita non valido']),
    ...(typeof data.serverTime === 'number' ? [] : ['serverTime assente'])
  ]);
  await testPublicJson('Ricerca catalogo pubblica', '/books/search?q=te&limit=5', data =>
    Array.isArray(data.results) ? [] : ['results non e un array']
  );
  await testPublicJson('Impostazioni pubbliche', '/settings');
  await testPublicJson('Informativa GDPR', '/gdpr/privacy-info', () => [], new Set(['password', 'email_to', 'user_email', 'user_indirizzo']));

  try {
    const response = await request(`${baseUrl}/init`, {
      headers: { Origin: 'https://attacker.invalid' }
    });
    const allowOrigin = response.headers.get('access-control-allow-origin') || '';
    addResult(
      'CORS namespace plugin',
      response.status === 200 && allowOrigin === '' ? 'pass' : 'fail',
      `HTTP ${response.status}; Access-Control-Allow-Origin: ${allowOrigin || 'assente'}.`
    );
  } catch (error) {
    addResult('CORS namespace plugin', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/orders`, {
      method: 'POST',
      headers: { Origin: 'https://attacker.invalid', 'Content-Type': 'application/json' },
      body: '{}'
    });
    addResult(
      'Origine esterna su endpoint privato',
      response.status === 403 ? 'pass' : 'fail',
      `HTTP ${response.status}; atteso 403.`
    );
  } catch (error) {
    addResult('Origine esterna su endpoint privato', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/orders`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: '{}'
    });
    const cacheControl = response.headers.get('cache-control') || '';
    const errors = [];
    if (![401, 403].includes(response.status)) errors.push(`HTTP ${response.status}`);
    if (!/no-store/i.test(cacheControl) || !/private/i.test(cacheControl)) errors.push('Cache-Control privato incompleto');
    addResult('API privata anonima e cache', errors.length ? 'fail' : 'pass', errors.length ? errors.join('; ') : `HTTP ${response.status}; ${cacheControl}`);
  } catch (error) {
    addResult('API privata anonima e cache', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/admin/catalog?page=1&per_page=10`);
    const cacheControl = response.headers.get('cache-control') || '';
    const errors = [];
    if (![401, 403].includes(response.status)) errors.push(`HTTP ${response.status}`);
    if (!/no-store/i.test(cacheControl) || !/private/i.test(cacheControl)) errors.push('Cache-Control privato incompleto');
    addResult('Catalogo staff protetto', errors.length ? 'fail' : 'pass', errors.length ? errors.join('; ') : `HTTP ${response.status}; ${cacheControl}`);
  } catch (error) {
    addResult('Catalogo staff protetto', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/orders`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: '{}'
    });
    addResult(
      'Endpoint ordini anonimo',
      [401, 403].includes(response.status) ? 'pass' : 'fail',
      `HTTP ${response.status}; atteso 401 o 403.`
    );
  } catch (error) {
    addResult('Endpoint ordini anonimo', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/reserve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: '{"json":'
    });
    addResult('JSON malformato', response.status === 400 ? 'pass' : 'fail', `HTTP ${response.status}; atteso 400.`);
  } catch (error) {
    addResult('JSON malformato', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/reserve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booksDetails: [{ id: 'smoke-test-only' }],
        userData: {},
        consent: { accepted: true }
      })
    });
    addResult('Schema dati prenotazione', response.status === 400 ? 'pass' : 'fail', `HTTP ${response.status}; atteso 400.`);
  } catch (error) {
    addResult('Schema dati prenotazione', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/reserve/confirm`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ requestId: 'invalid', verificationCode: '12' })
    });
    addResult('Schema conferma email', response.status === 400 ? 'pass' : 'fail', `HTTP ${response.status}; atteso 400.`);
  } catch (error) {
    addResult('Schema conferma email', 'fail', error.message);
  }

  try {
    const response = await request(`${baseUrl}/reserve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        unexpectedSmokeField: true
      })
    });
    addResult('Campi JSON inattesi', response.status === 400 ? 'pass' : 'fail', `HTTP ${response.status}; atteso 400.`);
  } catch (error) {
    addResult('Campi JSON inattesi', 'fail', error.message);
  }

  if (pageUrl) {
    try {
      const response = await request(pageUrl);
      const html = await response.text();
      const csp = response.headers.get('content-security-policy') || '';
      const externalRuntime = /(?:fonts\.googleapis|fonts\.gstatic|cdn\.sheetjs|cdnjs\.)/i.test(html);
      const errors = [];
      if (response.status !== 200) errors.push(`HTTP ${response.status}`);
      if (!/default-src\s+'self'/i.test(csp)) errors.push('CSP attesa non rilevata');
      if (externalRuntime) errors.push('riferimento runtime esterno rilevato');
      addResult('Pagina plugin e CSP', errors.length ? 'fail' : 'pass', errors.length ? errors.join('; ') : 'CSP presente e nessun CDN noto.');
    } catch (error) {
      addResult('Pagina plugin e CSP', 'fail', error.message);
    }
  } else {
    addResult('Pagina plugin e CSP', 'warn', 'SCARTO_PAGE_URL non configurato; controllo saltato.');
  }

  const summary = {
    generatedAt: new Date().toISOString(),
    baseUrl,
    pageUrl: pageUrl || null,
    mode: 'safe-non-destructive',
    counts: {
      pass: results.filter(result => result.status === 'pass').length,
      warn: results.filter(result => result.status === 'warn').length,
      fail: results.filter(result => result.status === 'fail').length
    },
    results
  };

  try {
    fs.mkdirSync(outputDir, { recursive: true });
    fs.writeFileSync(path.join(outputDir, 'smoke-report.json'), `${JSON.stringify(summary, null, 2)}\n`);
    fs.writeFileSync(path.join(outputDir, 'smoke-report.md'), createMarkdown(summary));
    console.log(`Report: ${path.join(outputDir, 'smoke-report.md')}`);
  } catch (error) {
    console.error(`Impossibile scrivere il report in ${outputDir}: ${error.message}`);
    process.exitCode = 2;
    return;
  }
  if (summary.counts.fail) process.exitCode = 1;
}

function createMarkdown(summary) {
  const rows = summary.results
    .map(result => `| ${result.status.toUpperCase()} | ${escapeCell(result.name)} | ${escapeCell(result.detail)} |`)
    .join('\n');
  return `# Scarto Librario - Smoke Test\n\n` +
    `- Data UTC: ${summary.generatedAt}\n` +
    `- API: ${summary.baseUrl}\n` +
    `- Pagina: ${summary.pageUrl || 'non configurata'}\n` +
    `- Modalita: non distruttiva\n` +
    `- Esito: ${summary.counts.pass} PASS, ${summary.counts.warn} WARN, ${summary.counts.fail} FAIL\n\n` +
    `| Esito | Controllo | Dettaglio |\n|---|---|---|\n${rows}\n`;
}

function escapeCell(value) {
  return String(value).replaceAll('|', '\\|').replaceAll('\n', ' ');
}

await run();

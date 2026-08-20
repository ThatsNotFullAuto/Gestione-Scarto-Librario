const options = parseArguments(process.argv.slice(2));
const baseUrl = normalizeBaseUrl(options.baseUrl || process.env.SCARTO_BASE_URL);
const confirmationsRaw = options.confirmations || process.env.SCARTO_CONFIRMATIONS;
const expectedAccepted = Number(options.expectedAccepted || process.env.SCARTO_EXPECTED_ACCEPTED || 2);
const timeoutMs = Number(options.timeout || process.env.SCARTO_TIMEOUT_MS || 20000);

if (!baseUrl || !confirmationsRaw) {
  console.error(
    "Uso: SCARTO_BASE_URL=... SCARTO_CONFIRMATIONS='[{\"requestId\":\"...\",\"verificationCode\":\"...\"}]' " +
    'SCARTO_EXPECTED_ACCEPTED=2 npm run test:security:active-limit'
  );
  process.exit(2);
}

let confirmations;
try {
  confirmations = JSON.parse(confirmationsRaw);
} catch {
  fail('SCARTO_CONFIRMATIONS non contiene JSON valido.');
}
if (!Array.isArray(confirmations) || confirmations.length <= expectedAccepted) {
  fail('Servono più conferme del numero che deve essere accettato.');
}
for (const confirmation of confirmations) {
  if (!/^[a-f0-9]{32}$/.test(confirmation?.requestId || '')
      || !/^[0-9]{6}$/.test(confirmation?.verificationCode || '')) {
    fail('Ogni conferma deve contenere requestId e verificationCode validi.');
  }
}

const origin = new URL(baseUrl).origin;

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
  return value ? value.replace(/\/+$/, '') : '';
}

function fail(message, details) {
  console.error(`FAIL ${message}`);
  if (details) console.error(JSON.stringify(details, null, 2));
  process.exit(1);
}

async function confirm(confirmation) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(`${baseUrl}/reserve/confirm`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Origin: origin,
        'User-Agent': 'ScartoLibrario-Active-Limit-Test/1.0'
      },
      body: JSON.stringify(confirmation),
      signal: controller.signal
    });
    const text = await response.text();
    let body = null;
    try {
      body = JSON.parse(text);
    } catch {
      // Keep the HTTP status for diagnostics when a proxy replaces the JSON body.
    }
    return { status: response.status, body };
  } finally {
    clearTimeout(timer);
  }
}

const results = await Promise.all(confirmations.map(confirm));
const accepted = results.filter(result => result.status === 200);
const blocked = results.filter(result => result.status === 429 && result.body?.code === 'active_reservation_limit');

if (accepted.length !== expectedAccepted || blocked.length !== confirmations.length - expectedAccepted) {
  fail('il limite delle prenotazioni attive non ha prodotto gli esiti attesi.', results);
}
if (results.some(result => ![200, 429].includes(result.status))) {
  fail('una conferma è fallita per un motivo diverso dal limite attivo.', results);
}

console.log(`PASS limite attivo: ${accepted.length} prenotazioni accettate e ${blocked.length} bloccate con HTTP 429.`);

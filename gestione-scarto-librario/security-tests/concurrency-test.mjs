const options = parseArguments(process.argv.slice(2));
const baseUrl = normalizeBaseUrl(options.baseUrl || process.env.SCARTO_BASE_URL);
const requestA = options.requestA || process.env.SCARTO_REQUEST_A;
const codeA = options.codeA || process.env.SCARTO_CODE_A;
const requestB = options.requestB || process.env.SCARTO_REQUEST_B;
const codeB = options.codeB || process.env.SCARTO_CODE_B;
const timeoutMs = Number(options.timeout || process.env.SCARTO_TIMEOUT_MS || 20000);

if (!baseUrl || !requestA || !codeA || !requestB || !codeB) {
  console.error(
    'Uso: SCARTO_BASE_URL=... SCARTO_REQUEST_A=... SCARTO_CODE_A=... ' +
    'SCARTO_REQUEST_B=... SCARTO_CODE_B=... npm run test:security:concurrency'
  );
  process.exit(2);
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

async function confirm(requestId, verificationCode) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(`${baseUrl}/reserve/confirm`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Origin: origin,
        'User-Agent': 'ScartoLibrario-Concurrency-Test/1.0'
      },
      body: JSON.stringify({ requestId, verificationCode }),
      signal: controller.signal
    });
    const text = await response.text();
    let body = null;
    try {
      body = JSON.parse(text);
    } catch {
      // The status remains useful even if an upstream proxy replaces the body.
    }
    return { status: response.status, body };
  } finally {
    clearTimeout(timer);
  }
}

function fail(message, details) {
  console.error(`FAIL ${message}`);
  if (details) console.error(JSON.stringify(details, null, 2));
  process.exit(1);
}

function publicResult(result) {
  return {
    label: result.label,
    status: result.status,
    body: result.body
  };
}

const [resultA, resultB] = await Promise.all([
  confirm(requestA, codeA),
  confirm(requestB, codeB)
]);

const results = [
  { label: 'A', requestId: requestA, verificationCode: codeA, ...resultA },
  { label: 'B', requestId: requestB, verificationCode: codeB, ...resultB }
];
const winner = results.find(result => result.status === 200);
const loser = results.find(result => result.status === 409);

if (!winner || !loser || results.some(result => ![200, 409].includes(result.status))) {
  fail('attesi esattamente un HTTP 200 e un HTTP 409.', results.map(publicResult));
}
if (!Array.isArray(loser.body?.data?.unavailableBooks) || loser.body.data.unavailableBooks.length === 0) {
  fail('il conflitto non elenca i libri divenuti indisponibili.', publicResult(loser));
}

const replay = await confirm(winner.requestId, winner.verificationCode);
if (replay.status !== 200 || replay.body?.code !== winner.body?.code || replay.body?.idempotentReplay !== true) {
  fail('il replay della conferma vincente non è idempotente.', { winner: publicResult(winner), replay });
}

console.log(`PASS concorrenza: richiesta ${winner.label} accettata, richiesta ${loser.label} respinta senza ordine parziale.`);
console.log(`PASS idempotenza: replay restituisce lo stesso codice ${winner.body.code}.`);

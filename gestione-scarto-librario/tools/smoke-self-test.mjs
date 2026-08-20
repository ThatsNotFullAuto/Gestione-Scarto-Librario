import { spawn } from 'node:child_process';
import http from 'node:http';
import path from 'node:path';

const server = http.createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost');
  response.setHeader('Content-Type', url.pathname === '/page/' ? 'text/html' : 'application/json');

  if (url.pathname === '/page/') {
    response.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'");
    response.end('<!doctype html><html><body>Scarto test</body></html>');
    return;
  }
  if (url.pathname.endsWith('/orders')) {
    if (request.headers.origin === 'https://attacker.invalid') {
      response.statusCode = 403;
      response.end('{}');
      return;
    }
    response.setHeader('Cache-Control', 'no-store, private');
    response.statusCode = 403;
    response.end('{}');
    return;
  }
  if (url.pathname.endsWith('/admin/catalog')) {
    response.setHeader('Cache-Control', 'no-store, private');
    response.statusCode = 403;
    response.end('{}');
    return;
  }
  if (url.pathname.endsWith('/reserve') || url.pathname.endsWith('/reserve/confirm')) {
    response.statusCode = 400;
    response.end('{}');
    return;
  }
  if (url.pathname.endsWith('/init')) {
    response.end(JSON.stringify({ books: [{ id: 'available-book', _availability: 'available' }], apiVersion: '9.4.4' }));
    return;
  }
  if (url.pathname.endsWith('/catalog/availability')) {
    response.end(JSON.stringify({
      states: [{ id: 'reserved-book', _availability: 'reserved', reservedUntil: Date.now() + 60000 }],
      serverTime: Date.now()
    }));
    return;
  }
  if (url.pathname.endsWith('/catalog')) {
    response.end(JSON.stringify({ books: [{ id: 'reserved-book', _availability: 'reserved', reservedUntil: Date.now() + 60000 }], pagination: {} }));
    return;
  }
  if (url.pathname.endsWith('/books/search')) {
    response.end(JSON.stringify({ results: [] }));
    return;
  }
  response.end('{}');
});

server.listen(0, '127.0.0.1', () => {
  const address = server.address();
  const origin = `http://127.0.0.1:${address.port}`;
  const output = path.resolve('security-tests/reports/self-test');
  const child = spawn(process.execPath, [
    'security-tests/smoke-test.mjs',
    '--base-url', `${origin}/wp-json/scarto/v1`,
    '--page-url', `${origin}/page/`,
    '--output', output,
    '--allow-http-local=true'
  ], { stdio: 'inherit' });

  child.on('exit', code => {
    server.close(() => process.exit(code ?? 1));
  });
});

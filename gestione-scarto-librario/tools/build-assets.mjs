import fs from 'node:fs';
import { build } from 'vite';

fs.rmSync('dist', { recursive: true, force: true });
await build({ mode: 'public' });
await build({ mode: 'admin' });

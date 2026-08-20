import fs from 'node:fs';
import path from 'node:path';
import PhpParser from 'php-parser';

const parser = new PhpParser.Engine({
  parser: { php7: true },
  ast: { withPositions: true }
});

const files = [];
const walk = directory => {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const file = path.join(directory, entry.name);
    if (entry.isDirectory() && !['node_modules', 'dist'].includes(entry.name)) {
      walk(file);
    } else if (entry.isFile() && file.endsWith('.php')) {
      files.push(file);
    }
  }
};

walk('.');
let failed = false;
for (const file of files) {
  try {
    parser.parseCode(fs.readFileSync(file, 'utf8'), file);
    console.log(`OK ${file}`);
  } catch (error) {
    failed = true;
    console.error(`FAIL ${file}: ${error.message}`);
  }
}

if (failed) process.exit(1);

import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const source = resolve('public/assets/build');
const destinations = [
  resolve('../public-assets/build'),
];

if (!existsSync(source)) {
  throw new Error(`[publish-build] Directorio de origen no encontrado: ${source}`);
}

for (const destination of destinations) {
  if (existsSync(destination)) {
    rmSync(destination, { recursive: true, force: true });
  }

  mkdirSync(dirname(destination), { recursive: true });
  cpSync(source, destination, { recursive: true });
}

console.log('[publish-build] Directorio build copiado a los destinos configurados.');

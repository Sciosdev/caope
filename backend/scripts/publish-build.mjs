import { readFileSync, copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const manifestPath = resolve('public/build/manifest.json');
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));

const buildRoot = resolve('public/build');
const targets = [
  {
    source: manifest['resources/css/app.css']?.file,
    destinations: [
      resolve('public/assets/build/css/app.css'),
      resolve('../public-assets/build/css/app.css'),
    ],
  },
  {
    source: manifest['resources/js/app.js']?.file,
    destinations: [
      resolve('public/assets/build/js/app.js'),
      resolve('../public-assets/build/js/app.js'),
    ],
  },
  {
    source: manifest['resources/js/app.js']?.css?.[0],
    destinations: [
      resolve('public/assets/build/css/app-editor.css'),
      resolve('../public-assets/build/css/app-editor.css'),
    ],
  },
  {
    source: manifest['resources/js/expedientes/anexos.js']?.file,
    destinations: [
      resolve('public/assets/build/js/anexos.js'),
      resolve('../public-assets/build/js/anexos.js'),
    ],
  },
  {
    source: manifest['resources/js/expedientes/anexos.js']?.css?.[0],
    destinations: [
      resolve('public/assets/build/css/anexos.css'),
      resolve('../public-assets/build/css/anexos.css'),
    ],
  },
];

for (const { source, destinations } of targets) {
  if (!source) {
    continue;
  }

  const absoluteSource = resolve(buildRoot, source);

  for (const destination of destinations) {
    mkdirSync(dirname(destination), { recursive: true });
    copyFileSync(absoluteSource, destination);
  }
}

console.log('[publish-build] Assets copiados a public/assets/build y ../public-assets/build.');

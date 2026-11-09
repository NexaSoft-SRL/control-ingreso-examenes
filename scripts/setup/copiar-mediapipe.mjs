// Copia a public/vendor/mediapipe/wasm/ el WebAssembly de
// @mediapipe/tasks-vision que está instalado, para servirlo desde el propio
// servidor sin desincronizarse de la versión de package.json.
// Corre tras `npm install` / `npm ci` (postinstall) o con
// `npm run mediapipe:copiar`.
import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const paquete = join(raiz, 'node_modules', '@mediapipe', 'tasks-vision');
const destino = join(raiz, 'public', 'vendor', 'mediapipe', 'wasm');

// Solo las variantes que pide FilesetResolver.forVisionTasks(ruta) sin
// módulos ES6: con SIMD y, para navegadores viejos, sin SIMD.
const ARCHIVOS = [
    'vision_wasm_internal.js',
    'vision_wasm_internal.wasm',
    'vision_wasm_nosimd_internal.js',
    'vision_wasm_nosimd_internal.wasm',
];

if (!existsSync(join(paquete, 'wasm'))) {
    console.warn('mediapipe: @mediapipe/tasks-vision no está instalado; nada que copiar');
    process.exit(0);
}

rmSync(destino, { recursive: true, force: true });
mkdirSync(destino, { recursive: true });
for (const archivo of ARCHIVOS) {
    copyFileSync(join(paquete, 'wasm', archivo), join(destino, archivo));
}

const { version } = JSON.parse(readFileSync(join(paquete, 'package.json'), 'utf8'));
console.log(`mediapipe: wasm ${version} copiado a public/vendor/mediapipe/wasm/`);

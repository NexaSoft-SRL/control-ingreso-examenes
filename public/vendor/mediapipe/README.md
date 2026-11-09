# MediaPipe Face Detector y Face Landmarker

Archivos que usa `resources/js/utiles/revisarFoto.js` para revisar la foto del estudiante en
el navegador. Se sirven desde este servidor, no desde un CDN.

| Archivo | Origen | Versión |
|---|---|---|
| `wasm/vision_wasm_internal.{js,wasm}` y `wasm/vision_wasm_nosimd_internal.{js,wasm}` | `node_modules/@mediapipe/tasks-vision/wasm/` (npm, Apache-2.0) | `@mediapipe/tasks-vision` 1.1.1, fijada en `package.json` |
| `blaze_face_short_range.tflite` | <https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite> (Apache-2.0) | BlazeFace corto alcance, float16, versión 1 (SHA-256 `b4578f35940bf5a1a655214a1cce5cab13eba73c1297cd78e1a04c2380b0152f`) |
| `face_landmarker.task` | <https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task> (Apache-2.0) | Face Landmarker (478 puntos), float16, versión 1 (SHA-256 `64184e229b263107bc2b804c6625db1341ff2bb731874b0bcc2fe6544e0bc9ff`) |

- `wasm/` no se versiona: lo copia `scripts/setup/copiar-mediapipe.mjs` tras cada `npm install`
  o `npm ci` (`postinstall`), o a mano con `npm run mediapipe:copiar`. Así siempre coincide con
  la versión instalada del paquete. Se copian solo las variantes con y sin SIMD; la de módulos
  ES6 (`vision_wasm_module_internal`) no se usa.
- Los dos modelos sí se versionan (con `git add -f`, porque `vendor` está excluido).
- Al cambiar la versión del paquete, revisar que los modelos sigan siendo compatibles.
- El navegador descarga una sola variante del WebAssembly (unos 13 MB), el detector (230 KB) y
  el Face Landmarker (3.6 MB), solo en la pantalla de la foto.
- El detector busca el rostro (uno, grande, centrado, de frente); el Face Landmarker da los
  puntos del rostro con los que se buscan lentes y filtros (`resources/js/utiles/rasgos.js`).

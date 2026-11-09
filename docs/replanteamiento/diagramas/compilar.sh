#!/bin/sh
# Compila los diagramas del replanteo. Ejecutar desde esta carpeta.
set -e
export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"

if ! command -v plantuml >/dev/null 2>&1; then
  echo 'ERROR: falta PlantUML. Instalar con: brew install plantuml' >&2
  exit 1
fi

DOCS='c4_1_contexto c4_2_contenedores c4_3_componentes modelo_datos flujo_1_preparar_periodo flujo_2_cargar_inscritos flujo_3_preparar_examen flujo_4_ingreso'

for f in $DOCS; do
  plantuml -tpng "$f.puml"
  printf '%-32s %s\n' "$f.png" "$(du -h "$f.png" | cut -f1)"
done

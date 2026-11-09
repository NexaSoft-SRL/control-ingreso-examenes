#!/bin/sh
# Compila las fichas de historia de usuario del proyecto guia.
# Ejecutar desde esta carpeta:
#
#   sh compilar.sh                # todas
#   sh compilar.sh sprint4        # las de un sprint
#   sh compilar.sh hu24 hu27      # algunas sueltas
#
# Las fichas viven en sprintN/huNN.tex y comparten cuerpo.tex, comun.tex y
# la carpeta mockups/. Se compilan desde aqui ---y no desde la carpeta del
# sprint--- justamente para que esas rutas compartidas resuelvan solas.
set -e
export PATH="/Library/TeX/texbin:$PATH"

if [ ! -f cuerpo.tex ]; then
  echo 'ERROR: ejecutar desde docs/guia/fichas.' >&2
  exit 1
fi

listar() {
  if [ $# -eq 0 ]; then
    ls sprint*/hu*.tex 2>/dev/null
    return
  fi

  for arg in "$@"; do
    case "$arg" in
      sprint*) ls "$arg"/hu*.tex 2>/dev/null ;;
      *)       ls sprint*/"${arg%.tex}".tex 2>/dev/null ;;
    esac
  done
}

DOCS=$(listar "$@")

if [ -z "$DOCS" ]; then
  echo 'No hay fichas que compilar con ese criterio.'
  exit 0
fi

AVISOS=0
for ruta in $DOCS; do
  carpeta=$(dirname "$ruta")
  ficha=$(basename "$ruta" .tex)
  mkdir -p "build/$carpeta"

  for _ in 1 2; do
    pdflatex -interaction=nonstopmode -file-line-error \
      -output-directory="build/$carpeta" "$ruta" >/dev/null
  done

  cp "build/$carpeta/$ficha.pdf" "$carpeta/"
  printf '%-18s %s pag.  ' "$carpeta/$ficha.pdf" \
    "$(pdfinfo "$carpeta/$ficha.pdf" | awk '/^Pages/{print $2}')"

  if /usr/bin/grep -qE 'Overfull|Underfull|Warning' "build/$carpeta/$ficha.log"; then
    echo 'CON AVISOS'; AVISOS=1
  else
    echo 'sin avisos'
  fi
done

[ "$AVISOS" -eq 0 ] && rm -rf build
exit 0

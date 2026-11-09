#!/bin/sh
# Compila el analisis de contexto. Ejecutar desde esta carpeta.
set -e
export PATH="/Library/TeX/texbin:$PATH"

mkdir -p build
for _ in 1 2; do
  pdflatex -interaction=nonstopmode -file-line-error -output-directory=build analisis_contexto.tex >/dev/null
done
cp build/analisis_contexto.pdf .
printf '%-24s %s pag.  ' analisis_contexto.pdf "$(pdfinfo analisis_contexto.pdf | awk '/^Pages/{print $2}')"
if /usr/bin/grep -qE 'Overfull|Underfull|Warning' build/analisis_contexto.log; then
  echo 'CON AVISOS'
else
  echo 'sin avisos'; rm -rf build
fi

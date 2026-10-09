// Textos y formatos que comparten las pantallas.

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

// Para comparar sin distinguir mayúsculas ni tildes.
export function normalizar(texto) {
    return String(texto ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '');
}

// plural(1, 'grupo') → '1 grupo'; plural(3, 'aula') → '3 aulas'.
export function plural(cantidad, singular, pluralTexto = `${singular}s`) {
    return `${cantidad} ${cantidad === 1 ? singular : pluralTexto}`;
}

export function nombreCompleto(e) {
    return `${e.apellidos}, ${e.nombres}`;
}

// 'Blanco Coca Leticia' → 'BC'.
export function iniciales(nombre) {
    return String(nombre ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
}

function partes(iso) {
    const [fecha, resto] = String(iso ?? '').split(/[ T]/);
    const [anio, mes, dia] = fecha.split('-').map(Number);
    return {
        anio,
        mes,
        dia,
        hora: resto ? resto.slice(0, 5) : null,
        valida: Boolean(anio && mes && dia),
    };
}

// '2026-10-04 03:00' → '4 oct 2026, 03:00'; '2026-10-04' → '4 oct 2026'.
// Acepta también el instante ISO ('2026-10-04T03:00:00-04:00').
export function fechaCorta(iso) {
    const { anio, mes, dia, hora, valida } = partes(iso);
    if (!valida) return '';
    const texto = `${dia} ${MESES[mes - 1]} ${anio}`;
    return hora ? `${texto}, ${hora}` : texto;
}

// '2026-10-12' → '12 oct'.
export function diaMes(iso) {
    const { mes, dia, valida } = partes(iso);
    return valida ? `${dia} ${MESES[mes - 1]}` : '';
}

function mediodia(iso) {
    return new Date(`${String(iso ?? '').slice(0, 10)}T12:00:00`);
}

// «lunes 12 de octubre».
export function fechaLarga(iso) {
    const d = mediodia(iso);
    return Number.isNaN(d.getTime())
        ? ''
        : d
              .toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' })
              .replace(',', '');
}

// «Octubre 2026», para cualquier mes.
export function mesDe(iso) {
    const d = mediodia(iso);
    if (Number.isNaN(d.getTime())) return '';
    const texto = d
        .toLocaleDateString('es', { month: 'long', year: 'numeric' })
        .replace(' de ', ' ');
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

// Colores fuera de la paleta semántica y de los de las facultades. Cada
// asignatura conserva el suyo: sale de su nombre.
export const COLORES_ASIGNATURA = [
    '#0e7490',
    '#a21caf',
    '#4d7c0f',
    '#9d174d',
    '#3730a3',
    '#57534e',
    '#0f766e',
    '#7e22ce',
];

export function colorDeAsignatura(nombre) {
    const texto = normalizar(nombre);
    if (!texto) return '#475569';
    let suma = 0;
    for (const letra of texto) suma = (suma * 31 + letra.codePointAt(0)) % 9973;
    return COLORES_ASIGNATURA[suma % COLORES_ASIGNATURA.length];
}

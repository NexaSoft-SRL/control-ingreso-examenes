import { diaMes } from '../../utiles/texto';

// La semana de una aula: de lunes a sábado (el domingo no hay clases ni
// exámenes), de 06:45 a 21:45, las horas del horario de la universidad.
export const INICIO = 6 * 60 + 45;
export const FIN = 21 * 60 + 45;
export const PX_HORA = 52;
export const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

// Una marca por hora, en punto de las :45 (06:45, 07:45 … 21:45).
export const MARCAS = Array.from({ length: (FIN - INICIO) / 60 + 1 }, (_, i) => INICIO + i * 60);

const dos = (n) => String(n).padStart(2, '0');

export const minutosDe = (hora) => Number(hora.slice(0, 2)) * 60 + Number(hora.slice(3, 5));
export const horaDe = (minutos) => `${dos(Math.floor(minutos / 60))}:${dos(minutos % 60)}`;

export const fechaISO = (d) => `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;
export const hoyISO = () => fechaISO(new Date());

export function sumarDias(iso, dias) {
    const d = new Date(`${iso}T12:00:00`);
    d.setDate(d.getDate() + dias);
    return fechaISO(d);
}

// 0 = lunes … 5 = sábado; el domingo cuenta como el lunes de la semana que empieza.
export function indiceDeDia(iso) {
    const dia = (new Date(`${iso}T12:00:00`).getDay() + 6) % 7;
    return dia > 5 ? 0 : dia;
}

// El lunes de la semana de esa fecha; un domingo es la semana que empieza.
export function lunesDe(iso) {
    const dia = (new Date(`${iso}T12:00:00`).getDay() + 6) % 7;
    return sumarDias(iso, dia === 6 ? 1 : -dia);
}

export const semanaDe = (lunes) => DIAS.map((_, i) => sumarDias(lunes, i));

// '23 – 28 nov 2026' o '30 nov – 5 dic 2026'.
export function rangoDeSemana(lunes) {
    const sabado = sumarDias(lunes, 5);
    const [d1, m1] = diaMes(lunes).split(' ');
    const [d2, m2] = diaMes(sabado).split(' ');
    const anio = sabado.slice(0, 4);
    return m1 === m2 ? `${d1} – ${d2} ${m1} ${anio}` : `${d1} ${m1} – ${d2} ${m2} ${anio}`;
}

// Lo que ocupa el aula ese día: sus clases y sus exámenes, ya recortados al
// horario de la vista y ordenados. `tipo` decide el color: 'clase',
// 'auxiliatura', 'examen' (de otro docente) o 'mio' (de la cuenta).
export function bloquesDelDia(aula, fecha) {
    const clases = (aula?.clases ?? [])
        .filter((c) => c.fecha === fecha)
        .map((c, i) => ({
            clave: `c${fecha}-${i}`,
            tipo: c.auxiliatura ? 'auxiliatura' : 'clase',
            fecha,
            inicio: c.inicio,
            fin: c.fin,
            titulo: c.asignatura,
            detalle: c.auxiliatura ? `Auxiliatura · grupo ${c.grupo}` : `Grupo ${c.grupo}`,
            docente: c.docente,
            examenId: null,
        }));
    const examenes = (aula?.examenes ?? [])
        .filter((e) => e.fecha === fecha)
        .map((e, i) => ({
            clave: `e${fecha}-${i}`,
            tipo: e.propio ? 'mio' : 'examen',
            fecha,
            inicio: e.inicio,
            fin: e.fin,
            titulo: e.asignatura,
            detalle: e.tipo,
            docente: e.docente,
            examenId: e.id,
        }));

    return [...clases, ...examenes]
        .map((b) => ({
            ...b,
            desde: Math.max(INICIO, minutosDe(b.inicio)),
            hasta: Math.min(FIN, minutosDe(b.fin)),
        }))
        .filter((b) => b.hasta > b.desde)
        .sort((a, b) => a.desde - b.desde || b.hasta - a.hasta);
}

// Los que se solapan van lado a lado: cada uno con su carril y los carriles
// que tiene su grupo de bloques.
export function conCarriles(bloques) {
    const salida = [];
    let grupo = [];
    let finGrupo = -1;
    let ocupados = [];

    const cerrar = () => {
        const carriles = Math.max(...grupo.map((b) => b.carril)) + 1;
        grupo.forEach((b) => salida.push({ ...b, carriles }));
        grupo = [];
        finGrupo = -1;
        ocupados = [];
    };

    for (const b of bloques) {
        if (grupo.length > 0 && b.desde >= finGrupo) cerrar();
        let carril = ocupados.findIndex((fin) => fin <= b.desde);
        if (carril === -1) {
            carril = ocupados.length;
            ocupados.push(b.hasta);
        } else {
            ocupados[carril] = b.hasta;
        }
        grupo.push({ ...b, carril });
        finGrupo = Math.max(finGrupo, b.hasta);
    }
    if (grupo.length > 0) cerrar();

    return salida;
}

export function posicionDe(b) {
    return {
        top: `${((b.desde - INICIO) / 60) * PX_HORA}px`,
        height: `${Math.max(((b.hasta - b.desde) / 60) * PX_HORA - 2, 20)}px`,
        left: `calc(${(b.carril / b.carriles) * 100}% + 2px)`,
        width: `calc(${100 / b.carriles}% - 4px)`,
    };
}

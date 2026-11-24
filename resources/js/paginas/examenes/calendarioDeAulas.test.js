import { describe, expect, it } from 'vitest';
import {
    MARCAS,
    bloquesDelDia,
    conCarriles,
    indiceDeDia,
    lunesDe,
    posicionDe,
    rangoDeSemana,
    semanaDe,
    sumarDias,
} from './calendarioDeAulas';

describe('semana del calendario', () => {
    it('pasa cualquier día de la semana a su lunes, y el domingo a la semana que empieza', () => {
        expect(lunesDe('2026-11-24')).toBe('2026-11-23');
        expect(lunesDe('2026-11-23')).toBe('2026-11-23');
        expect(lunesDe('2026-11-28')).toBe('2026-11-23');
        expect(lunesDe('2026-11-29')).toBe('2026-11-30');
    });

    it('cuenta de lunes a sábado y trata el domingo como el lunes', () => {
        expect(semanaDe('2026-11-23')).toEqual([
            '2026-11-23',
            '2026-11-24',
            '2026-11-25',
            '2026-11-26',
            '2026-11-27',
            '2026-11-28',
        ]);
        expect([
            indiceDeDia('2026-11-23'),
            indiceDeDia('2026-11-28'),
            indiceDeDia('2026-11-29'),
        ]).toEqual([0, 5, 0]);
        expect(sumarDias('2026-12-30', 3)).toBe('2027-01-02');
    });

    it('rotula el rango, también cuando cambia el mes', () => {
        expect(rangoDeSemana('2026-11-23')).toBe('23 – 28 nov 2026');
        expect(rangoDeSemana('2026-11-30')).toBe('30 nov – 5 dic 2026');
    });

    it('marca una hora en punto de las :45, de 06:45 a 21:45', () => {
        expect(MARCAS).toHaveLength(16);
        expect([MARCAS[0], MARCAS[15]]).toEqual([405, 1305]);
    });
});

const aula = {
    clases: [
        {
            fecha: '2026-11-23',
            inicio: '08:15',
            fin: '09:45',
            asignatura: 'Cálculo I',
            grupo: 'G1',
            docente: 'Ana',
            auxiliatura: false,
        },
        {
            fecha: '2026-11-23',
            inicio: '09:45',
            fin: '10:30',
            asignatura: 'Física',
            grupo: 'G2',
            docente: null,
            auxiliatura: true,
        },
        {
            fecha: '2026-11-24',
            inicio: '08:15',
            fin: '09:45',
            asignatura: 'Otra',
            grupo: 'G3',
            docente: null,
            auxiliatura: false,
        },
    ],
    examenes: [
        {
            id: 7,
            fecha: '2026-11-23',
            inicio: '09:00',
            fin: '10:00',
            asignatura: 'Cálculo I',
            tipo: 'Primer parcial',
            docente: 'Ana',
            propio: true,
        },
        {
            id: null,
            fecha: '2026-11-23',
            inicio: '21:00',
            fin: '23:30',
            asignatura: 'Tarde',
            tipo: 'Examen final',
            docente: 'Luis',
            propio: false,
        },
    ],
};

describe('bloques de un día', () => {
    it('junta clases y exámenes de ese día, con su tipo, ordenados y recortados al horario', () => {
        const bloques = bloquesDelDia(aula, '2026-11-23');

        expect(bloques.map((b) => [b.tipo, b.titulo, b.inicio, b.fin])).toEqual([
            ['clase', 'Cálculo I', '08:15', '09:45'],
            ['mio', 'Cálculo I', '09:00', '10:00'],
            ['auxiliatura', 'Física', '09:45', '10:30'],
            ['examen', 'Tarde', '21:00', '23:30'],
        ]);
        // El examen de la noche se corta a las 21:45.
        expect(bloques[3].hasta).toBe(21 * 60 + 45);
        expect(bloques[1].examenId).toBe(7);
        expect(bloques[0].detalle).toBe('Grupo G1');
        expect(bloques[2].detalle).toBe('Auxiliatura · grupo G2');
    });

    it('un día libre no tiene bloques', () => {
        expect(bloquesDelDia(aula, '2026-11-25')).toEqual([]);
        expect(bloquesDelDia(undefined, '2026-11-25')).toEqual([]);
    });
});

describe('carriles de los que se solapan', () => {
    it('los que se pisan van lado a lado y los demás a todo el ancho', () => {
        const bloques = conCarriles(bloquesDelDia(aula, '2026-11-23'));
        const por = Object.fromEntries(
            bloques.map((b) => [`${b.titulo}-${b.tipo}`, [b.carril, b.carriles]])
        );

        // Clase 08:15–09:45 y examen 09:00–10:00 se pisan; la auxiliatura de 09:45 pisa al examen.
        expect(por['Cálculo I-clase']).toEqual([0, 2]);
        expect(por['Cálculo I-mio']).toEqual([1, 2]);
        expect(por['Física-auxiliatura']).toEqual([0, 2]);
        expect(por['Tarde-examen']).toEqual([0, 1]);
    });

    it('da a cada bloque su posición en la grilla', () => {
        const [uno] = conCarriles(
            bloquesDelDia({ clases: [aula.clases[0]], examenes: [] }, '2026-11-23')
        );

        // 08:15 está a 1,5 h de las 06:45: 78 px con 52 px por hora.
        expect(posicionDe(uno)).toEqual({
            top: '78px',
            height: '76px',
            left: 'calc(0% + 2px)',
            width: 'calc(100% - 4px)',
        });
    });
});

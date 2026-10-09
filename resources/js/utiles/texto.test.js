import { describe, expect, it } from 'vitest';
import {
    COLORES_ASIGNATURA,
    colorDeAsignatura,
    diaMes,
    fechaCorta,
    fechaLarga,
    iniciales,
    mesDe,
    nombreCompleto,
    normalizar,
    plural,
} from './texto';

describe('utiles de texto', () => {
    it('normaliza sin mayúsculas ni tildes', () => {
        expect(normalizar('Álgebra LINEAL ñ')).toBe('algebra lineal n');
        expect(normalizar(null)).toBe('');
    });

    it('arma el plural con la cantidad', () => {
        expect(plural(1, 'grupo')).toBe('1 grupo');
        expect(plural(3, 'aula')).toBe('3 aulas');
        expect(plural(2, 'examen', 'exámenes')).toBe('2 exámenes');
    });

    it('da el nombre completo y las iniciales', () => {
        expect(nombreCompleto({ apellidos: 'Aguilar Cossío', nombres: 'Mariana' })).toBe(
            'Aguilar Cossío, Mariana'
        );
        expect(iniciales('Blanco Coca Leticia')).toBe('BC');
        expect(iniciales('')).toBe('');
    });

    it('escribe las fechas cortas', () => {
        expect(fechaCorta('2026-10-04')).toBe('4 oct 2026');
        expect(fechaCorta('2026-10-04 03:00')).toBe('4 oct 2026, 03:00');
        expect(fechaCorta('2026-10-12T09:45:00-04:00')).toBe('12 oct 2026, 09:45');
        expect(fechaCorta(null)).toBe('');
        expect(diaMes('2026-10-12')).toBe('12 oct');
    });

    it('escribe la fecha larga y el mes', () => {
        expect(fechaLarga('2026-10-12')).toBe('lunes 12 de octubre');
        expect(fechaLarga('')).toBe('');
        expect(mesDe('2026-10-12')).toBe('Octubre 2026');
    });

    it('da a cada asignatura un color estable de la paleta', () => {
        const color = colorDeAsignatura('Introducción a la Programación');
        expect(COLORES_ASIGNATURA).toContain(color);
        expect(colorDeAsignatura('introduccion a la programacion')).toBe(color);
        expect(colorDeAsignatura('')).toBe('#475569');
    });
});

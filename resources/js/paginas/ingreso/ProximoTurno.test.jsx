import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import ProximoTurno, { turnoDe } from './ProximoTurno';

const examen = (fecha, hora, cambios = {}) => ({
    tipo: 'PRIMER_PARCIAL',
    tipo_texto: 'Primer parcial',
    asignatura: 'Cálculo I',
    fecha,
    hora,
    abierto: false,
    aulas: [{ aula_id: 35, nombre: '691E' }],
    ...cambios,
});
const ahora = new Date(2026, 10, 23, 10, 0);

describe('ProximoTurno', () => {
    it('el de otro día: aula, fecha, hora y a qué hora abre', () => {
        render(<ProximoTurno examenes={[examen('2026-11-25', '14:15')]} ahora={ahora} />);

        expect(
            screen.getByText(/Próximo: Primer parcial · Cálculo I · aula 691E/)
        ).toHaveTextContent(/14:15 · abre 13:15/);
    });

    it('el de hoy que todavía no abre es el próximo; el de hoy ya pasado dice que terminó', () => {
        expect(turnoDe([examen('2026-11-23', '14:15')], ahora).tipo).toBe('proximo');
        expect(turnoDe([examen('2026-11-23', '10:30')], ahora).tipo).toBe('terminado');

        render(<ProximoTurno examenes={[examen('2026-11-23', '08:15')]} ahora={ahora} />);
        expect(
            screen.getByText('Terminó: Primer parcial · Cálculo I · aula 691E')
        ).toBeInTheDocument();
    });

    it('sin exámenes asignados dice a quién acudir', () => {
        render(<ProximoTurno examenes={[]} ahora={ahora} />);

        expect(screen.getByText('Sin aulas asignadas · avise al docente')).toBeInTheDocument();
    });
});

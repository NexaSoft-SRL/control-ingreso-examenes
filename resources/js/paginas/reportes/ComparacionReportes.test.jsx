import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../api/cliente';
import { simularApi } from '../../test/apoyo';
import ComparacionReportes from './ComparacionReportes';

vi.mock('../../api/cliente');

const examen = (id, periodoId, periodo, fecha) => ({
    id,
    asignatura: 'Cálculo I',
    tipo: 'Primer parcial',
    fecha,
    periodo_id: periodoId,
    periodo,
    rendido: true,
    asistencia: 80,
});

function montar(periodos, examenes) {
    simularApi(api, {
        'GET /reportes/comparacion/opciones': { periodos, examenes },
        'GET /reportes/alertas': {
            preferencias: { asistencia_minima: 70, incidencias_minimas: 5 },
            alertas: [],
        },
    });
    render(<ComparacionReportes />);
}

describe('Comparación de reportes', () => {
    it('con un solo período no ofrece compararlos y muestra las fechas como el resto', async () => {
        montar(
            [{ id: 1, codigo: '2/2026', examenes: 2 }],
            [examen(5, 1, '2/2026', '2026-11-15'), examen(6, 1, '2/2026', '2026-10-12')]
        );

        expect(await screen.findByRole('heading', { name: 'Comparar exámenes' })).toBeVisible();
        expect(screen.queryByRole('heading', { name: 'Comparar períodos' })).toBeNull();
        expect(screen.getByText(/15 nov 2026/)).toBeInTheDocument();
        expect(screen.queryByText(/2026-11-15/)).toBeNull();
        expect(await screen.findByText('Sin alertas')).toBeInTheDocument();
    });

    it('con dos períodos o más permite compararlos', async () => {
        montar(
            [
                { id: 1, codigo: '2/2026', examenes: 1 },
                { id: 2, codigo: '1/2026', examenes: 1 },
            ],
            [examen(5, 1, '2/2026', '2026-11-15'), examen(6, 2, '1/2026', '2026-05-12')]
        );

        expect(await screen.findByRole('heading', { name: 'Comparar períodos' })).toBeVisible();
        expect(screen.getByRole('checkbox', { name: 'Período 1/2026' })).toBeInTheDocument();
    });
});

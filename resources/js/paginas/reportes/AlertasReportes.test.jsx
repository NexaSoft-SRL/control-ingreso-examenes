import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../api/cliente';
import { simularApi } from '../../test/apoyo';
import AlertasReportes from './AlertasReportes';

vi.mock('../../api/cliente');

const alerta = {
    id: 8,
    examen_id: 3,
    asignatura: 'Cálculo I',
    tipo_examen: 'Primer parcial',
    fecha: '2026-10-12',
    tipo: 'ASISTENCIA_BAJA',
    titulo: 'Asistencia por debajo del umbral',
    mensaje: 'Cálculo I: asistencia de 25%, por debajo del umbral de 70%.',
    correo_estado: 'FALLIDO',
    leida_en: null,
    creada_en: '2026-10-12T10:00:00-04:00',
};

describe('Alertas de reportes', () => {
    beforeEach(() => {
        let preferencias = { asistencia_minima: 70, incidencias_minimas: 5 };
        let alertas = [alerta];
        simularApi(api, {
            'GET /reportes/alertas': () => ({ preferencias, alertas }),
            'PUT /reportes/alertas/preferencias': ({ data }) => {
                preferencias = data;
                return { preferencias };
            },
            'POST /reportes/alertas/*/leida': () => {
                alertas = alertas.map((actual) => ({ ...actual, leida_en: 'ahora' }));
                return {};
            },
        });
    });

    it('guarda umbrales y permite marcar una alerta como leída', async () => {
        render(<AlertasReportes />);

        expect(await screen.findByText(/Cálculo I: asistencia de 25%/)).toBeInTheDocument();
        expect(screen.getByText('Correo: no enviado')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Asistencia mínima (%)'), {
            target: { value: '75' },
        });
        fireEvent.change(screen.getByLabelText('Incidencias para alertar'), {
            target: { value: '4' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar umbrales' }));

        await screen.findByText('Umbrales de alerta guardados');
        expect(api.put).toHaveBeenCalledWith('/reportes/alertas/preferencias', {
            asistencia_minima: '75',
            incidencias_minimas: '4',
        });

        fireEvent.click(screen.getByRole('button', { name: 'Marcar leída' }));
        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/reportes/alertas/8/leida');
            expect(screen.queryByRole('button', { name: 'Marcar leída' })).not.toBeInTheDocument();
        });
    });
});

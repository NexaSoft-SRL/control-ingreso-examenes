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
        expect(
            screen.getByText('Generada el 12 oct 2026 · Correo: no enviado')
        ).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/^Asistencia mínima/), {
            target: { value: '75' },
        });
        fireEvent.change(screen.getByLabelText(/^Alertar desde/), {
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

    it('los umbrales solo admiten números dentro del rango y no se guardan si no lo están', async () => {
        render(<AlertasReportes />);
        await screen.findByText(/Cálculo I: asistencia de 25%/);

        const asistencia = screen.getByLabelText(/^Asistencia mínima/);
        const incidencias = screen.getByLabelText(/^Alertar desde/);
        const guardar = screen.getByRole('button', { name: 'Guardar umbrales' });

        fireEvent.change(asistencia, { target: { value: '8a5' } });
        expect(asistencia).toHaveValue('85');

        fireEvent.change(asistencia, { target: { value: '120' } });
        expect(screen.getByText('Entre 0 y 100')).toBeInTheDocument();
        expect(guardar).toBeDisabled();
        expect(screen.getByText('Corrija el campo')).toBeInTheDocument();

        fireEvent.change(asistencia, { target: { value: '80' } });
        fireEvent.change(incidencias, { target: { value: '0' } });
        expect(screen.getByText('Entre 1 y 999')).toBeInTheDocument();
        expect(guardar).toBeDisabled();
        expect(api.put).not.toHaveBeenCalled();

        fireEvent.change(incidencias, { target: { value: '3' } });
        expect(guardar).toBeEnabled();
        expect(screen.queryByText('Corrija el campo')).toBeNull();
    });
});

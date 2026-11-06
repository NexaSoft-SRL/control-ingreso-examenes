import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FotoDelInscrito from './FotoDelInscrito';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const montar = (foto, onHabilitada = vi.fn()) =>
    renderConSesion(
        <FotoDelInscrito
            grupoId={4}
            estudiante={{ id: 9, nombre: 'Romero Rojas Camila', foto }}
            onHabilitada={onHabilitada}
            onCerrar={vi.fn()}
        />,
        { usuario: usuarioDePrueba('Docente') }
    );

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-23T10:00:00'));
});

afterEach(() => vi.useRealTimers());

describe('FotoDelInscrito', () => {
    it('sin foto propone una semana y el docente elige otro día', async () => {
        simularApi(api, {
            'POST /docente/grupos/4/inscritos/9/foto': {
                hasta: '2026-11-06',
                message: 'Foto habilitada.',
            },
        });
        const onHabilitada = vi.fn();
        montar({ subida_en: null, habilitada_hasta: null }, onHabilitada);

        expect(screen.getByText('Sin foto')).toBeInTheDocument();
        const dia = screen.getByLabelText(/Puede subirla hasta/);
        expect(dia).toHaveValue('2026-10-30');
        expect(dia).toHaveAttribute('min', '2026-10-23');
        expect(dia).toHaveAttribute('max', '2026-11-22');
        fireEvent.change(dia, { target: { value: '2026-11-06' } });
        fireEvent.click(screen.getByRole('button', { name: 'Habilitar' }));

        await waitFor(() =>
            expect(onHabilitada).toHaveBeenCalledWith('Foto habilitada hasta el 6 nov 2026')
        );
        expect(api.post).toHaveBeenCalledWith('/docente/grupos/4/inscritos/9/foto', {
            hasta: '2026-11-06',
        });
    });

    it('con una foto de hace menos de un año dice desde cuándo se puede cambiar', () => {
        montar({ subida_en: '2026-10-12', habilitada_hasta: null });

        expect(screen.getByText('Foto del 12 oct 2026')).toBeInTheDocument();
        expect(screen.getByText('Cambio habilitable desde el 12 oct 2027')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Habilitar/ })).not.toBeInTheDocument();
    });

    it('ya habilitado cambia el día límite', async () => {
        simularApi(api, {
            'POST /docente/grupos/4/inscritos/9/foto': { hasta: '2026-11-02' },
        });
        const onHabilitada = vi.fn();
        montar({ subida_en: null, habilitada_hasta: '2026-10-28' }, onHabilitada);

        expect(screen.getByText('Habilitado hasta el 28 oct 2026')).toBeInTheDocument();
        const cambiar = screen.getByRole('button', { name: 'Cambiar plazo' });
        expect(cambiar).toBeDisabled();
        fireEvent.change(screen.getByLabelText(/Puede subirla hasta/), {
            target: { value: '2026-11-02' },
        });
        fireEvent.click(cambiar);

        await waitFor(() =>
            expect(onHabilitada).toHaveBeenCalledWith('Foto habilitada hasta el 2 nov 2026')
        );
    });

    it('muestra bajo el campo el día rechazado', async () => {
        simularApi(api, {
            'POST /docente/grupos/4/inscritos/9/foto': errorHttp(422, {
                errors: { hasta: ['Máximo 30 días'] },
            }),
        });
        montar({ subida_en: null, habilitada_hasta: null });
        fireEvent.click(screen.getByRole('button', { name: 'Habilitar' }));

        expect(await screen.findByText('Máximo 30 días')).toBeInTheDocument();
    });

    it('muestra el rechazo del servidor', async () => {
        simularApi(api, {
            'POST /docente/grupos/4/inscritos/9/foto': errorHttp(409, {
                message: 'Foto del 12 oct 2026: el cambio se puede habilitar desde el 12 oct 2027.',
            }),
        });
        montar({ subida_en: null, habilitada_hasta: null });
        fireEvent.click(screen.getByRole('button', { name: 'Habilitar' }));

        expect(
            await screen.findByText(
                'Foto del 12 oct 2026: el cambio se puede habilitar desde el 12 oct 2027'
            )
        ).toBeInTheDocument();
    });
});

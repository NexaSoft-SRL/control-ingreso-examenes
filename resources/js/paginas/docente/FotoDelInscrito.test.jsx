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
    it('sin foto habilita subirla por siete días', async () => {
        simularApi(api, {
            'POST /docente/grupos/4/inscritos/9/foto': {
                hasta: '2026-10-30',
                message: 'Foto habilitada.',
            },
        });
        const onHabilitada = vi.fn();
        montar({ subida_en: null, habilitada_hasta: null }, onHabilitada);

        expect(screen.getByText('Sin foto')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Habilitar 7 días' }));

        await waitFor(() =>
            expect(onHabilitada).toHaveBeenCalledWith('Foto habilitada hasta el 30 oct 2026')
        );
    });

    it('con una foto de hace menos de un año dice desde cuándo se puede cambiar', () => {
        montar({ subida_en: '2026-10-12', habilitada_hasta: null });

        expect(screen.getByText('Foto del 12 oct 2026')).toBeInTheDocument();
        expect(screen.getByText('Cambio habilitable desde el 12 oct 2027')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Habilitar/ })).not.toBeInTheDocument();
    });

    it('ya habilitado no lo ofrece de nuevo', () => {
        montar({ subida_en: null, habilitada_hasta: '2026-10-28' });
        expect(screen.getByText('Habilitado hasta el 28 oct 2026')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Habilitar/ })).not.toBeInTheDocument();
    });

    it('muestra el rechazo del servidor', async () => {
        simularApi(api, {
            'POST /docente/grupos/4/inscritos/9/foto': errorHttp(409, {
                message: 'Ya habilitado hasta el 30 oct 2026 por Salazar Serrudo Carla.',
            }),
        });
        montar({ subida_en: null, habilitada_hasta: null });
        fireEvent.click(screen.getByRole('button', { name: 'Habilitar 7 días' }));

        expect(
            await screen.findByText('Ya habilitado hasta el 30 oct 2026 por Salazar Serrudo Carla')
        ).toBeInTheDocument();
    });
});

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
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:foto');
    globalThis.URL.revokeObjectURL = vi.fn();
    simularApi(api, { 'GET /docente/grupos/4/inscritos/9/foto': new Blob(['x']) });
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

        expect(screen.getAllByText('Sin foto').length).toBeGreaterThan(0);
        expect(screen.getByText('Aún no subió foto')).toBeInTheDocument();
        expect(screen.getByRole('dialog', { name: /^Foto de / })).toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalled();
        const dia = screen.getByLabelText(/Plazo para subirla/);
        expect(dia).toHaveValue('2026-10-30');
        expect(dia).toHaveAttribute('min', '2026-10-23');
        expect(dia).toHaveAttribute('max', '2026-11-22');
        fireEvent.change(dia, { target: { value: '2026-11-06' } });
        fireEvent.click(screen.getByRole('button', { name: 'Dar plazo para subir foto' }));

        await waitFor(() =>
            expect(onHabilitada).toHaveBeenCalledWith('Foto habilitada hasta el 6 nov 2026')
        );
        expect(api.post).toHaveBeenCalledWith('/docente/grupos/4/inscritos/9/foto', {
            hasta: '2026-11-06',
        });
    });

    it('con una foto de hace menos de un año dice desde cuándo se puede cambiar', () => {
        montar({ subida_en: '2026-10-12', habilitada_hasta: null });

        expect(screen.getByText('Con foto · del 12 oct 2026')).toBeInTheDocument();
        expect(screen.getByText('No se puede cambiar todavía')).toBeInTheDocument();
        expect(
            screen.getByText('Se permite desde el 12 oct 2027, un año después de subirla')
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Habilitar/ })).not.toBeInTheDocument();
    });

    it('con una foto de hace un año ya ofrece habilitar el cambio', () => {
        montar({ subida_en: '2025-10-01', habilitada_hasta: null });

        expect(screen.getByText('Con foto · del 1 oct 2025')).toBeInTheDocument();
        expect(screen.getByLabelText(/Plazo para cambiarla/)).toHaveValue('2026-10-30');
        expect(
            screen.getByRole('button', { name: 'Dar plazo para subir foto' })
        ).toBeInTheDocument();
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
        fireEvent.change(screen.getByLabelText(/Plazo para subirla/), {
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
        fireEvent.click(screen.getByRole('button', { name: 'Dar plazo para subir foto' }));

        expect(await screen.findByText('Máximo 30 días')).toBeInTheDocument();
    });

    it('muestra el rechazo del servidor', async () => {
        simularApi(api, {
            'POST /docente/grupos/4/inscritos/9/foto': errorHttp(409, {
                message: 'Foto del 12 oct 2026: el cambio se puede habilitar desde el 12 oct 2027.',
            }),
        });
        montar({ subida_en: null, habilitada_hasta: null });
        fireEvent.click(screen.getByRole('button', { name: 'Dar plazo para subir foto' }));

        expect(
            await screen.findByText(
                'Foto del 12 oct 2026: el cambio se puede habilitar desde el 12 oct 2027'
            )
        ).toBeInTheDocument();
    });
});

describe('FotoDelInscrito: la foto', () => {
    beforeEach(() => {
        globalThis.URL.createObjectURL = vi.fn(() => 'blob:foto');
        globalThis.URL.revokeObjectURL = vi.fn();
    });

    it('muestra la foto del estudiante cuando la tiene', async () => {
        simularApi(api, { 'GET /docente/grupos/4/inscritos/9/foto': new Blob(['x']) });
        montar({ subida_en: '2026-10-12', habilitada_hasta: null });

        const foto = await screen.findByRole('img', { name: 'Foto de Romero Rojas Camila' });
        expect(foto).toHaveAttribute('src', 'blob:foto');
        expect(api.get).toHaveBeenCalledWith('/docente/grupos/4/inscritos/9/foto', {
            responseType: 'blob',
        });
    });

    it('si la foto no carga deja la silueta, sin romper el cuadro', async () => {
        simularApi(api, { 'GET /docente/grupos/4/inscritos/9/foto': errorHttp(404, {}) });
        montar({ subida_en: '2026-10-12', habilitada_hasta: null });

        await waitFor(() => expect(api.get).toHaveBeenCalled());
        expect(screen.queryByRole('img')).toBeNull();
        expect(screen.getByText('Con foto · del 12 oct 2026')).toBeInTheDocument();
    });
});

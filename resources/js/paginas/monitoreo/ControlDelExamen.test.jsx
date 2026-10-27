import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ControlDelExamen from './ControlDelExamen';
import { api } from '../../api/cliente';
import { renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const CONTROL = {
    data: [
        {
            aula_id: 31,
            nombre: '691A',
            usa_codigo: true,
            personal: [
                { usuario_id: 5, nombre: 'Blanco Coca Leticia', rol: 'a_cargo', visto_en: null },
                {
                    usuario_id: 9,
                    nombre: 'Mamani Quispe Diego',
                    rol: 'apoyo',
                    visto_en: '2026-10-23T10:14:00-04:00',
                },
            ],
            accesos: [
                {
                    id: 70,
                    nombre: 'Quiroga Marcelo',
                    estado: 'pendiente',
                    aula_id: 31,
                    aula: '691A',
                    pedido_en: '2026-10-23T10:12:00-04:00',
                    visto_en: null,
                },
            ],
        },
        { aula_id: 32, nombre: '617', usa_codigo: false, personal: [], accesos: [] },
    ],
    meta: { pendientes: 1 },
};

const montar = (abierto = true) =>
    renderConSesion(<ControlDelExamen examenId={1} abierto={abierto} />, {
        usuario: usuarioDePrueba('Docente'),
    });

beforeEach(() => {
    simularApi(api, {
        'GET /examenes/1/control': CONTROL,
        'POST /examenes/1/accesos/*/autorizar': {
            estado: 'autorizado',
            message: 'Acceso autorizado.',
        },
        'POST /examenes/1/movimientos': {
            movidos: 12,
            message: '12 estudiantes cambiaron de aula.',
        },
    });
});

describe('ControlDelExamen', () => {
    it('muestra quién controla cada aula y si está en la puerta', async () => {
        montar();
        expect(await screen.findByText('Mamani Quispe Diego')).toBeInTheDocument();
        expect(screen.getByText(/En la puerta · 10:14/)).toBeInTheDocument();
        expect(screen.getByText('Sin conectar')).toBeInTheDocument();
    });

    it('el docente autoriza un acceso con código en el aula que elige', async () => {
        montar();
        const pedido = await screen.findByRole('region', { name: 'Accesos por autorizar' });
        expect(within(pedido).getByText('Quiroga Marcelo')).toBeInTheDocument();

        fireEvent.change(within(pedido).getByLabelText('Aula para Quiroga Marcelo'), {
            target: { value: '32' },
        });
        fireEvent.click(within(pedido).getByRole('button', { name: /Autorizar/ }));

        expect(await screen.findByText('Acceso autorizado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/examenes/1/accesos/70/autorizar', { aula_id: 32 });
    });

    it('mueve a los estudiantes de un aula que no se pudo usar', async () => {
        montar();
        await screen.findByText('Mamani Quispe Diego');
        fireEvent.click(screen.getAllByRole('button', { name: /Mover estudiantes/ })[0]);

        const dialogo = within(screen.getByRole('dialog'));
        expect(dialogo.getByLabelText(/Aula de destino/)).toHaveValue('32');
        fireEvent.click(dialogo.getByRole('button', { name: 'Mover' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/examenes/1/movimientos', {
                de_aula_id: 31,
                a_aula_id: 32,
            })
        );
        expect(await screen.findByText('12 estudiantes cambiaron de aula')).toBeInTheDocument();
    });

    it('mueve a una persona de aula', async () => {
        montar();
        fireEvent.click(
            await screen.findByRole('button', { name: 'Mover a Mamani Quispe Diego a otra aula' })
        );
        fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Mover' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/examenes/1/movimientos', {
                de_aula_id: 31,
                a_aula_id: 32,
                usuario_id: 9,
            })
        );
    });

    it('con el examen cerrado no pregunta', () => {
        montar(false);
        expect(api.get).not.toHaveBeenCalledWith('/examenes/1/control', expect.anything());
    });
});

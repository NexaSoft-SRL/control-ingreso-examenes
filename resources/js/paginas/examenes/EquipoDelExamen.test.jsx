import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import EquipoDelExamen from './EquipoDelExamen';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const MIOS = [
    { id: 9, nombre: 'Rojas Ledezma Andrea', usuario: 'andrea.rojas' },
    { id: 12, nombre: 'Vargas Arce Sergio', usuario: 'sergio.vargas' },
];

let equipo;

const montar = () =>
    renderConSesion(<EquipoDelExamen examenId={24} />, {
        usuario: usuarioDePrueba('Docente'),
        ruta: '/examenes/detalle?examen=24',
    });

beforeEach(() => {
    equipo = [
        {
            id: 7,
            nombre: 'Blanco Coca Leticia',
            es_docente: true,
            es_auxiliar: false,
            a_cargo: true,
            agregado_por: null,
            agregado_por_nombre: null,
            puede_quitar: false,
        },
        {
            id: 40,
            nombre: 'Quispe Mamani Rosa',
            es_auxiliar: true,
            a_cargo: false,
            agregado_por: 7,
            agregado_por_nombre: 'Blanco Coca Leticia',
            puede_quitar: false,
        },
        {
            id: 41,
            nombre: 'Mamani Torrez Diego',
            es_auxiliar: true,
            a_cargo: true,
            agregado_por: 8,
            agregado_por_nombre: 'Salazar Serrudo Carla',
            puede_quitar: false,
        },
        {
            id: 42,
            nombre: 'Flores Rios Ana',
            es_auxiliar: true,
            a_cargo: false,
            agregado_por: 8,
            agregado_por_nombre: 'Salazar Serrudo Carla',
            puede_quitar: true,
        },
    ];
    simularApi(api, {
        'GET /examenes/24': () => ({ data: { id: 24, equipo } }),
        'GET /auxiliares': () => ({ data: MIOS }),
        'POST /examenes/24/equipo': ({ data }) => {
            const persona = MIOS.find((p) => p.id === data.usuario_id);
            equipo = [
                ...equipo,
                {
                    id: persona.id,
                    nombre: persona.nombre,
                    es_auxiliar: true,
                    a_cargo: false,
                    agregado_por: 8,
                    agregado_por_nombre: 'Salazar Serrudo Carla',
                    puede_quitar: true,
                },
            ];
            return { message: 'Sumado.', data: { equipo } };
        },
        'DELETE /examenes/24/equipo/*': ({ url }) => {
            equipo = equipo.filter((m) => `/examenes/24/equipo/${m.id}` !== url);
            return { message: 'Quitado.', data: { equipo } };
        },
    });
});

describe('EquipoDelExamen', () => {
    it('lista al equipo con quién sumó a cada uno y solo deja sacar a quien se puede', async () => {
        montar();

        const lista = await screen.findByRole('list', { name: 'Equipo del examen' });
        const filas = within(lista).getAllByRole('listitem');
        expect(filas).toHaveLength(4);
        expect(filas[0]).toHaveTextContent('Blanco Coca Leticia');
        expect(filas[0]).toHaveTextContent('docente');
        expect(filas[1]).toHaveTextContent('Quispe Mamani Rosa');
        expect(filas[1]).toHaveTextContent('lo sumó Blanco Coca Leticia');
        expect(filas[2]).toHaveTextContent('A cargo de un aula');
        // Solo quien sumó a Flores puede sacarla.
        expect(
            within(lista).queryByRole('button', { name: 'Sacar a Quispe Mamani Rosa del equipo' })
        ).toBeNull();
        expect(
            within(lista).getByRole('button', { name: 'Sacar a Flores Rios Ana del equipo' })
        ).toBeVisible();
    });

    it('suma a uno de sus auxiliares al equipo', async () => {
        montar();

        fireEvent.click(
            await screen.findByRole('button', { name: 'Sumar a Rojas Ledezma Andrea' })
        );

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/examenes/24/equipo', { usuario_id: 9 })
        );
        expect(
            await screen.findByText('Rojas Ledezma Andrea sumado al equipo')
        ).toBeInTheDocument();
        expect(
            await within(screen.getByRole('list', { name: 'Equipo del examen' })).findByText(
                'Rojas Ledezma Andrea'
            )
        ).toBeVisible();
        // Ya no se ofrece para sumar.
        expect(screen.queryByRole('button', { name: 'Sumar a Rojas Ledezma Andrea' })).toBeNull();
    });

    it('saca a quien sumó', async () => {
        montar();

        fireEvent.click(
            await screen.findByRole('button', { name: 'Sacar a Flores Rios Ana del equipo' })
        );

        await waitFor(() => expect(api.delete).toHaveBeenCalledWith('/examenes/24/equipo/42'));
        expect(await screen.findByText('Flores Rios Ana salió del equipo')).toBeInTheDocument();
    });

    it('dice por qué no se pudo sacar a alguien', async () => {
        api.delete.mockRejectedValueOnce(
            errorHttp(409, {
                message: 'Está a cargo de un aula: cámbielo antes.',
                codigo: 'EQUIPO_A_CARGO',
            })
        );
        montar();

        fireEvent.click(
            await screen.findByRole('button', { name: 'Sacar a Flores Rios Ana del equipo' })
        );

        expect(
            await screen.findByText('Está a cargo de un aula: cámbielo antes')
        ).toBeInTheDocument();
    });

    it('sin equipo lo dice', async () => {
        equipo = [];
        montar();

        expect(await screen.findByText('Sin auxiliares en el equipo')).toBeVisible();
    });
});

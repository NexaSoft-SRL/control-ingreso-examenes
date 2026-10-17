import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Oferta from './Oferta';
import RutaProtegida from '../../sesion/RutaProtegida';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const PERIODOS = {
    data: [
        { id: 1, codigo: '2/2026', estado: 'Vigente' },
        { id: 2, codigo: '0/2026', estado: 'Vigente' },
        { id: 3, codigo: '1/2026', estado: 'Cerrado' },
    ],
    meta: { total: 3, pagina: 1, por_pagina: 50 },
};

const CARRERAS = {
    data: [
        { id: 10, codigo: 'C1', nombre: 'Ingeniería de Sistemas', regimen: 'Semestral' },
        { id: 11, codigo: 'C2', nombre: 'Arquitectura', regimen: 'Anual' },
    ],
};

const NIVELES = { data: ['Semestre 1', 'Semestre 2', 'Semestre 3'] };

const ASIGNATURAS = {
    data: [
        {
            id: 100,
            codigo: '2010010',
            nombre: 'Introduccion a la Programacion',
            nivel: 'Semestre 1',
            facultad: 'FCyT',
            grupos: 11,
            inscritos: 260,
        },
        {
            id: 101,
            codigo: '2014051',
            nombre: 'Accionamiento y Simulacion Electronica',
            nivel: 'Semestre 10',
            facultad: 'FCyT',
            grupos: 1,
            inscritos: 0,
        },
    ],
    meta: { total: 2, pagina: 1, por_pagina: 15 },
};

const GRUPOS = {
    data: [
        {
            id: 1,
            codigo: '1',
            docente: 'Salazar Serrudo Carla',
            horarios: [{ dia: 'JU', hora: '11:15-12:45', aula: '691A' }],
            inscritos: 65,
        },
        {
            id: 2,
            codigo: '2',
            docente: null,
            horarios: [],
            inscritos: 0,
        },
    ],
    meta: { total: 2, pagina: 1, por_pagina: 10 },
};

function simular(cambios = {}) {
    return simularApi(api, {
        'GET /periodos': PERIODOS,
        'GET /oferta/asignaturas': ASIGNATURAS,
        'GET /oferta/asignaturas/*/grupos': GRUPOS,
        'GET /oferta/carreras*': CARRERAS,
        'GET /oferta/niveles': NIVELES,
        ...cambios,
    });
}

function montar(usuario = usuarioDePrueba('Administrador')) {
    return renderConSesion(<Oferta />, { usuario, ruta: '/oferta' });
}

const buscador = () => screen.getByLabelText('Asignatura, código o docente');

async function cargada() {
    await screen.findByText('Introduccion a la Programacion');
}

beforeEach(() => {
    simular();
});

describe('Oferta académica', () => {
    it('muestra la insignia de solo lectura y las asignaturas de a 15 (criterio 1)', async () => {
        montar();
        await cargada();

        expect(screen.getByRole('heading', { name: 'Oferta académica' })).toBeInTheDocument();
        expect(screen.getByText('Importado · solo lectura')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/oferta/asignaturas', {
            params: { pagina: 1, por_pagina: 15 },
        });
    });

    it('muestra el subtítulo con período, sigla y carrera; anual para la anual (criterios 2 y 5)', async () => {
        montar();
        await cargada();

        expect(screen.getByText('Períodos vigentes')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Facultad'), { target: { value: 'fcyt' } });
        await waitFor(() =>
            expect(screen.getByText('Períodos vigentes · FCyT')).toBeInTheDocument()
        );

        fireEvent.change(await screen.findByLabelText('Carrera'), {
            target: { value: '10' },
        });
        await waitFor(() =>
            expect(
                screen.getByText('Períodos vigentes · FCyT · Ingeniería de Sistemas')
            ).toBeInTheDocument()
        );

        fireEvent.change(screen.getByLabelText('Carrera'), { target: { value: '11' } });
        await waitFor(() =>
            expect(screen.getByText('Período anual · FCyT · Arquitectura')).toBeInTheDocument()
        );
    });

    it('muestra nombre, código, nivel, grupos e inscritos con singular (criterio 3)', async () => {
        montar();
        await cargada();

        const fila = screen.getByText('Introduccion a la Programacion').closest('li');
        expect(within(fila).getByText(/2010010 · Semestre 1/)).toBeInTheDocument();
        expect(within(fila).getByText('11 grupos')).toBeInTheDocument();
        expect(within(fila).getByText('260 inscritos')).toBeInTheDocument();

        const segunda = screen.getByText('Accionamiento y Simulacion Electronica').closest('li');
        expect(within(segunda).getByText('1 grupo')).toBeInTheDocument();
        expect(within(segunda).getByText('0 inscritos')).toBeInTheDocument();
    });

    it('al cambiar de facultad se limpian carrera y nivel (criterio 4)', async () => {
        montar();
        await cargada();

        fireEvent.change(screen.getByLabelText('Facultad'), { target: { value: 'fcyt' } });
        fireEvent.change(await screen.findByLabelText('Carrera'), { target: { value: '10' } });
        fireEvent.change(screen.getByLabelText('Nivel'), {
            target: { value: 'Semestre 1' },
        });

        fireEvent.change(screen.getByLabelText('Facultad'), { target: { value: 'fce' } });

        await waitFor(() => expect(screen.getByLabelText('Carrera')).toHaveValue(''));
        expect(screen.getByLabelText('Nivel')).toHaveValue('');
    });

    it('el selector de nivel se llena con los niveles filtrados (criterio 6)', async () => {
        montar();
        await cargada();

        const nivel = screen.getByLabelText('Nivel');
        await waitFor(() => within(nivel).getByRole('option', { name: 'Semestre 1' }));
        expect(within(nivel).getByRole('option', { name: 'Semestre 2' })).toBeInTheDocument();
        expect(within(nivel).getByRole('option', { name: 'Semestre 3' })).toBeInTheDocument();
    });

    it('busca por nombre, código o docente, y sin coincidencias dice «Sin resultados» (criterios 7 y 8)', async () => {
        montar();
        await cargada();

        fireEvent.change(buscador(), { target: { value: 'Programacion' } });

        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith(
                '/oferta/asignaturas',
                expect.objectContaining({
                    params: expect.objectContaining({ buscar: 'Programacion' }),
                })
            )
        );

        simular({
            'GET /oferta/asignaturas': { data: [], meta: { total: 0, pagina: 1, por_pagina: 15 } },
        });
        fireEvent.change(buscador(), { target: { value: 'zzz' } });

        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
    });

    it('al pulsar una asignatura se despliegan sus grupos y se pliega al volver a pulsar (criterio 9)', async () => {
        montar();
        await cargada();

        const boton = screen.getByRole('button', { name: /Introduccion a la Programacion/ });
        expect(boton).toHaveAttribute('aria-expanded', 'false');

        fireEvent.click(boton);
        expect(boton).toHaveAttribute('aria-expanded', 'true');
        expect(await screen.findByText('Salazar Serrudo Carla')).toBeInTheDocument();

        fireEvent.click(boton);
        expect(boton).toHaveAttribute('aria-expanded', 'false');
        await waitFor(() =>
            expect(screen.queryByText('Salazar Serrudo Carla')).not.toBeInTheDocument()
        );
    });

    it('un grupo sin docente figura «Por designar» destacado (criterio 10)', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Introduccion a la Programacion/ }));
        const porDesignar = await screen.findByText('Por designar');
        expect(porDesignar.className).toMatch(/text-danger/);
    });

    it('con búsqueda por docente, sus grupos van primero (criterio 11)', async () => {
        montar();
        await cargada();

        fireEvent.change(buscador(), { target: { value: 'Salazar' } });
        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith(
                '/oferta/asignaturas',
                expect.objectContaining({
                    params: expect.objectContaining({ buscar: 'Salazar' }),
                })
            )
        );

        fireEvent.click(screen.getByRole('button', { name: /Introduccion a la Programacion/ }));

        const grupos = (await screen.findAllByRole('listitem')).map((li) => li.textContent);
        expect(grupos[0]).toContain('Salazar Serrudo Carla');
    });

    it('los grupos se paginan de a 10 (criterio 12)', async () => {
        const muchos = {
            data: Array.from({ length: 10 }, (_, i) => ({
                id: i + 1,
                codigo: String(i + 1),
                docente: `Docente ${i + 1}`,
                horarios: [],
                inscritos: 0,
            })),
            meta: { total: 11, pagina: 1, por_pagina: 10 },
        };
        simular({ 'GET /oferta/asignaturas/*/grupos': muchos });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Introduccion a la Programacion/ }));

        await screen.findByText('Docente 1');
        expect(screen.getByText('1–10 de 11 grupos')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));

        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith(
                '/oferta/asignaturas/100/grupos',
                expect.objectContaining({
                    params: expect.objectContaining({ pagina: 2, por_pagina: 10 }),
                })
            )
        );
    });

    it('el selector de período ofrece vigentes y cerrados (criterio 13)', async () => {
        montar();
        await cargada();

        const periodo = screen.getByLabelText('Período');
        await waitFor(() =>
            expect(
                within(periodo).getByRole('option', { name: /2\/2026 · Vigente/ })
            ).toBeInTheDocument()
        );
        expect(
            within(periodo).getByRole('option', { name: /1\/2026 · Cerrado/ })
        ).toBeInTheDocument();

        api.get.mockClear();
        fireEvent.change(periodo, { target: { value: '3' } });

        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith(
                '/oferta/asignaturas',
                expect.objectContaining({
                    params: expect.objectContaining({ periodo: '3' }),
                })
            )
        );
    });

    it('si la lista falla ofrece «Reintentar» (criterio 14)', async () => {
        simular({ 'GET /oferta/asignaturas': errorHttp(500) });
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar');

        simular();
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

        expect(await screen.findByText('Introduccion a la Programacion')).toBeInTheDocument();
    });

    it('un rol sin permiso ve «Sin permiso» y no consulta (criterio 16)', () => {
        renderConSesion(
            <RutaProtegida ruta="/oferta">
                <Oferta />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Docente'), ruta: '/oferta' }
        );

        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalled();
    });

    it('los filtros se reacomodan en dos columnas en móvil (criterio 17)', async () => {
        montar();
        await cargada();

        const filtros = screen.getByLabelText('Período').closest('label').parentElement;
        expect(filtros.className).toMatch(/grid-cols-2/);
        expect(filtros.className).toMatch(/lg:grid-cols-5/);
    });
});

import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Periodo from './Periodo';
import { api } from '../../api/cliente';
import {
    errorHttp,
    renderConSesion,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
} from '../../test/apoyo';

vi.mock('../../api/cliente');

const PERIODOS = [
    {
        id: 1,
        codigo: '2/2026',
        tipo: 'Semestre 2',
        fecha_inicio: '2026-08-10',
        fecha_fin: '2026-12-26',
        estado: 'Vigente',
        nota: '36 carreras · 2.844 grupos',
    },
    {
        id: 2,
        codigo: '0/2026',
        tipo: 'Anual',
        fecha_inicio: '2026-02-02',
        fecha_fin: '2026-12-19',
        estado: 'Vigente',
        nota: '5 carreras · 325 grupos',
    },
    {
        id: 4,
        codigo: '4/2026',
        tipo: 'Invierno',
        fecha_inicio: null,
        fecha_fin: null,
        estado: 'Sin fechas',
        nota: 'Sin oferta importada',
    },
    {
        id: 5,
        codigo: '1/2027',
        tipo: 'Semestre 1',
        fecha_inicio: '2027-02-22',
        fecha_fin: '2027-07-03',
        estado: 'Próximo',
        nota: 'Sin oferta importada',
    },
];

const CERRADOS = [
    {
        id: 3,
        codigo: '1/2026',
        tipo: 'Semestre 1',
        fecha_inicio: '2026-02-23',
        fecha_fin: '2026-07-04',
        estado: 'Cerrado',
        nota: 'Sin oferta importada',
    },
    {
        id: 6,
        codigo: '4/2025',
        tipo: 'Invierno',
        fecha_inicio: '2025-07-09',
        fecha_fin: '2025-07-31',
        estado: 'Cerrado',
        nota: 'Sin oferta importada',
    },
];

const facultad = (sigla, nombre, cifras, importacion) => ({
    sigla,
    nombre,
    carreras: cifras[0],
    grupos: cifras[1],
    aulas: cifras[2],
    importacion: { estado: 'sin', fecha: null, error: null, ...importacion },
});

function resumenDe(cambios = {}) {
    return {
        hoy: '2026-10-09',
        periodo: { codigo: '2/2026', estado: 'Vigente' },
        ventana: { nombre: 'Primeros parciales', desde: '2026-10-12', hasta: '2026-10-31' },
        pendientes: {
            docentes_sin_cuenta: { valor: 632, de: 847 },
            grupos_sin_lista: { valor: 3137, de: 3169 },
        },
        facultades: [
            facultad('FCyT', 'Ciencias y Tecnología', [20, 1100, 115], {
                estado: 'importada',
                fecha: '2026-08-03',
            }),
            facultad('FCE', 'Ciencias Económicas', [5, 1007, 85], { estado: 'importando' }),
            facultad('FHCE', 'Humanidades y Ciencias de la Educación', [0, 0, 0]),
            facultad('FACH', 'Arquitectura y Ciencias del Hábitat', [6, 383, 33], {
                estado: 'fallo',
                error: 'No se pudo leer la fuente.',
            }),
        ],
        ...cambios,
    };
}

const paginaDe = ({ params }) =>
    params?.pagina === 2
        ? { data: CERRADOS, meta: { total: 6, pagina: 2, por_pagina: 4 } }
        : { data: PERIODOS, meta: { total: 6, pagina: 1, por_pagina: 4 } };

function simular(rutas = {}) {
    return simularApi(api, {
        'GET /periodos': paginaDe,
        'GET /periodos/resumen': resumenDe(),
        ...rutas,
    });
}

const montar = (usuario = usuarioDePrueba('Administrador')) =>
    renderConSesion(<Periodo />, { usuario, ruta: '/periodo' });

const fila = (texto) => screen.getByText(texto).closest('li');

async function cargada() {
    await screen.findByText('2/2026');
    await screen.findByText('Ciencias y Tecnología');
}

beforeEach(() => {
    simular();
});

describe('Periodo', () => {
    it('muestra la fecha de hoy, el período principal y la ventana de exámenes (criterio 1)', async () => {
        montar();
        await cargada();

        expect(screen.getByRole('heading', { name: 'Período académico' })).toBeInTheDocument();
        expect(
            screen.getByText('Hoy: 9 oct 2026 · Primeros parciales 12 – 31 oct')
        ).toBeInTheDocument();
        expect(screen.getByText('2/2026 · Vigente')).toBeInTheDocument();
    });

    it('lista los períodos con código, tipo, fechas y estado, de a cuatro (criterios 1 y 7)', async () => {
        montar();
        await cargada();

        expect(api.get).toHaveBeenCalledWith('/periodos', {
            params: { pagina: 1, por_pagina: 4 },
        });
        const vigente = fila('2/2026');
        expect(within(vigente).getByText('Semestre 2 · 10 ago – 26 dic 2026')).toBeInTheDocument();
        expect(within(vigente).getByText('36 carreras · 2.844 grupos')).toBeInTheDocument();
        expect(within(vigente).getByText('Vigente')).toBeInTheDocument();
        expect(within(fila('1/2027')).getByText('Próximo')).toBeInTheDocument();
        expect(screen.getByText('1–4 de 6 períodos')).toBeInTheDocument();
    });

    it('avanza a los períodos cerrados de la página siguiente (criterio 7)', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));

        const cerrado = (await screen.findByText('1/2026')).closest('li');
        expect(within(cerrado).getByText('Cerrado')).toBeInTheDocument();
        expect(within(cerrado).getByText('Semestre 1 · 23 feb – 4 jul 2026')).toBeInTheDocument();
        expect(within(fila('4/2025')).getByText('Invierno · 9 – 31 jul 2025')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/periodos', {
            params: { pagina: 2, por_pagina: 4 },
        });
        expect(screen.getByText('5–6 de 6 períodos')).toBeInTheDocument();
    });

    it('solo el período «Sin fechas» ofrece «Ajustar» (criterio 3)', async () => {
        montar();
        await cargada();

        const sinFechas = fila('4/2026');
        expect(within(sinFechas).getByText('Sin fechas')).toBeInTheDocument();
        expect(within(sinFechas).getByText('Invierno')).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: /^Ajustar/ })).toHaveLength(1);
        expect(within(sinFechas).getByRole('button', { name: 'Ajustar 4/2026' })).toBeVisible();
    });

    it('guarda el ajuste de fechas y vuelve a leer la lista (criterio 4)', async () => {
        simular({
            'PUT /periodos/*': { data: { ...PERIODOS[2], estado: 'Cerrado' } },
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Ajustar 4/2026' }));
        const dialogo = screen.getByRole('dialog', { name: 'Fechas del período 4/2026' });
        fireEvent.change(within(dialogo).getByLabelText(/Inicio/), {
            target: { value: '2026-07-09' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Fin/), {
            target: { value: '2026-07-31' },
        });
        api.get.mockClear();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(api.put).toHaveBeenCalledWith('/periodos/4', {
            fecha_inicio: '2026-07-09',
            fecha_fin: '2026-07-31',
        });
        expect(screen.getByText('Fechas guardadas')).toBeInTheDocument();
        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/periodos', {
                params: { pagina: 1, por_pagina: 4 },
            })
        );
        expect(api.get).toHaveBeenCalledWith('/periodos/resumen', { params: {} });
    });

    it('rechaza en el campo un fin que no es posterior al inicio, sin enviar (criterio 5)', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Ajustar 4/2026' }));
        const dialogo = screen.getByRole('dialog');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));
        expect(within(dialogo).getAllByText('Obligatorio')).toHaveLength(2);

        fireEvent.change(within(dialogo).getByLabelText(/Inicio/), {
            target: { value: '2026-07-09' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Fin/), {
            target: { value: '2026-07-09' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(within(dialogo).getByText('Debe ser posterior al inicio')).toBeInTheDocument();
        expect(within(dialogo).getByLabelText(/Fin/)).toHaveAttribute('aria-invalid', 'true');
        expect(within(dialogo).getByLabelText(/Inicio/)).not.toHaveAttribute('aria-invalid');
        expect(api.put).not.toHaveBeenCalled();
    });

    it('señala en el campo el rechazo del servidor y deja el diálogo abierto (criterio 5)', async () => {
        simular({
            'PUT /periodos/*': errorHttp(422, {
                message: 'La fecha de fin debe ser posterior a la fecha de inicio.',
                errors: {
                    fecha_fin: ['La fecha de fin debe ser posterior a la fecha de inicio.'],
                },
            }),
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Ajustar 4/2026' }));
        const dialogo = screen.getByRole('dialog');
        fireEvent.change(within(dialogo).getByLabelText(/Inicio/), {
            target: { value: '2026-07-09' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Fin/), {
            target: { value: '2026-07-31' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(
            await within(dialogo).findByText(
                'La fecha de fin debe ser posterior a la fecha de inicio.'
            )
        ).toBeInTheDocument();
        expect(within(dialogo).getByLabelText(/Fin/)).toHaveAttribute('aria-invalid', 'true');
        expect(within(dialogo).getByRole('button', { name: 'Guardar' })).toBeEnabled();
    });

    it('«Detectar» pide la detección y vuelve a leer períodos y resumen (criterio 6)', async () => {
        const detectar = respuestaDiferida();
        simular({ 'POST /periodos/detectar': () => detectar.promesa });
        montar();
        await cargada();

        api.get.mockClear();
        fireEvent.click(screen.getByRole('button', { name: 'Detectar' }));
        expect(screen.getByRole('button', { name: 'Detectando' })).toBeDisabled();

        detectar.resolver({ creados: 1, actualizados: 3 });

        expect(await screen.findByText('1 nuevos · 3 actualizados')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/periodos/detectar');
        await waitFor(() => expect(screen.getByRole('button', { name: 'Detectar' })).toBeEnabled());
        expect(api.get).toHaveBeenCalledWith('/periodos', {
            params: { pagina: 1, por_pagina: 4 },
        });
        expect(api.get).toHaveBeenCalledWith('/periodos/resumen', { params: {} });
    });

    it('muestra una fila por facultad con sus cifras y su estado (criterio 8)', async () => {
        montar();
        await cargada();

        const fcyt = fila('Ciencias y Tecnología');
        expect(
            within(fcyt).getByText('20 carreras · 1.100 grupos · 115 aulas')
        ).toBeInTheDocument();
        expect(within(fcyt).getByText('Importada · 3 ago 2026')).toBeInTheDocument();
        expect(within(fila('Ciencias Económicas')).getByText('Importando')).toBeInTheDocument();
        expect(
            within(fila('Humanidades y Ciencias de la Educación')).getByText('Sin importar')
        ).toBeInTheDocument();
        const fach = fila('Arquitectura y Ciencias del Hábitat');
        expect(within(fach).getByText('Falló')).toBeInTheDocument();
        expect(within(fach).getByText('No se pudo leer la fuente.')).toBeInTheDocument();
        expect(screen.getByText('1 de 4 importadas')).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: /^(Importar|Reintentar) / })).toHaveLength(4);
    });

    it('al importar figura «Importando» con el botón deshabilitado y luego «Importada» con las cifras nuevas (criterios 9 y 10)', async () => {
        const importacion = respuestaDiferida();
        simular({ 'POST /oferta/importaciones': () => importacion.promesa });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar FHCE' }));

        const fhce = fila('Humanidades y Ciencias de la Educación');
        expect(api.post).toHaveBeenCalledWith('/oferta/importaciones', { facultad: 'fhce' });
        expect(within(fhce).getByRole('button', { name: 'Importar FHCE' })).toBeDisabled();
        expect(within(fhce).getAllByText('Importando')).toHaveLength(2);
        expect(screen.getByRole('button', { name: 'Importar FCyT' })).toBeEnabled();

        const nuevo = resumenDe();
        nuevo.facultades[2] = facultad(
            'FHCE',
            'Humanidades y Ciencias de la Educación',
            [10, 679, 48],
            { estado: 'importada', fecha: '2026-10-09' }
        );
        simular({ 'GET /periodos/resumen': nuevo });
        importacion.resolver({ data: { estado: 'importada', fecha: '2026-10-09', resumen: {} } });

        expect(await within(fhce).findByText('Importada · 9 oct 2026')).toBeInTheDocument();
        expect(within(fhce).getByText('10 carreras · 679 grupos · 48 aulas')).toBeInTheDocument();
        expect(within(fhce).getByRole('button', { name: 'Importar FHCE' })).toBeEnabled();
        expect(screen.getByText('2 de 4 importadas')).toBeInTheDocument();
    });

    it('si la importación falla queda «Falló» con el motivo y «Reintentar»; al reintentar queda «Importada» (criterio 11)', async () => {
        simular({
            'POST /oferta/importaciones': errorHttp(422, {
                message: 'No se pudo importar FCyT.',
                data: { estado: 'fallo', error: 'La fuente no tiene grupos.' },
            }),
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar FCyT' }));

        const fcyt = fila('Ciencias y Tecnología');
        expect(await within(fcyt).findByText('Falló')).toBeInTheDocument();
        expect(within(fcyt).getByText('La fuente no tiene grupos.')).toBeInTheDocument();
        const reintentar = within(fcyt).getByRole('button', { name: 'Reintentar FCyT' });
        expect(reintentar).toBeEnabled();

        simular({
            'POST /oferta/importaciones': {
                data: { estado: 'importada', fecha: '2026-10-09', resumen: {} },
            },
        });
        fireEvent.click(reintentar);

        expect(await within(fcyt).findByText('Importada · 3 ago 2026')).toBeInTheDocument();
        expect(within(fcyt).queryByText('La fuente no tiene grupos.')).not.toBeInTheDocument();
        expect(within(fcyt).getByRole('button', { name: 'Importar FCyT' })).toBeEnabled();
    });

    it('avisa cuando el servidor rechaza un segundo pedido de la misma facultad (criterio 10)', async () => {
        simular({
            'POST /oferta/importaciones': errorHttp(409, {
                message: 'Ya hay una importación de esa facultad en curso.',
                codigo: 'IMPORTACION_EN_CURSO',
            }),
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar FCE' }));

        expect(
            await screen.findByText('Ya hay una importación de esa facultad en curso.')
        ).toBeInTheDocument();
        const fce = fila('Ciencias Económicas');
        await waitFor(() =>
            expect(within(fce).getByRole('button', { name: 'Importar FCE' })).toBeEnabled()
        );
        expect(within(fce).queryByText('Falló')).not.toBeInTheDocument();
    });

    it('indica los pendientes sobre el total y enlaza a Docentes y al Padrón (criterio 13)', async () => {
        montar();
        await cargada();

        const docentes = screen.getByRole('link', { name: /Docentes sin cuenta/ });
        expect(docentes).toHaveAttribute('href', '/docentes');
        expect(within(docentes).getByText('632')).toBeInTheDocument();
        expect(within(docentes).getByText('de 847')).toBeInTheDocument();
        const grupos = screen.getByRole('link', { name: /Grupos sin lista de inscritos/ });
        expect(grupos).toHaveAttribute('href', '/estudiantes');
        expect(within(grupos).getByText('3.137')).toBeInTheDocument();
        expect(within(grupos).getByText('de 3.169')).toBeInTheDocument();
    });

    it('no enlaza un pendiente a una vista para la que la cuenta no tiene permiso', async () => {
        montar(usuarioDePrueba('Auxiliar', { permisos: ['periodo_oferta', 'aulas_docentes'] }));
        await cargada();

        expect(screen.getByRole('link', { name: /Docentes sin cuenta/ })).toBeInTheDocument();
        expect(screen.getByText('Grupos sin lista de inscritos')).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Grupos sin lista/ })).not.toBeInTheDocument();
    });

    it('sin nada por resolver dice «Sin pendientes»', async () => {
        simular({
            'GET /periodos/resumen': resumenDe({
                periodo: null,
                ventana: null,
                pendientes: {
                    docentes_sin_cuenta: { valor: 0, de: 847 },
                    grupos_sin_lista: { valor: 0, de: 3169 },
                },
            }),
        });
        montar();
        await cargada();

        expect(screen.getByText('Sin pendientes')).toBeInTheDocument();
        expect(screen.getByText('Sin período vigente')).toBeInTheDocument();
        expect(screen.getByText('Hoy: 9 oct 2026')).toBeInTheDocument();
    });

    it('si la lectura falla ofrece «Reintentar» en cada bloque', async () => {
        simular({
            'GET /periodos': errorHttp(500),
            'GET /periodos/resumen': errorHttp(500),
        });
        montar();

        expect(await screen.findAllByText('No se pudo cargar')).toHaveLength(3);

        simular();
        fireEvent.click(screen.getAllByRole('button', { name: 'Reintentar' })[0]);

        expect(await screen.findByText('2/2026')).toBeInTheDocument();
    });

    it('las listas y las acciones se apilan en móvil, sin anchos fijos (criterio 16)', async () => {
        montar();
        await cargada();

        const periodo = fila('2/2026');
        expect(periodo.className).toContain('flex-wrap');
        const fcyt = fila('Ciencias y Tecnología');
        expect(fcyt.className).toContain('grid');
        expect(fcyt.className).toMatch(/md:grid-cols-/);
        expect(fcyt.className).not.toMatch(/(^| )grid-cols-/);
        const boton = within(fcyt).getByRole('button', { name: 'Importar FCyT' });
        expect(boton.className).toContain('min-h-11');
        expect(boton.parentElement.className).toContain('flex-wrap');
    });
});

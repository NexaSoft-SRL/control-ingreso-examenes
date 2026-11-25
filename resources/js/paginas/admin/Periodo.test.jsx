import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
        calendario: {
            primeros_parciales: ['2026-10-12', '2026-11-20'],
            segundos_parciales: ['2026-12-07', '2026-12-17'],
            finales_y_segunda_instancia: ['2026-12-19', '2026-12-24'],
            examenes_de_mesa: null,
        },
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

        expect(screen.getByRole('heading', { name: 'Período' })).toBeInTheDocument();
        expect(
            screen.getByText('Hoy: 9 oct 2026 · Primeros parciales 12 – 31 oct')
        ).toBeInTheDocument();
        // Con período vigente la cabecera no lleva insignia: la lista ya lo muestra.
        expect(screen.queryByText('2/2026 · Vigente')).not.toBeInTheDocument();
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

    it('los semestres vigentes y próximos ofrecen el calendario académico; el anual, los cerrados y los sin fechas, no', async () => {
        montar();
        await cargada();

        expect(screen.getAllByRole('button', { name: /^Calendario académico/ })).toHaveLength(2);
        expect(
            within(fila('4/2026')).queryByRole('button', { name: /^Calendario académico/ })
        ).toBeNull();
    });

    describe('calendario académico', () => {
        const AJUSTADO = {
            ...PERIODOS[0],
            calendario_ajustado: true,
            calendario_ajustado_por: 'Administración Académica',
            calendario_ajustado_en: '2026-11-24T10:30:00-04:00',
        };
        const conPeriodo0 = (periodo) => ({
            'GET /periodos': () => ({
                data: [periodo, ...PERIODOS.slice(1)],
                meta: { total: 6, pagina: 1, por_pagina: 4 },
            }),
        });

        async function abrir(periodoUno = PERIODOS[0], rutas = {}) {
            simular({ ...conPeriodo0(periodoUno), ...rutas });
            montar();
            await cargada();
            fireEvent.click(screen.getByRole('button', { name: 'Calendario académico 2/2026' }));
            return screen.getByRole('dialog', { name: 'Calendario académico 2/2026' });
        }

        beforeEach(() => {
            vi.useFakeTimers({ toFake: ['Date'] });
            vi.setSystemTime(new Date('2026-12-10T09:00:00'));
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        it('se abre para leer, sin campos que editar, con el origen y el estado de cada ventana', async () => {
            const dialogo = await abrir();

            expect(within(dialogo).getByText('Según la universidad')).toBeInTheDocument();
            expect(dialogo.querySelector('input')).toBeNull();
            const ventanas = within(dialogo)
                .getAllByRole('listitem')
                .map((li) => li.textContent);
            expect(ventanas).toEqual([
                'Primeros parciales12 oct – 20 nov 2026Cerrada',
                'Segundos parciales7 dic – 17 dic 2026Abierta ahora',
                'Finales y segunda instancia19 dic – 24 dic 2026Próxima',
                'Exámenes de mesa—Sin fechas',
            ]);
            expect(
                within(dialogo).queryByRole('button', { name: /universidad|Restablecer/ })
            ).toBeNull();
        });

        it('ya ajustado, la lectura no ofrece volver: «Restablecer» está dentro del ajuste', async () => {
            const dialogo = await abrir(AJUSTADO);

            expect(
                within(dialogo).queryByRole('button', { name: /universidad|Restablecer/ })
            ).toBeNull();
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));

            const ajuste = screen.getByRole('dialog', { name: 'Ajustar calendario 2/2026' });
            expect(within(ajuste).getByRole('button', { name: 'Restablecer' })).toBeInTheDocument();
        });

        it('sin ajustes, el formulario no ofrece restablecer', async () => {
            const dialogo = await abrir();

            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            fireEvent.click(screen.getByRole('button', { name: 'Sí, ajustar' }));

            const ajuste = screen.getByRole('dialog', { name: 'Ajustar calendario 2/2026' });
            expect(within(ajuste).queryByRole('button', { name: 'Restablecer' })).toBeNull();
        });

        it('el período anual no tiene calendario de exámenes: no muestra el botón', async () => {
            montar();
            await cargada();

            expect(
                screen.queryByRole('button', { name: 'Calendario académico 0/2026' })
            ).not.toBeInTheDocument();
            expect(within(fila('0/2026')).getByText('No disponible')).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Calendario académico 2/2026' })
            ).toBeEnabled();
        });

        it('un período cerrado se consulta, pero ya no se ajusta', async () => {
            simular(conPeriodo0(CERRADOS[0]));
            montar();

            fireEvent.click(
                await screen.findByRole('button', { name: 'Calendario académico 1/2026' })
            );
            const dialogo = screen.getByRole('dialog', { name: 'Calendario académico 1/2026' });

            expect(
                within(dialogo).getByText('El período cerró: el calendario solo se consulta.')
            ).toBeInTheDocument();
            expect(within(dialogo).queryByRole('button', { name: 'Ajustar fechas' })).toBeNull();
            expect(within(dialogo).getAllByRole('button', { name: 'Cerrar' })).not.toHaveLength(0);
        });

        it('ajustar pide confirmar y solo después muestra el formulario', async () => {
            const dialogo = await abrir();

            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            expect(
                within(dialogo).getByText(
                    'Estas fechas vienen del calendario de la universidad. ¿Ajustarlas solo en este sistema?'
                )
            ).toBeInTheDocument();
            expect(dialogo.querySelector('input')).toBeNull();

            fireEvent.click(within(dialogo).getByRole('button', { name: 'Cancelar' }));
            expect(within(dialogo).getByText('Según la universidad')).toBeInTheDocument();
            expect(api.put).not.toHaveBeenCalled();

            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Sí, ajustar' }));
            expect(
                screen.getByRole('dialog', { name: 'Ajustar calendario 2/2026' })
            ).toBeInTheDocument();
        });

        it('el administrador ajusta una ventana y la guarda', async () => {
            const dialogo = await abrir(PERIODOS[0], {
                'PUT /periodos/*/calendario': { data: PERIODOS[0] },
            });
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Sí, ajustar' }));

            const formulario = screen.getByRole('dialog', { name: 'Ajustar calendario 2/2026' });
            const segundos = within(
                within(formulario).getByText('Segundos parciales').closest('fieldset')
            );
            expect(segundos.getByLabelText('Desde')).toHaveValue('2026-12-07');

            fireEvent.change(segundos.getByLabelText('Desde'), { target: { value: '2026-11-23' } });
            api.get.mockClear();
            fireEvent.click(within(formulario).getByRole('button', { name: 'Guardar ajuste' }));

            await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
            expect(api.put).toHaveBeenCalledWith('/periodos/1/calendario', {
                ventanas: {
                    primeros_parciales: ['2026-10-12', '2026-11-20'],
                    segundos_parciales: ['2026-11-23', '2026-12-17'],
                    finales_y_segunda_instancia: ['2026-12-19', '2026-12-24'],
                },
            });
            expect(screen.getByText('Calendario ajustado')).toBeInTheDocument();
            await waitFor(() =>
                expect(api.get).toHaveBeenCalledWith('/periodos/resumen', { params: {} })
            );
        });

        it('el formulario señala en su campo una ventana que termina antes de empezar, sin enviar', async () => {
            const dialogo = await abrir();
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Sí, ajustar' }));

            const formulario = screen.getByRole('dialog');
            const mesa = within(
                within(formulario).getByText('Exámenes de mesa').closest('fieldset')
            );
            fireEvent.change(mesa.getByLabelText('Desde'), { target: { value: '2026-12-20' } });
            fireEvent.change(mesa.getByLabelText('Hasta'), { target: { value: '2026-12-10' } });
            fireEvent.click(within(formulario).getByRole('button', { name: 'Guardar ajuste' }));

            expect(within(formulario).getByText('Debe ser después de Desde')).toBeInTheDocument();
            expect(api.put).not.toHaveBeenCalled();
        });

        it('el formulario va en orden de fechas y no admite fechas fuera del período', async () => {
            const dialogo = await abrir();
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            fireEvent.click(within(dialogo).getByRole('button', { name: 'Sí, ajustar' }));

            const formulario = screen.getByRole('dialog');
            expect([...formulario.querySelectorAll('legend')].map((l) => l.textContent)).toEqual([
                'Primeros parciales',
                'Segundos parciales',
                'Finales y segunda instancia',
                'Exámenes de mesa',
            ]);
            const primeros = within(
                within(formulario).getByText('Primeros parciales').closest('fieldset')
            );
            expect(primeros.getByLabelText('Desde')).toHaveAttribute('min', '2026-08-10');
            expect(primeros.getByLabelText('Hasta')).toHaveAttribute('max', '2026-12-26');

            fireEvent.change(primeros.getByLabelText('Hasta'), { target: { value: '2027-01-15' } });
            fireEvent.click(within(formulario).getByRole('button', { name: 'Guardar ajuste' }));

            expect(within(formulario).getByText('Fuera del período')).toBeInTheDocument();
            expect(api.put).not.toHaveBeenCalled();
        });

        it('si ya está ajustado dice quién y cuándo, y ajustar va directo al formulario', async () => {
            const dialogo = await abrir(AJUSTADO);

            expect(within(dialogo).getByText('Ajustado por administración')).toBeInTheDocument();
            expect(
                within(dialogo).getByText('Administración Académica · 24 nov 2026, 10:30')
            ).toBeInTheDocument();

            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            expect(
                screen.getByRole('dialog', { name: 'Ajustar calendario 2/2026' })
            ).toBeInTheDocument();
        });

        it('restablece el calendario de la universidad solo tras confirmar', async () => {
            const dialogo = await abrir(AJUSTADO, {
                'DELETE /periodos/*/calendario': { data: PERIODOS[0] },
            });

            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            const ajuste = screen.getByRole('dialog', { name: 'Ajustar calendario 2/2026' });
            fireEvent.click(within(ajuste).getByRole('button', { name: 'Restablecer' }));
            expect(within(ajuste).getByText('¿Restablecer el calendario?')).toBeInTheDocument();
            expect(api.delete).not.toHaveBeenCalled();

            api.get.mockClear();
            fireEvent.click(within(ajuste).getByRole('button', { name: 'Sí, restablecer' }));

            await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
            expect(api.delete).toHaveBeenCalledWith('/periodos/1/calendario');
            expect(screen.getByText('Calendario de la universidad')).toBeInTheDocument();
            await waitFor(() =>
                expect(api.get).toHaveBeenCalledWith('/periodos/resumen', { params: {} })
            );
        });

        it('si la universidad no publica calendario, lo dice y no cambia nada', async () => {
            const dialogo = await abrir(AJUSTADO, {
                'DELETE /periodos/*/calendario': () =>
                    Promise.reject(
                        errorHttp(409, {
                            message: 'La universidad no publica calendario para este período.',
                        })
                    ),
            });

            fireEvent.click(within(dialogo).getByRole('button', { name: 'Ajustar fechas' }));
            const ajuste = screen.getByRole('dialog', { name: 'Ajustar calendario 2/2026' });
            fireEvent.click(within(ajuste).getByRole('button', { name: 'Restablecer' }));
            fireEvent.click(within(ajuste).getByRole('button', { name: 'Sí, restablecer' }));

            expect(
                await within(ajuste).findByText(
                    'La universidad no publica calendario para este período.'
                )
            ).toBeInTheDocument();
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });
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
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar fechas' }));

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
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar fechas' }));
        expect(within(dialogo).getAllByText('Obligatorio')).toHaveLength(2);

        fireEvent.change(within(dialogo).getByLabelText(/Inicio/), {
            target: { value: '2026-07-09' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Fin/), {
            target: { value: '2026-07-09' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar fechas' }));

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
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar fechas' }));

        expect(
            await within(dialogo).findByText(
                'La fecha de fin debe ser posterior a la fecha de inicio.'
            )
        ).toBeInTheDocument();
        expect(within(dialogo).getByLabelText(/Fin/)).toHaveAttribute('aria-invalid', 'true');
        expect(within(dialogo).getByRole('button', { name: 'Guardar fechas' })).toBeEnabled();
    });

    it('«Buscar períodos» pide la detección y vuelve a leer períodos y resumen (criterio 6)', async () => {
        const detectar = respuestaDiferida();
        simular({ 'POST /periodos/detectar': () => detectar.promesa });
        montar();
        await cargada();

        api.get.mockClear();
        fireEvent.click(screen.getByRole('button', { name: 'Buscar períodos' }));
        expect(screen.getByRole('button', { name: 'Buscando' })).toBeDisabled();

        detectar.resolver({ creados: 1, actualizados: 3 });

        // El resultado sale como aviso temporal.
        expect(
            await screen.findByText('Períodos al día · 1 nuevo · 3 actualizados')
        ).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/periodos/detectar');
        await waitFor(() =>
            expect(screen.getByRole('button', { name: 'Buscar períodos' })).toBeEnabled()
        );
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
        // Las filas son de lectura: se importa todo con un solo botón.
        expect(
            screen.queryAllByRole('button', {
                name: /^(Importar|Reintentar|Volver a importar) [A-Z]/,
            })
        ).toHaveLength(0);
        expect(screen.getByRole('button', { name: 'Importar todas' })).toBeEnabled();
    });

    // Resumen con las cuatro facultades ya importadas.
    const TODAS = () =>
        resumenDe({
            facultades: [
                facultad('FCyT', 'Ciencias y Tecnología', [20, 1100, 115], {
                    estado: 'importada',
                    fecha: '2026-10-09',
                }),
                facultad('FCE', 'Ciencias Económicas', [5, 1007, 85], {
                    estado: 'importada',
                    fecha: '2026-10-09',
                }),
                facultad('FHCE', 'Humanidades y Ciencias de la Educación', [10, 679, 48], {
                    estado: 'importada',
                    fecha: '2026-10-09',
                }),
                facultad('FACH', 'Arquitectura y Ciencias del Hábitat', [6, 383, 33], {
                    estado: 'importada',
                    fecha: '2026-10-09',
                }),
            ],
        });

    const llamadasA = (ruta) => api.get.mock.calls.filter(([r]) => r === ruta).length;
    const importadasEn = () => api.post.mock.calls.map(([, cuerpo]) => cuerpo.facultad);

    it('«Importar todas» importa una facultad tras otra, con el avance, y recarga una sola vez (criterios 9 y 10)', async () => {
        const pendientes = [0, 1, 2, 3].map(() => respuestaDiferida());
        let n = 0;
        simular({ 'POST /oferta/importaciones': () => pendientes[n++].promesa });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar todas' }));

        // Primera facultad en turno: su fila y el botón lo dicen.
        const avance = await screen.findByRole('button', { name: 'Importando 1 de 4' });
        expect(avance).toBeDisabled();
        expect(within(fila('Ciencias y Tecnología')).getByText('Importando')).toBeInTheDocument();
        expect(importadasEn()).toEqual(['fcyt']);

        pendientes[0].resolver({ data: { estado: 'importada' } });
        expect(await screen.findByRole('button', { name: 'Importando 2 de 4' })).toBeDisabled();
        expect(importadasEn()).toEqual(['fcyt', 'fce']);
        expect(within(fila('Ciencias y Tecnología')).getByText(/^Importada/)).toBeInTheDocument();

        pendientes[1].resolver({ data: { estado: 'importada' } });
        await screen.findByRole('button', { name: 'Importando 3 de 4' });
        pendientes[2].resolver({ data: { estado: 'importada' } });
        await screen.findByRole('button', { name: 'Importando 4 de 4' });

        api.get.mockClear();
        simular({
            'POST /oferta/importaciones': () => pendientes[3].promesa,
            'GET /periodos/resumen': TODAS(),
        });
        pendientes[3].resolver({ data: { estado: 'importada' } });

        expect(await screen.findByText('Oferta de las 4 facultades importada')).toBeInTheDocument();
        expect(llamadasA('/periodos/resumen')).toBe(1);
        expect(screen.getByText('4 de 4 importadas')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Volver a importar todas' })).toBeEnabled();
        expect(
            within(fila('Humanidades y Ciencias de la Educación')).getByText(
                'Importada · 9 oct 2026'
            )
        ).toBeInTheDocument();
    });

    it('una facultad que falla queda «Falló» con su motivo y las demás se importan (criterio 11)', async () => {
        simular({
            'POST /oferta/importaciones': ({ data }) =>
                data.facultad === 'fce'
                    ? errorHttp(422, {
                          message: 'No se pudo importar FCE.',
                          data: { estado: 'fallo', error: 'La fuente no tiene grupos.' },
                      })
                    : { data: { estado: 'importada' } },
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar todas' }));
        // Importa directo, sin confirmar.
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        expect(await screen.findByText('3 de 4 importadas · 1 con error')).toBeInTheDocument();
        // No se detuvo en la que falló: las cuatro se intentaron, en orden.
        expect(api.post.mock.calls.map(([, c]) => c.facultad)).toEqual([
            'fcyt',
            'fce',
            'fhce',
            'fach',
        ]);
        const fce = fila('Ciencias Económicas');
        expect(within(fce).getByText('Falló')).toBeInTheDocument();
        expect(within(fce).getByText('La fuente no tiene grupos.')).toBeInTheDocument();
        await waitFor(() =>
            expect(screen.getByRole('button', { name: 'Importar todas' })).toBeEnabled()
        );
    });

    it('para reintentar basta volver a pulsar «Importar todas»: lo importado queda al día', async () => {
        let fallar = true;
        simular({
            'POST /oferta/importaciones': ({ data }) =>
                fallar && data.facultad === 'fcyt'
                    ? errorHttp(422, {
                          message: 'No se pudo importar FCyT.',
                          data: { estado: 'fallo', error: 'La fuente no tiene grupos.' },
                      })
                    : { data: { estado: 'importada' } },
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar todas' }));
        const fcyt = fila('Ciencias y Tecnología');
        expect(await within(fcyt).findByText('Falló')).toBeInTheDocument();
        await screen.findByText('3 de 4 importadas · 1 con error');
        await waitFor(() =>
            expect(screen.getByRole('button', { name: 'Importar todas' })).toBeEnabled()
        );

        fallar = false;
        simular({
            'POST /oferta/importaciones': { data: { estado: 'importada' } },
            'GET /periodos/resumen': TODAS(),
        });
        fireEvent.click(screen.getByRole('button', { name: 'Importar todas' }));

        expect(await screen.findByText('Oferta de las 4 facultades importada')).toBeInTheDocument();
        expect(within(fcyt).queryByText('La fuente no tiene grupos.')).not.toBeInTheDocument();
        expect(within(fcyt).getByText('Importada · 9 oct 2026')).toBeInTheDocument();
    });

    it('si otra importación de una facultad sigue en curso, no es un error (criterio 10)', async () => {
        simular({
            'POST /oferta/importaciones': ({ data }) =>
                data.facultad === 'fce'
                    ? errorHttp(409, {
                          message: 'Ya hay una importación de esa facultad en curso.',
                          codigo: 'IMPORTACION_EN_CURSO',
                      })
                    : { data: { estado: 'importada' } },
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar todas' }));

        expect(await screen.findByText('3 de 4 importadas · 1 en curso')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledTimes(4);
        const fce = fila('Ciencias Económicas');
        expect(within(fce).queryByText('Falló')).not.toBeInTheDocument();
        await waitFor(() =>
            expect(screen.getByRole('button', { name: 'Importar todas' })).toBeEnabled()
        );
    });

    it('con las cuatro facultades importadas el botón dice «Volver a importar todas»', async () => {
        simular({ 'GET /periodos/resumen': TODAS() });
        montar();
        await cargada();

        expect(screen.getByText('4 de 4 importadas')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Volver a importar todas' })).toBeEnabled();
        expect(screen.queryByRole('button', { name: 'Importar todas' })).not.toBeInTheDocument();
    });

    it('indica los pendientes sobre el total y enlaza a Docentes y a la Oferta ya filtrados (criterio 13)', async () => {
        montar();
        await cargada();

        const docentes = screen.getByRole('link', { name: /Docentes sin cuenta/ });
        expect(docentes).toHaveAttribute('href', '/docentes?sin_cuenta=1');
        expect(within(docentes).getByText('632')).toBeInTheDocument();
        expect(within(docentes).getByText('de 847')).toBeInTheDocument();
        const grupos = screen.getByRole('link', { name: /Grupos sin lista de inscritos/ });
        expect(grupos).toHaveAttribute('href', '/oferta?sin_lista=1');
        expect(within(grupos).getByText('3.137')).toBeInTheDocument();
        expect(within(grupos).getByText('de 3.169')).toBeInTheDocument();
    });

    it('no enlaza un pendiente a una vista para la que la cuenta no tiene permiso', async () => {
        montar(usuarioDePrueba('Auxiliar', { permisos: ['aulas_docentes'] }));
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
        // Cada bloque tiene su propio «Reintentar»: se pulsan todos.
        screen
            .getAllByRole('button', { name: 'Reintentar' })
            .forEach((boton) => fireEvent.click(boton));

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
        // El botón único va en la cabecera, que baja de línea si no cabe.
        const boton = screen.getByRole('button', { name: 'Importar todas' });
        expect(boton.className).toContain('min-h-11');
        expect(boton.closest('header').className).toContain('flex-wrap');
    });

    it('si la fuente no cambió avisa que la oferta ya está al día', async () => {
        simular({
            'POST /oferta/importaciones': {
                message: 'La oferta de FCyT ya está al día.',
                data: { estado: 'importada', sin_cambios: true },
            },
        });
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Importar todas' }));
        // Que la fuente no haya cambiado cuenta como al día, no como error.
        expect(await screen.findByText('Oferta de las 4 facultades importada')).toBeInTheDocument();
    });
});

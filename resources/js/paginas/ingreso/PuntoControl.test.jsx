import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PuntoControl from './PuntoControl';
import { api } from '../../api/cliente';
import { renderConSesion, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

const examen = {
    id: 10,
    fecha: '2026-10-26',
    tipo: 'PRIMER_PARCIAL',
    tipo_etiqueta: 'Primer parcial',
    hora: '08:00',
    abierto: true,
    asignatura: { nombre: 'Introducción a la Programación' },
    aulas: [
        { id: 7, nombre: '691A', edificio: 'Edificio Académico 2', piso: '1.er piso' },
        { id: 8, nombre: '691B', edificio: 'Edificio Académico 2', piso: '2.º piso' },
    ],
};

const puerta = (aula = 7) => ({
    data: {
        aula: {
            id: aula,
            nombre: aula === 7 ? '691A' : '691B',
            edificio: 'Edificio Académico 2',
            piso: aula === 7 ? '1.er piso' : '2.º piso',
            edificio_id: 3,
        },
        fecha: '2026-10-26',
        hora_servidor: '08:40',
        abierta: true,
        examenes: [
            {
                id: 10,
                asignatura: 'Introducción a la Programación',
                tipo: 'Primer parcial',
                hora: '08:00',
                hora_fin: '09:30',
                abierto: true,
                ingresados: 3,
                asignados: 20,
                faltan: 17,
                normas: 'Sin celulares.',
                normas_marcadas: ['Llevar carnet'],
            },
        ],
    },
});

const fila = (id, extra = {}) => ({
    ingreso_id: id,
    examen_id: 10,
    estudiante_id: id,
    codigo: `20221${String(id).padStart(4, '0')}`,
    nombres: `Nombre${id}`,
    apellidos: `Apellido${id}`,
    registrado_en: '2026-10-26 08:39:20',
    hora: '08:39',
    expulsado: false,
    es_excepcion: false,
    ...extra,
});

let respuestas;

function montar(ruta = '/control') {
    return render(
        <MemoryRouter initialEntries={[ruta]}>
            <PuntoControl />
        </MemoryRouter>
    );
}

async function verificarCodigo(codigo = '202210098') {
    const abrir = await screen
        .findByRole('button', { name: 'Código SIS o C.I. manual' })
        .catch(() => null);
    if (abrir) fireEvent.click(abrir);
    const campo = await screen.findByRole('textbox', { name: 'Código SIS o C.I.' });
    fireEvent.change(campo, { target: { value: codigo } });
    fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
}

function rechazo(data, status = 409) {
    return Promise.reject({ response: { status, data: { message: 'x', data } } });
}

describe('PuntoControl HU-19', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        localStorage.clear();
        respuestas = {
            ingresos: { data: [fila(1)], meta: { total: 1 } },
            incidencias: { data: [] },
        };

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto')
                return Promise.resolve({ data: { data: [examen] } });
            if (/^\/aulas\/\d+\/punto-control$/.test(ruta)) {
                return Promise.resolve({ data: puerta(Number(ruta.split('/')[2])) });
            }
            if (ruta.endsWith('/punto-control/ingresos'))
                return Promise.resolve({ data: respuestas.ingresos });
            if (ruta.endsWith('/punto-control/incidencias')) {
                return Promise.resolve({ data: respuestas.incidencias });
            }
            if (ruta.endsWith('/incidencias-manuales'))
                return Promise.resolve({ data: { data: [] } });
            if (ruta === '/edificios') return Promise.reject(new Error('sin mapa'));
            return Promise.reject(new Error(`Ruta inesperada ${ruta}`));
        });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('BUG-22 y BUG-07: abre la primera aula con examen y muestra ubicación, exámenes y normas', async () => {
        montar();

        expect(await screen.findByText('Aula 691A')).toBeInTheDocument();
        expect(await screen.findByText('Edificio Académico 2 · 1.er piso')).toBeInTheDocument();

        const lista = await screen.findByRole('list', { name: 'Exámenes del aula' });
        expect(within(lista).getByText('Introducción a la Programación')).toBeInTheDocument();
        expect(within(lista).getByText(/Primer parcial · 08:00–09:30/)).toBeInTheDocument();
        expect(within(lista).getByText('3 ingresaron')).toBeInTheDocument();
        expect(within(lista).getByText('Faltan 17')).toBeInTheDocument();
        expect(screen.getByText(/3 ingresaron · Faltan 17/)).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Normas' }));
        expect(screen.getByText('Llevar carnet')).toBeInTheDocument();
        expect(screen.getByText('Sin celulares.')).toBeInTheDocument();

        // Sin párrafos explicativos ni tuteo (BUG-17).
        expect(screen.queryByText(/Identifica al estudiante/)).not.toBeInTheDocument();
        expect(screen.queryByText(/Selecciona el aula/)).not.toBeInTheDocument();
    });

    it('BUG-22: «Cambiar» lista las aulas con «Actual» y cambia de puerta', async () => {
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Cambiar' }));
        const dialogo = screen.getByRole('dialog', { name: 'Aula' });
        expect(within(dialogo).getByText('Actual')).toBeInTheDocument();

        fireEvent.click(within(dialogo).getByText('Aula 691B'));

        expect(await screen.findByText('Aula 691B')).toBeInTheDocument();
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/aulas/8/punto-control', expect.anything());
        });
    });

    it('respeta el aula que llega en ?aula= desde «En curso»', async () => {
        montar('/control?aula=8');

        expect(await screen.findByText('Aula 691B')).toBeInTheDocument();
    });

    it('BUG-19: verifica y registra en un paso, con nombre, código, examen y hora', async () => {
        api.post.mockResolvedValue({
            data: {
                data: {
                    veredicto: 'ADMITIDO',
                    admitido: true,
                    estudiante: { id: 5, codigo: '202210098', nombre: 'Vega Escóbar, Silvia' },
                    examen: {
                        id: 10,
                        asignatura: 'Introducción a la Programación',
                        tipo: 'Primer parcial',
                    },
                    dato: 'Registrado a las 08:39',
                },
            },
        });

        montar();
        await verificarCodigo();

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos', {
                identificador: '202210098',
            });
        });

        const veredicto = await screen.findByText('Ingreso admitido');
        const tarjeta = veredicto.closest('[role="status"]');
        expect(within(tarjeta).getByText('Vega Escóbar, Silvia')).toBeInTheDocument();
        expect(within(tarjeta).getByText('Código SIS 202210098')).toBeInTheDocument();
        expect(
            within(tarjeta).getByText('Introducción a la Programación · Primer parcial')
        ).toBeInTheDocument();
        expect(within(tarjeta).getByText('Registrado a las 08:39')).toBeInTheDocument();
        expect(within(tarjeta).queryByText('Situación excepcional')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        expect(
            await screen.findByRole('textbox', { name: 'Código SIS o C.I.' })
        ).toBeInTheDocument();
    });

    it('BUG-11: «Ya ingresó» con hora y aula, sin situación excepcional (N4)', async () => {
        api.post.mockImplementation(() =>
            rechazo({
                veredicto: 'YA_INGRESO',
                estudiante: { id: 5, codigo: '202210098', nombre: 'Vega Escóbar, Silvia' },
                dato: 'Ingresó a las 08:39 en el aula 691A',
                pie: 'Incidencia registrada',
            })
        );

        montar();
        await verificarCodigo();

        expect(await screen.findByText('Ya ingresó')).toBeInTheDocument();
        expect(screen.getByText('Ingresó a las 08:39 en el aula 691A')).toBeInTheDocument();
        expect(screen.getByText('Incidencia registrada')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Situación excepcional' })
        ).not.toBeInTheDocument();
    });

    it('HU-27: muestra la foto para comparar la cara, o «Sin foto»', async () => {
        api.post.mockImplementationOnce(() =>
            rechazo({
                veredicto: 'NO_HABILITADO',
                estudiante: {
                    id: 5,
                    codigo: '202210098',
                    nombre: 'Vega Escóbar, Silvia',
                    foto: 'data:image/jpeg;base64,AAAA',
                },
                dato: 'No subió su foto',
            })
        );

        montar();
        await verificarCodigo();

        expect(
            await screen.findByRole('img', { name: 'Foto de Vega Escóbar, Silvia' })
        ).toHaveAttribute('src', 'data:image/jpeg;base64,AAAA');
        expect(screen.getByText('No subió su foto')).toBeInTheDocument();

        api.post.mockResolvedValueOnce({
            data: {
                data: {
                    veredicto: 'ADMITIDO',
                    admitido: true,
                    estudiante: { id: 6, codigo: '202210099', nombre: 'Rojas Paz, Ana' },
                    dato: 'Registrado a las 08:40',
                },
            },
        });
        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        await verificarCodigo('202210099');

        expect(await screen.findByText('Rojas Paz, Ana')).toBeInTheDocument();
        expect(screen.getByText('Sin foto')).toBeInTheDocument();
    });

    it('BUG-14: distingue «No registrado» de «No es de esta aula»', async () => {
        api.post
            .mockImplementationOnce(() =>
                rechazo({
                    veredicto: 'NO_CORRESPONDE',
                    causa: 'NO_REGISTRADO',
                    estudiante: null,
                    dato: '99999999 · sin registro en el padrón',
                    pie: 'Intento registrado',
                })
            )
            .mockImplementationOnce(() =>
                rechazo({
                    veredicto: 'NO_CORRESPONDE',
                    causa: 'NO_INSCRITO',
                    estudiante: { id: 9, codigo: '202310308', nombre: 'Rojas, Ana' },
                    dato: 'No inscrito en los exámenes del aula 691A',
                    examen_correspondiente: {
                        texto: 'Su examen: Cálculo I · Primer parcial · 10:00',
                        aula: '617',
                    },
                    pie: 'Intento registrado',
                })
            );

        montar();
        await verificarCodigo('99999999');

        expect(await screen.findByText('No registrado')).toBeInTheDocument();
        expect(screen.getByText('Intento registrado')).toBeInTheDocument();
        // N4: también con el no inscrito o sin registro.
        expect(screen.getByRole('button', { name: 'Situación excepcional' })).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        await verificarCodigo('202310308');

        expect(await screen.findByText('No es de esta aula')).toBeInTheDocument();
        expect(
            screen.getByText('Su examen: Cálculo I · Primer parcial · 10:00 · Aula 617')
        ).toBeInTheDocument();
    });

    it('el aula asignada es una sugerencia y la llegada tarde se marca', async () => {
        api.post.mockResolvedValue({
            data: {
                data: {
                    veredicto: 'ADMITIDO',
                    admitido: true,
                    estudiante: { id: 5, codigo: '202110007', nombre: 'Paz Luis' },
                    dato: 'Registrado a las 08:39 · su aula sugerida era la 691B',
                    tarde: 39,
                    pie: 'Tarde · 39 min después del inicio',
                },
            },
        });
        respuestas.ingresos = { data: [fila(1, { tarde: 39 })], meta: { total: 1 } };

        montar();
        await verificarCodigo('5003748-1a');

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos', {
                identificador: '5003748-1A',
            });
        });
        expect(
            await screen.findByText('Registrado a las 08:39 · su aula sugerida era la 691B')
        ).toBeInTheDocument();
        expect(screen.getByText('Tarde · 39 min después del inicio')).toBeInTheDocument();
        expect(screen.getByText('Tarde · 39 min')).toBeInTheDocument();
    });

    it('N4: con el no habilitado no ofrece cambio de aula', async () => {
        api.post.mockImplementation(() =>
            rechazo({
                veredicto: 'NO_HABILITADO',
                estudiante: { id: 6, codigo: '202610014', nombre: 'Lima, Eva' },
                examen: {
                    id: 10,
                    asignatura: 'Introducción a la Programación',
                    tipo: 'Primer parcial',
                },
                dato: 'No entregó las prácticas 1 y 2.',
                pie: 'Registrado por Blanco Coca Leticia · 6 oct',
            })
        );

        montar();
        await verificarCodigo('202610014');

        expect(await screen.findByText('No entregó las prácticas 1 y 2.')).toBeInTheDocument();
        expect(screen.getByText('Registrado por Blanco Coca Leticia · 6 oct')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Situación excepcional' }));
        const opciones = within(screen.getByRole('combobox', { name: 'Tipo' }))
            .getAllByRole('option')
            .map((o) => o.textContent);
        expect(opciones).toEqual(['Problema de identificación', 'Estudiante no habilitado']);
    });

    it('BUG-20: al leer un QR verifica solo, con «Verificando…»', async () => {
        let resolver;
        api.post.mockImplementation(
            () =>
                new Promise((r) => {
                    resolver = r;
                })
        );
        vi.stubGlobal('isSecureContext', true);
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: { getUserMedia: vi.fn().mockResolvedValue({ getTracks: () => [] }) },
        });
        vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue();
        vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});
        const detect = vi.fn().mockResolvedValue([{ rawValue: 'SCIQR1:abc' }]);
        vi.stubGlobal(
            'BarcodeDetector',
            class {
                detect = detect;
            }
        );
        vi.stubGlobal('requestAnimationFrame', (fn) => {
            setTimeout(fn, 0);
            return 1;
        });
        vi.stubGlobal('cancelAnimationFrame', vi.fn());

        montar();
        fireEvent.click(await screen.findByRole('button', { name: 'Leer código QR' }));

        expect(await screen.findByText('Verificando…')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos', {
            tipo: 'qr',
            identificador: 'SCIQR1:abc',
        });

        resolver({
            data: {
                data: { veredicto: 'ADMITIDO', admitido: true, dato: 'Registrado a las 08:42' },
            },
        });
        expect(await screen.findByText('Registrado a las 08:42')).toBeInTheDocument();
        vi.unstubAllGlobals();
    });

    it('BUG-21: sin cámara deja la identificación manual a la vista', async () => {
        vi.stubGlobal('isSecureContext', false);

        montar();
        fireEvent.click(await screen.findByRole('button', { name: 'Leer código QR' }));

        expect(
            await screen.findByText('Cámara no disponible: requiere conexión segura (HTTPS)')
        ).toBeInTheDocument();
        expect(screen.getByRole('textbox', { name: 'Código SIS o C.I.' })).toBeInTheDocument();
        vi.unstubAllGlobals();
    });

    it('BUG-12 y BUG-13: recientes con nombre sin coma, hora corta y llegada tarde', async () => {
        respuestas.ingresos = { data: [fila(1, { tarde: 22 })], meta: { total: 1 } };
        montar();

        const lista = await screen.findByRole('list', { name: 'Ingresos' });
        expect(await within(lista).findByText('Apellido1 Nombre1')).toBeInTheDocument();
        expect(within(lista).getByText('08:39')).toBeInTheDocument();
        expect(within(lista).getByText('Tarde · 22 min')).toBeInTheDocument();
        expect(screen.queryByText(/2026-10-26 08:39:20/)).not.toBeInTheDocument();
    });

    it('BUG-23: «Ver los N» con búsqueda y «Sin resultados»', async () => {
        respuestas.ingresos = { data: [fila(1), fila(2)], meta: { total: 10 } };

        montar();
        fireEvent.click(await screen.findByRole('button', { name: 'Ver los 10' }));

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos', {
                params: { todos: 1 },
            });
        });

        respuestas.ingresos = { data: [], meta: { total: 10 } };
        fireEvent.change(screen.getByRole('textbox', { name: 'Buscar ingreso' }), {
            target: { value: 'zzz' },
        });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos', {
                params: { todos: 1, buscar: 'zzz' },
            });
        });
        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
    });

    it('BUG-23: avisa «Sin conexión» tras fallos seguidos del sondeo', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        montar();
        expect(await screen.findByText(/3 ingresaron · Faltan 17/)).toBeInTheDocument();
        expect(await screen.findByText('Apellido1 Nombre1')).toBeInTheDocument();

        api.get.mockImplementation(() => Promise.reject(new Error('Network Error')));
        for (let i = 0; i < 3; i += 1) {
            await act(async () => vi.advanceTimersByTimeAsync(10000));
        }

        expect(screen.getByText('Sin conexión')).toBeInTheDocument();
    });

    it('BUG-05 y N7: expulsa con motivo en un diálogo y marca «Expulsado» con su hora', async () => {
        api.post.mockResolvedValue({ data: { data: { expulsado: true, hora: '09:10' } } });

        montar();
        fireEvent.click(
            await screen.findByRole('button', { name: 'Registrar expulsión de Apellido1 Nombre1' })
        );

        const dialogo = screen.getByRole('dialog', { name: 'Expulsión' });
        const motivo = within(dialogo).getByRole('textbox', { name: /Motivo/ });
        fireEvent.change(motivo, { target: { value: 'abc' } });
        expect(within(dialogo).getByRole('button', { name: 'Registrar' })).toBeDisabled();

        respuestas.ingresos = {
            data: [fila(1, { expulsado: true, expulsado_a: '09:10' })],
            meta: { total: 1 },
        };
        fireEvent.change(motivo, { target: { value: 'Uso de celular.' } });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos/1/expulsion', {
                motivo: 'Uso de celular.',
            });
        });
        expect(await screen.findByText('Expulsado · 09:10')).toBeInTheDocument();
    });

    it('bloquea la operación cuando el servidor responde 403', async () => {
        api.post.mockRejectedValue({
            response: { status: 403, data: { message: 'Sin asignación en esta aula.' } },
        });

        montar();
        await verificarCodigo();

        expect((await screen.findAllByText('Sin asignación en esta aula')).length).toBeGreaterThan(
            0
        );
        expect(
            screen.queryByRole('textbox', { name: 'Código SIS o C.I.' })
        ).not.toBeInTheDocument();
    });

    it('valida el identificador antes de enviar', async () => {
        montar();
        await verificarCodigo('12');

        expect(await screen.findByText('Código SIS o C.I. no válido')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('sin aulas asignadas no opera', async () => {
        api.get.mockImplementation((ruta) =>
            ruta === '/punto-control/contexto'
                ? Promise.resolve({ data: { data: [] } })
                : Promise.reject(new Error('no'))
        );

        montar();

        expect(await screen.findByText('Sin aulas asignadas hoy')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Verificar' })).not.toBeInTheDocument();
    });

    it('con código de aula ve la misma puerta, sin expulsar ni excepción', async () => {
        api.post.mockImplementation(() =>
            rechazo({
                veredicto: 'NO_HABILITADO',
                estudiante: { id: 6, codigo: '202610014', nombre: 'Lima Eva' },
                dato: 'Sin revisar',
            })
        );

        render(
            <MemoryRouter initialEntries={['/acceso-aula?aula=7']}>
                <PuntoControl
                    acceso={{
                        examen_id: 10,
                        aula_id: 7,
                        aula_nombre: '691A',
                        nombre_colaborador: 'Rocha Ana',
                    }}
                />
            </MemoryRouter>
        );

        // Los exámenes, sus cifras y las normas, como con cuenta.
        const lista = await screen.findByRole('list', { name: 'Exámenes del aula' });
        expect(within(lista).getByText('Introducción a la Programación')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Incidencia' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Expulsar' })).not.toBeInTheDocument();

        await verificarCodigo();
        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos', {
                identificador: '202210098',
            });
        });
        expect(await screen.findByText('Sin revisar')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Situación excepcional' })
        ).not.toBeInTheDocument();
    });
    it('ofrece la lista del aula a quien la sigue y busca entre muchas aulas', async () => {
        const muchas = Array.from({ length: 8 }, (_, n) => ({
            id: 7 + n,
            nombre: `69${n}`,
            edificio: 'Edificio Académico 2',
            piso: '1.er piso',
        }));
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [{ ...examen, aulas: muchas }] } });
            }
            if (/^\/aulas\/\d+\/punto-control$/.test(ruta)) {
                return Promise.resolve({ data: puerta(7) });
            }
            if (ruta.endsWith('/punto-control/ingresos'))
                return Promise.resolve({ data: respuestas.ingresos });
            return Promise.reject(new Error(`Ruta inesperada ${ruta}`));
        });

        renderConSesion(<PuntoControl />, {
            usuario: usuarioDePrueba('Auxiliar'),
            ruta: '/punto-control',
        });

        expect(await screen.findByRole('link', { name: /Lista/ })).toHaveAttribute(
            'href',
            '/mi-aula?examen=10&aula=7'
        );

        fireEvent.click(screen.getByRole('button', { name: 'Cambiar' }));
        const dialogo = screen.getByRole('dialog', { name: 'Aula' });
        fireEvent.change(within(dialogo).getByRole('textbox', { name: 'Buscar aula' }), {
            target: { value: '697' },
        });
        expect(within(dialogo).getByText('Aula 697')).toBeInTheDocument();
        expect(within(dialogo).queryByText('Aula 691')).not.toBeInTheDocument();
    });
});

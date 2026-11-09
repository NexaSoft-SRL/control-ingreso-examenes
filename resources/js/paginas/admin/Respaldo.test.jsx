import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Respaldo, { tamanoLegible } from './Respaldo';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import RutaProtegida from '../../sesion/RutaProtegida';
import {
    errorHttp,
    renderConSesion,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
} from '../../test/apoyo';

vi.mock('../../api/cliente');
vi.mock('../../api/descargar', () => ({ default: vi.fn() }));

const MB = 1024 * 1024;

// 18 respaldos, el más reciente primero: el 3 falló, el 5 es puntual y el
// 17 es de un esquema anterior (el servidor no deja restaurarlo).
const RESPALDOS = Array.from({ length: 18 }, (_, i) => {
    const id = 18 - i;
    const dia = String(12 - i > 0 ? 12 - i : 30 + (12 - i)).padStart(2, '0');
    const mes = 12 - i > 0 ? '10' : '09';
    const fallido = id === 3;
    const puntual = id === 5 || id === 16;
    return {
        id,
        fecha: `2026-${mes}-${dia}T${puntual ? '18:20' : '03:00'}:00-04:00`,
        tipo: puntual ? 'Puntual' : 'Programado',
        tamano_bytes: fallido ? null : 48 * MB,
        estado: fallido ? 'fallido' : 'correcto',
        restaurado: false,
        archivo: fallido ? null : `respaldo-2026${mes}${dia}-${puntual ? '182000' : '030000'}.sql`,
    };
});

// Un servidor mínimo en memoria con el contrato de las rutas 14 a 17.
function servidor({ generar = null } = {}) {
    let filas = RESPALDOS.map((r) => ({ ...r }));

    const aplica = {
        programados: (r) => r.tipo === 'Programado',
        puntuales: (r) => r.tipo === 'Puntual',
        fallidos: (r) => r.estado === 'fallido',
    };

    const lista = ({ params = {} }) => {
        const filtradas = params.filtro ? filas.filter(aplica[params.filtro]) : filas;
        const pagina = params.pagina ?? 1;
        const porPagina = params.por_pagina ?? 15;
        const correctos = filas.filter((r) => r.estado === 'correcto');
        return {
            data: filtradas.slice((pagina - 1) * porPagina, pagina * porPagina),
            meta: {
                total: filtradas.length,
                pagina,
                por_pagina: porPagina,
                conteos: {
                    todos: filas.length,
                    programados: filas.filter(aplica.programados).length,
                    puntuales: filas.filter(aplica.puntuales).length,
                    fallidos: filas.filter(aplica.fallidos).length,
                },
                programacion: 'Diario, 03:00',
                ultimo: correctos[0]?.fecha ?? null,
            },
        };
    };

    const crear = () => {
        if (generar) return generar;
        const fila = {
            id: 19,
            fecha: '2026-10-12T10:15:00-04:00',
            tipo: 'Puntual',
            tamano_bytes: 49 * MB,
            estado: 'correcto',
            restaurado: false,
            archivo: 'respaldo-20261012-101500.sql',
        };
        filas = [fila, ...filas];
        return { data: fila, message: 'Respaldo generado.' };
    };

    const restaurar = ({ url }) => {
        const id = Number(url.split('/')[2]);
        const fila = filas.find((r) => r.id === id);
        if (!fila) return errorHttp(404, { message: 'Respaldo no encontrado.' });
        if (id === 17) {
            return errorHttp(409, {
                message:
                    'Ese respaldo es de una versión anterior del sistema y no se puede restaurar.',
                codigo: 'RESPALDO_INCOMPATIBLE',
            });
        }
        filas = filas.map((r) => ({ ...r, restaurado: r.id === id }));
        return { message: 'Restaurado al 11 oct 2026, 03:00.', tablas: 34 };
    };

    simularApi(api, {
        'GET /respaldos': lista,
        'POST /respaldos': crear,
        'POST /respaldos/*/restaurar': restaurar,
    });
}

const tabla = () => screen.getByRole('table', { name: 'Historial' });
const fila = (fecha) => within(tabla()).getByText(fecha).closest('tr');

async function montar(opciones = {}) {
    const resultado = renderConSesion(<Respaldo />, {
        usuario: usuarioDePrueba('Administrador'),
        ruta: '/respaldo',
        ...opciones,
    });
    await within(await screen.findByRole('table', { name: 'Historial' })).findByText(
        '12 oct 2026, 03:00'
    );
    return resultado;
}

beforeEach(() => {
    descargar.mockReset();
    servidor();
});

describe('Respaldo', () => {
    it('muestra la programación, el último respaldo y el historial de a 15 con fecha, tipo, tamaño y estado (criterio 2)', async () => {
        await montar();

        expect(screen.getByText('Diario, 03:00 · último: 12 oct 2026, 03:00')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/respaldos', {
            params: { pagina: 1, por_pagina: 15 },
        });
        expect(within(tabla()).getAllByRole('row')).toHaveLength(16);

        const primera = fila('12 oct 2026, 03:00');
        expect(within(primera).getByText('Programado')).toBeInTheDocument();
        expect(within(primera).getByText('48 MB')).toBeInTheDocument();
        expect(within(primera).getByText('Correcto')).toBeInTheDocument();
        expect(screen.getByText('1–15 de 18 respaldos')).toBeInTheDocument();
    });

    it('el filtro viaja al servidor, cada ficha lleva su conteo y un respaldo fallido no tiene acciones', async () => {
        await montar();

        const filtros = screen.getByRole('group', { name: 'Filtrar respaldos' });
        expect(
            within(filtros)
                .getAllByRole('button')
                .map((b) => b.textContent)
        ).toEqual(['Todos18', 'Programados16', 'Puntuales2', 'Fallidos1']);

        fireEvent.click(within(filtros).getByRole('button', { name: /Fallidos/ }));
        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/respaldos', {
                params: { filtro: 'fallidos', pagina: 1, por_pagina: 15 },
            })
        );
        await waitFor(() => expect(within(tabla()).getAllByRole('row')).toHaveLength(2));
        expect(within(tabla()).getByText('Fallido')).toBeInTheDocument();
        expect(within(tabla()).getByText('—')).toBeInTheDocument();
        expect(within(tabla()).queryByRole('button')).toBeNull();
    });

    it('la paginación pide la página siguiente al servidor', async () => {
        await montar();

        fireEvent.click(screen.getByRole('button', { name: /siguiente/i }));
        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/respaldos', {
                params: { pagina: 2, por_pagina: 15 },
            })
        );
        await waitFor(() => expect(within(tabla()).getAllByRole('row')).toHaveLength(4));
    });

    it('«Respaldar ahora» genera uno, lo deja primero y avisa (criterio 1)', async () => {
        const espera = respuestaDiferida();
        await montar();
        const crear = api.post.getMockImplementation();
        api.post.mockImplementationOnce(async (...args) => {
            await espera.promesa;
            return crear(...args);
        });

        fireEvent.click(screen.getByRole('button', { name: /Programados/ }));
        await waitFor(() => expect(within(tabla()).getAllByRole('row')).toHaveLength(16));
        fireEvent.click(screen.getByRole('button', { name: 'Respaldar ahora' }));
        expect(screen.getByRole('button', { name: 'Respaldando' })).toBeDisabled();
        espera.resolver();

        expect(await screen.findByText('Respaldo generado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/respaldos');
        // El puntual nuevo no cabe en «Programados»: se quita el filtro.
        const nueva = (await within(tabla()).findByText('12 oct 2026, 10:15')).closest('tr');
        expect(within(nueva).getByText('Puntual')).toBeInTheDocument();
        expect(within(nueva).getByText('49 MB')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Todos/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        expect(screen.getByText('Diario, 03:00 · último: 12 oct 2026, 10:15')).toBeInTheDocument();
    });

    it('si la generación falla avisa con el mensaje del servidor y vuelve a leer el historial', async () => {
        servidor({
            generar: errorHttp(500, { message: 'No se pudo generar el respaldo.', data: {} }),
        });
        await montar();

        fireEvent.click(screen.getByRole('button', { name: 'Respaldar ahora' }));

        expect(await screen.findByText('No se pudo generar el respaldo')).toBeInTheDocument();
        await waitFor(() => expect(api.get).toHaveBeenCalledTimes(2));
        expect(screen.getByRole('button', { name: 'Respaldar ahora' })).toBeEnabled();
    });

    it('«Descargar» baja el archivo del respaldo y avisa con su nombre (criterio 3)', async () => {
        descargar.mockResolvedValue('respaldo-20261011-030000.sql');
        await montar();

        fireEvent.click(
            within(tabla()).getByRole('button', {
                name: 'Descargar el respaldo del 11 oct 2026, 03:00',
            })
        );

        expect(descargar).toHaveBeenCalledWith('/respaldos/17/descargar', {
            nombre: 'respaldo-20261011-030000.sql',
        });
        expect(
            await screen.findByText('respaldo-20261011-030000.sql descargado')
        ).toBeInTheDocument();
    });

    it('si la descarga se rechaza muestra el motivo', async () => {
        descargar.mockRejectedValue(
            errorHttp(404, { message: 'El archivo de ese respaldo ya no está en disco.' })
        );
        await montar();

        fireEvent.click(
            within(tabla()).getByRole('button', {
                name: 'Descargar el respaldo del 12 oct 2026, 03:00',
            })
        );

        expect(
            await screen.findByText('El archivo de ese respaldo ya no está en disco')
        ).toBeInTheDocument();
    });

    it('«Restaurar» pide confirmación en la fila y se puede cancelar (criterio 4)', async () => {
        await montar();

        fireEvent.click(
            within(tabla()).getByRole('button', {
                name: 'Restaurar el respaldo del 12 oct 2026, 03:00',
            })
        );
        const confirmacion = within(tabla()).getByRole('group', {
            name: 'Restaurar el respaldo del 12 oct 2026, 03:00',
        });
        expect(
            within(confirmacion).getByText('Sobrescribe los datos actuales')
        ).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();

        fireEvent.click(within(confirmacion).getByRole('button', { name: 'Cancelar' }));
        expect(within(tabla()).queryByText('Sobrescribe los datos actuales')).toBeNull();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('al confirmar restaura, avisa la fecha y marca la fila como «Restaurado» (criterio 4)', async () => {
        await montar();

        fireEvent.click(
            within(tabla()).getByRole('button', {
                name: 'Restaurar el respaldo del 12 oct 2026, 03:00',
            })
        );
        fireEvent.click(
            within(
                within(tabla()).getByRole('group', {
                    name: 'Restaurar el respaldo del 12 oct 2026, 03:00',
                })
            ).getByRole('button', { name: 'Restaurar' })
        );

        expect(await screen.findByText('Restaurado al 12 oct 2026, 03:00')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/respaldos/18/restaurar');
        expect(
            await within(fila('12 oct 2026, 03:00')).findByText('Restaurado')
        ).toBeInTheDocument();
        expect(within(tabla()).queryByText('Sobrescribe los datos actuales')).toBeNull();
    });

    it('un respaldo incompatible se rechaza con aviso y la fila deja de ofrecer «Restaurar» (criterio 5)', async () => {
        await montar();

        fireEvent.click(
            within(tabla()).getByRole('button', {
                name: 'Restaurar el respaldo del 11 oct 2026, 03:00',
            })
        );
        fireEvent.click(
            within(
                within(tabla()).getByRole('group', {
                    name: 'Restaurar el respaldo del 11 oct 2026, 03:00',
                })
            ).getByRole('button', { name: 'Restaurar' })
        );

        expect(await screen.findByText('Respaldo incompatible')).toBeInTheDocument();
        const rechazada = fila('11 oct 2026, 03:00');
        expect(within(rechazada).getByText('Incompatible')).toBeInTheDocument();
        expect(within(rechazada).queryByRole('button', { name: /^Restaurar/ })).toBeNull();
        expect(within(rechazada).getByRole('button', { name: /^Descargar/ })).toBeInTheDocument();
        // No se volvió a leer: nada cambió en el servidor.
        expect(api.get).toHaveBeenCalledTimes(1);
    });

    it('sin respaldos dice «Sin respaldos» y no hay «último»', async () => {
        simularApi(api, {
            'GET /respaldos': {
                data: [],
                meta: {
                    total: 0,
                    pagina: 1,
                    por_pagina: 15,
                    conteos: { todos: 0, programados: 0, puntuales: 0, fallidos: 0 },
                    programacion: 'Diario, 03:00',
                    ultimo: null,
                },
            },
        });
        renderConSesion(<Respaldo />, { usuario: usuarioDePrueba('Administrador') });

        expect(await screen.findByText('Sin respaldos')).toBeInTheDocument();
        expect(screen.getByText('Diario, 03:00')).toBeInTheDocument();
    });

    it('si el historial no carga ofrece «Reintentar»', async () => {
        let falla = true;
        const lista = api.get.getMockImplementation();
        api.get.mockImplementation((...args) =>
            falla ? Promise.reject(errorHttp(500, {})) : lista(...args)
        );
        renderConSesion(<Respaldo />, { usuario: usuarioDePrueba('Administrador') });

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        falla = false;
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(
            await within(await screen.findByRole('table')).findByText('12 oct 2026, 03:00')
        ).toBeInTheDocument();
    });

    it('sin el permiso de respaldo recibe una negativa explícita y no consulta', () => {
        renderConSesion(
            <RutaProtegida ruta="/respaldo">
                <Respaldo />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Docente'), ruta: '/respaldo' }
        );

        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Respaldo' })).toBeNull();
        expect(api.get).not.toHaveBeenCalled();
    });

    it('en móvil el historial es una lista con las mismas acciones', async () => {
        const { container } = await montar();

        const movil = container.querySelector('ul.sm\\:hidden');
        expect(within(movil).getAllByRole('listitem')).toHaveLength(15);
        expect(within(movil).getAllByText('Programado · 48 MB').length).toBeGreaterThan(0);
        expect(
            within(movil).getByRole('button', {
                name: 'Restaurar el respaldo del 12 oct 2026, 03:00',
            })
        ).toBeInTheDocument();
    });

    it('el tamaño se lee en la unidad que corresponde', () => {
        expect(tamanoLegible(null)).toBe('—');
        expect(tamanoLegible(512)).toBe('512 B');
        expect(tamanoLegible(20480)).toBe('20 KB');
        expect(tamanoLegible(1468006)).toBe('1,4 MB');
        expect(tamanoLegible(50331648)).toBe('48 MB');
    });
});

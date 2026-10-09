import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Docentes from './Docentes';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const SIGLAS = { fcyt: 'FCyT', fce: 'FCE', fhce: 'FHCE', fach: 'FACH' };
const TEMPORAL = 'Fa7-Kmq4-Ru9';

// Un servidor mínimo en memoria con el contrato de GET /docentes,
// GET /docentes/{id} y POST /docentes/{id}/cuenta.
function servidor(docentes) {
    const cuentas = new Map();
    docentes.forEach((d) => d.cuenta && cuentas.set(d.id, d.cuenta));
    const estado = (d) => cuentas.get(d.id)?.estado ?? 'sin_cuenta';

    const lista = ({ params = {} }) => {
        const texto = (params.buscar ?? '').toLowerCase();
        const filtrados = docentes.filter(
            (d) =>
                (!params.facultad ||
                    d.facultades.some((f) => f.sigla === SIGLAS[params.facultad])) &&
                (!params.sin_cuenta || estado(d) === 'sin_cuenta') &&
                (!params.varias_facultades || d.facultades.length > 1) &&
                (!texto || d.nombre.toLowerCase().includes(texto))
        );
        const pagina = params.pagina ?? 1;
        const porPagina = params.por_pagina ?? 20;
        const conteos = { todas: docentes.length };
        Object.values(SIGLAS).forEach((sigla) => {
            conteos[sigla] = docentes.filter((d) =>
                d.facultades.some((f) => f.sigla === sigla)
            ).length;
        });
        conteos.sin_cuenta = docentes.filter((d) => estado(d) === 'sin_cuenta').length;
        conteos.varias_facultades = docentes.filter((d) => d.facultades.length > 1).length;

        return {
            data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina).map((d) => ({
                id: d.id,
                nombre: d.nombre,
                facultades: d.facultades.map(({ sigla, grupos }) => ({ sigla, grupos })),
                cuenta: estado(d),
            })),
            meta: { total: filtrados.length, pagina, por_pagina: porPagina, conteos },
        };
    };

    const detalle = ({ url }) => {
        const d = docentes.find((uno) => uno.id === Number(url.split('/')[2]));
        if (!d) return errorHttp(404, { message: 'Docente no encontrado.' });
        const cuenta = cuentas.get(d.id) ?? null;
        return {
            data: {
                id: d.id,
                nombre: d.nombre,
                grupos: d.facultades.reduce((s, f) => s + f.grupos, 0),
                facultades: d.facultades,
                cuenta,
                usuario_sugerido: cuenta?.usuario ?? d.sugerido,
            },
        };
    };

    const activar = ({ url, data }) => {
        const id = Number(url.split('/')[2]);
        if (cuentas.has(id)) {
            return errorHttp(409, {
                message: 'El docente ya tiene una cuenta.',
                codigo: 'YA_TIENE_CUENTA',
            });
        }
        const tomados = [...cuentas.values()];
        if (tomados.some((c) => c.usuario === data.usuario)) {
            const mensaje = 'Ya existe una cuenta con ese usuario.';
            return errorHttp(422, { message: mensaje, errors: { usuario: [mensaje] } });
        }
        if (data.correo && tomados.some((c) => c.correo === data.correo)) {
            const mensaje = 'Ya existe una cuenta con ese correo.';
            return errorHttp(422, { message: mensaje, errors: { correo: [mensaje] } });
        }
        cuentas.set(id, { estado: 'temporal', usuario: data.usuario, correo: data.correo });
        return {
            data: {
                usuario: data.usuario,
                contrasena_temporal: TEMPORAL,
                enviada_a: data.correo,
                caduca_en: '2026-10-12T10:00:00-04:00',
            },
            message: 'Cuenta activada.',
        };
    };

    simularApi(api, {
        'GET /docentes': lista,
        'GET /docentes/*': detalle,
        'POST /docentes/*/cuenta': activar,
    });

    return { cuentas };
}

const fcyt = (grupos, materias = ['Algebra I']) => ({ sigla: 'FCyT', grupos, materias });

const DOCENTES = [
    {
        id: 36,
        nombre: 'Agreda Corrales Luis Roberto',
        sugerido: 'luis.agreda',
        facultades: [fcyt(11, ['Arquitectura de Computadoras I', 'Fisica Basica I'])],
        cuenta: { estado: 'activa', usuario: 'luis.agreda', correo: 'luis.agreda@umss.edu.bo' },
    },
    {
        id: 21,
        nombre: 'Cespedes Guizada Maria Benita',
        sugerido: 'maria.cespedes',
        facultades: [
            fcyt(3, ['Ingles', 'Ingles I']),
            { sigla: 'FHCE', grupos: 5, materias: ['Ingles III', 'Linguistica General'] },
        ],
    },
    {
        id: 32,
        nombre: 'Acha Perez Samuel',
        sugerido: 'samuel.acha',
        facultades: [fcyt(1)],
        cuenta: { estado: 'temporal', usuario: 'samuel.acha', correo: null },
    },
    {
        id: 50,
        nombre: 'Flores Soliz Juan',
        sugerido: 'juan.flores',
        facultades: [{ sigla: 'FCE', grupos: 2, materias: ['Contabilidad I'] }],
    },
];

// 45 docentes: los 25 primeros con cuenta salvo el segundo; del 26 en
// adelante, solo el 43 está sin cuenta (tercera página).
const MUCHOS = Array.from({ length: 45 }, (_, i) => {
    const n = i + 1;
    const sin = n === 2 || n === 43;
    return {
        id: n,
        nombre: `Docente ${String(n).padStart(2, '0')}`,
        sugerido: `docente.${n}`,
        facultades: [fcyt(1)],
        ...(sin ? {} : { cuenta: { estado: 'activa', usuario: `docente.${n}`, correo: null } }),
    };
});

const montar = () =>
    renderConSesion(<Docentes />, { usuario: usuarioDePrueba('Administrador'), ruta: '/docentes' });

const fila = (nombre) => screen.getByRole('button', { name: new RegExp(nombre) });
const elegir = async (nombre) =>
    fireEvent.click(await screen.findByRole('button', { name: new RegExp(nombre) }));
const peticiones = () =>
    api.get.mock.calls.filter(([url]) => url === '/docentes').map(([, c]) => c.params);

async function activar({ usuario, correo } = {}) {
    const campo = await screen.findByLabelText(/Usuario/);
    if (usuario !== undefined) fireEvent.change(campo, { target: { value: usuario } });
    if (correo !== undefined) {
        fireEvent.change(screen.getByLabelText(/^Correo/), { target: { value: correo } });
    }
    fireEvent.click(screen.getByRole('button', { name: /Activar cuenta/ }));
}

beforeEach(() => {
    servidor(DOCENTES);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('Docentes', () => {
    it('lista de a 20 con facultades, grupos, estado de la cuenta y los totales (criterio 1)', async () => {
        montar();

        const cespedes = await screen.findByRole('button', { name: /Cespedes Guizada/ });
        expect(cespedes).toHaveTextContent('FCyT · 3 grupos');
        expect(cespedes).toHaveTextContent('FHCE · 5 grupos');
        expect(cespedes).toHaveTextContent('Sin cuenta');
        expect(fila('Agreda Corrales')).toHaveTextContent('Cuenta activa');
        expect(fila('Acha Perez')).toHaveTextContent('FCyT · 1 grupo');
        expect(fila('Acha Perez')).toHaveTextContent('Temporal');
        expect(screen.getByText('4 docentes · 1 en más de una facultad')).toBeInTheDocument();
        expect(peticiones()[0]).toEqual({ pagina: 1, por_pagina: 20 });
        expect(screen.getByRole('navigation', { name: 'Paginación' })).toHaveTextContent(
            '4 docentes'
        );
    });

    it('pagina en el servidor', async () => {
        servidor(MUCHOS);
        montar();

        await screen.findByRole('button', { name: /Docente 01/ });
        expect(screen.getByText('1–20 de 45 docentes')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));

        await screen.findByRole('button', { name: /Docente 21/ });
        expect(peticiones().at(-1)).toEqual({ pagina: 2, por_pagina: 20 });
        expect(screen.queryByRole('button', { name: /Docente 01/ })).not.toBeInTheDocument();
    });

    it('busca por nombre en el servidor y muestra «Sin resultados» (criterio 2)', async () => {
        montar();
        await screen.findByRole('button', { name: /Cespedes Guizada/ });

        fireEvent.change(screen.getByLabelText('Buscar docente'), { target: { value: 'flores' } });
        await waitFor(() =>
            expect(
                screen.queryByRole('button', { name: /Cespedes Guizada/ })
            ).not.toBeInTheDocument()
        );
        expect(fila('Flores Soliz')).toBeInTheDocument();
        expect(peticiones().at(-1)).toEqual({ buscar: 'flores', pagina: 1, por_pagina: 20 });

        fireEvent.change(screen.getByLabelText('Buscar docente'), { target: { value: 'zzz' } });
        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
    });

    it('filtra por facultad con la clave y cada opción muestra su conteo (criterio 3)', async () => {
        montar();
        await screen.findByRole('button', { name: /Flores Soliz/ });

        const filtro = screen.getByRole('group', { name: 'Filtrar por facultad' });
        expect(within(filtro).getByRole('button', { name: /Todas/ })).toHaveTextContent('4');
        expect(within(filtro).getByRole('button', { name: /FCyT/ })).toHaveTextContent('3');
        expect(within(filtro).getByRole('button', { name: /FCE/ })).toHaveTextContent('1');
        expect(within(filtro).getByRole('button', { name: /FACH/ })).toHaveTextContent('0');

        fireEvent.click(within(filtro).getByRole('button', { name: /FCE/ }));

        await waitFor(() =>
            expect(
                screen.queryByRole('button', { name: /Cespedes Guizada/ })
            ).not.toBeInTheDocument()
        );
        expect(fila('Flores Soliz')).toBeInTheDocument();
        expect(peticiones().at(-1)).toEqual({ facultad: 'fce', pagina: 1, por_pagina: 20 });
    });

    it('«Sin cuenta» filtra con sin_cuenta=1 y su conteo baja al activar (criterio 4)', async () => {
        montar();
        await screen.findByRole('button', { name: /Agreda Corrales/ });

        const ficha = screen.getByRole('button', { name: /^Sin cuenta/ });
        expect(ficha).toHaveTextContent('2');
        fireEvent.click(ficha);

        await waitFor(() =>
            expect(
                screen.queryByRole('button', { name: /Agreda Corrales/ })
            ).not.toBeInTheDocument()
        );
        expect(ficha).toHaveAttribute('aria-pressed', 'true');
        expect(peticiones().at(-1)).toEqual({ sin_cuenta: 1, pagina: 1, por_pagina: 20 });
        expect(fila('Cespedes Guizada')).toBeInTheDocument();
        expect(fila('Flores Soliz')).toBeInTheDocument();

        await elegir('Cespedes Guizada');
        await activar();

        await waitFor(() => expect(ficha).toHaveTextContent('1'));
        expect(screen.queryByRole('button', { name: /Cespedes Guizada/ })).not.toBeInTheDocument();
    });

    it('«Más de una facultad» filtra con varias_facultades=1 (criterio 5)', async () => {
        montar();
        await screen.findByRole('button', { name: /Flores Soliz/ });

        const ficha = screen.getByRole('button', { name: /Más de una facultad/ });
        expect(ficha).toHaveTextContent('1');
        fireEvent.click(ficha);

        await waitFor(() =>
            expect(screen.queryByRole('button', { name: /Flores Soliz/ })).not.toBeInTheDocument()
        );
        expect(fila('Cespedes Guizada')).toBeInTheDocument();
        expect(peticiones().at(-1)).toEqual({ varias_facultades: 1, pagina: 1, por_pagina: 20 });
    });

    it('el detalle separa grupos y materias por facultad e indica una sola cuenta (criterio 6)', async () => {
        montar();
        await elegir('Cespedes Guizada');

        const titulo = await screen.findByRole('heading', {
            name: 'Cespedes Guizada Maria Benita',
        });
        const detalle = titulo.closest('section');
        expect(within(detalle).getByText('8 grupos · 1 cuenta')).toBeInTheDocument();

        const [primera, segunda] = within(detalle)
            .getAllByRole('listitem')
            .filter((li) => li.querySelector('ul'));
        expect(primera).toHaveTextContent('FCyT');
        expect(primera).toHaveTextContent('3 grupos');
        expect(within(primera).getByText('Ingles I')).toBeInTheDocument();
        expect(segunda).toHaveTextContent('FHCE');
        expect(segunda).toHaveTextContent('5 grupos');
        expect(within(segunda).getByText('Linguistica General')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/docentes/21', expect.anything());
    });

    it('con cuenta muestra su usuario y no ofrece activar (criterio 7)', async () => {
        montar();

        // Al cargar se abre el primero de la lista.
        expect(await screen.findByText('luis.agreda')).toBeInTheDocument();
        expect(
            screen.getByText(/luis\.agreda@umss\.edu\.bo · contraseña propia/)
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Activar cuenta/ })).not.toBeInTheDocument();

        await elegir('Acha Perez');
        expect(await screen.findByText('samuel.acha')).toBeInTheDocument();
        expect(screen.getByText(/contraseña temporal/)).toBeInTheDocument();
        expect(screen.queryByText(TEMPORAL)).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Activar cuenta/ })).not.toBeInTheDocument();
    });

    it('un segundo intento rechazado con 409 avisa y muestra la cuenta (criterio 7)', async () => {
        const { cuentas } = servidor(DOCENTES);
        montar();
        await elegir('Flores Soliz');
        await screen.findByLabelText(/Usuario/);

        // Otra sesión la activó entretanto.
        cuentas.set(50, { estado: 'temporal', usuario: 'juan.flores', correo: null });
        await activar();

        expect(await screen.findByRole('status')).toHaveTextContent(
            'El docente ya tiene una cuenta.'
        );
        expect(await screen.findByText('juan.flores')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Activar cuenta/ })).not.toBeInTheDocument();
        expect(screen.queryByText(TEMPORAL)).not.toBeInTheDocument();
    });

    it('propone el usuario marcado «Sugerido», editable, y el correo es opcional (criterio 8)', async () => {
        montar();
        await elegir('Cespedes Guizada');

        const usuario = await screen.findByLabelText(/Usuario/);
        expect(usuario).toHaveValue('maria.cespedes');
        expect(screen.getByText('Sugerido')).toBeInTheDocument();
        expect(screen.getByLabelText(/^Correo/)).toHaveValue('');
        expect(screen.getByRole('button', { name: /Activar cuenta/ })).toBeEnabled();

        fireEvent.change(usuario, { target: { value: 'mb.cespedes' } });
        expect(screen.queryByText('Sugerido')).not.toBeInTheDocument();
        await activar();

        await screen.findByText(TEMPORAL);
        expect(api.post).toHaveBeenCalledWith('/docentes/21/cuenta', {
            usuario: 'mb.cespedes',
            correo: null,
        });
        expect(screen.getByText('mb.cespedes · sin correo')).toBeInTheDocument();
    });

    it('usuario o correo no válidos: el botón no procede y el campo lo dice (criterio 9)', async () => {
        montar();
        await elegir('Cespedes Guizada');
        const usuario = await screen.findByLabelText(/Usuario/);
        const boton = screen.getByRole('button', { name: /Activar cuenta/ });

        fireEvent.change(usuario, { target: { value: 'maría cespedes' } });
        expect(screen.getByText('Solo letras, números, puntos y guiones')).toBeInTheDocument();
        expect(boton).toBeDisabled();

        fireEvent.change(usuario, { target: { value: '' } });
        expect(usuario).toHaveAttribute('aria-invalid', 'true');
        expect(boton).toBeDisabled();

        fireEvent.change(usuario, { target: { value: 'maria.cespedes' } });
        expect(boton).toBeEnabled();

        fireEvent.change(screen.getByLabelText(/^Correo/), { target: { value: 'maria@' } });
        expect(screen.getByText('Correo no válido')).toBeInTheDocument();
        expect(boton).toBeDisabled();

        fireEvent.submit(boton.closest('form'));
        expect(api.post).not.toHaveBeenCalled();
    });

    it('usuario o correo de otra cuenta: el 422 se señala en su campo (criterio 10)', async () => {
        montar();
        await elegir('Cespedes Guizada');

        await activar({ usuario: 'luis.agreda' });
        expect(
            await screen.findByText('Ya existe una cuenta con ese usuario.')
        ).toBeInTheDocument();
        expect(screen.getByLabelText(/Usuario/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.queryByText(TEMPORAL)).not.toBeInTheDocument();

        // Al corregir el campo se va su error.
        fireEvent.change(screen.getByLabelText(/Usuario/), { target: { value: 'maria.cespedes' } });
        expect(screen.queryByText('Ya existe una cuenta con ese usuario.')).not.toBeInTheDocument();

        await activar({ correo: 'luis.agreda@umss.edu.bo' });
        expect(await screen.findByText('Ya existe una cuenta con ese correo.')).toBeInTheDocument();
        expect(screen.getByLabelText(/^Correo/)).toHaveAttribute('aria-invalid', 'true');
        expect(fila('Cespedes Guizada')).toHaveTextContent('Sin cuenta');
    });

    it('activa: avisa «Cuenta activada» y el estado pasa a «Temporal» (criterio 11)', async () => {
        montar();
        await elegir('Cespedes Guizada');
        await activar({ correo: 'Maria.Cespedes@umss.edu.bo ' });

        expect(await screen.findByRole('status')).toHaveTextContent('Cuenta activada');
        expect(api.post).toHaveBeenCalledWith('/docentes/21/cuenta', {
            usuario: 'maria.cespedes',
            correo: 'Maria.Cespedes@umss.edu.bo',
        });
        await waitFor(() => expect(fila('Cespedes Guizada')).toHaveTextContent('Temporal'));
    });

    it('la contraseña temporal se muestra una vez, con «Copiar» y la caducidad (criterio 12)', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });
        montar();
        await elegir('Cespedes Guizada');
        await activar({ correo: 'maria.cespedes@umss.edu.bo' });

        expect(await screen.findByText(TEMPORAL)).toBeInTheDocument();
        expect(screen.getByText('Caduca en 72 horas')).toBeInTheDocument();
        expect(
            screen.getByText('maria.cespedes · enviada a maria.cespedes@umss.edu.bo')
        ).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Copiar/ }));
        expect(writeText).toHaveBeenCalledWith(TEMPORAL);
        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent('Contraseña copiada')
        );

        // Al volver a abrir el detalle ya no se muestra.
        fireEvent.click(fila('Agreda Corrales'));
        await screen.findByText('luis.agreda');
        fireEvent.click(fila('Cespedes Guizada'));
        expect(await screen.findByText('maria.cespedes')).toBeInTheDocument();
        expect(screen.getByText(/contraseña temporal$/)).toBeInTheDocument();
        expect(screen.queryByText(TEMPORAL)).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Copiar/ })).not.toBeInTheDocument();
    });

    it('«Siguiente sin cuenta» abre el siguiente de la lista con su usuario propuesto (criterio 13)', async () => {
        montar();
        await elegir('Cespedes Guizada');
        await activar();
        await screen.findByText(TEMPORAL);

        fireEvent.click(await screen.findByRole('button', { name: /Siguiente sin cuenta/ }));

        expect(
            await screen.findByRole('heading', { name: 'Flores Soliz Juan' })
        ).toBeInTheDocument();
        expect(await screen.findByLabelText(/Usuario/)).toHaveValue('juan.flores');
        expect(screen.getByText('Sugerido')).toBeInTheDocument();
        expect(screen.queryByText(TEMPORAL)).not.toBeInTheDocument();
    });

    it('«Siguiente sin cuenta» pide las páginas que siguen si en esta no queda ninguno (criterio 13)', async () => {
        servidor(MUCHOS);
        montar();
        await elegir('Docente 02');
        await activar();
        await screen.findByText(TEMPORAL);

        fireEvent.click(await screen.findByRole('button', { name: /Siguiente sin cuenta/ }));

        expect(await screen.findByRole('heading', { name: 'Docente 43' })).toBeInTheDocument();
        expect(await screen.findByLabelText(/Usuario/)).toHaveValue('docente.43');
        // La lista queda en la página del elegido.
        expect(await screen.findByRole('button', { name: /Docente 43/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        expect(screen.getByText('41–45 de 45 docentes')).toBeInTheDocument();
    });

    it('sin más docentes sin cuenta en la lista filtrada no ofrece «Siguiente» (criterio 13)', async () => {
        montar();
        await screen.findByRole('button', { name: /Flores Soliz/ });
        fireEvent.click(
            within(screen.getByRole('group', { name: 'Filtrar por facultad' })).getByRole(
                'button',
                {
                    name: /FCE/,
                }
            )
        );
        await waitFor(() =>
            expect(
                screen.queryByRole('button', { name: /Cespedes Guizada/ })
            ).not.toBeInTheDocument()
        );

        await elegir('Flores Soliz');
        await activar();
        await screen.findByText(TEMPORAL);
        await waitFor(() => expect(fila('Flores Soliz')).toHaveTextContent('Temporal'));

        expect(
            screen.queryByRole('button', { name: /Siguiente sin cuenta/ })
        ).not.toBeInTheDocument();
    });

    it('en el teléfono, al elegir un docente se desplaza hasta el detalle (criterio 16)', async () => {
        const scrollIntoView = vi.fn();
        const original = Element.prototype.scrollIntoView;
        Element.prototype.scrollIntoView = scrollIntoView;
        vi.stubGlobal(
            'matchMedia',
            vi.fn((consulta) => ({ matches: consulta === '(max-width: 1023px)' }))
        );
        vi.stubGlobal('requestAnimationFrame', (tarea) => tarea());

        try {
            montar();
            await screen.findByText('luis.agreda');
            expect(scrollIntoView).not.toHaveBeenCalled();

            await elegir('Cespedes Guizada');
            expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
        } finally {
            Element.prototype.scrollIntoView = original;
        }
    });

    it('muestra «No se pudo cargar» con «Reintentar» si la lista falla', async () => {
        simularApi(api, { 'GET /docentes': errorHttp(500, { message: 'Error' }) });
        montar();

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        servidor(DOCENTES);
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

        expect(await screen.findByRole('button', { name: /Cespedes Guizada/ })).toBeInTheDocument();
    });
});

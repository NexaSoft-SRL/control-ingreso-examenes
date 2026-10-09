import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Usuarios from './Usuarios';
import { api } from '../../api/cliente';
import RutaProtegida from '../../sesion/RutaProtegida';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const TEMPORAL = 'Ej8-Heq3-Jb3';
const OTRA_TEMPORAL = 'Pj8-Rqy8-Ur2';

const CATALOGO = [
    ['periodo_oferta', 'Período y oferta académica'],
    ['aulas_docentes', 'Aulas y docentes'],
    ['padron_estudiantes', 'Padrón e inscripciones'],
    ['mis_grupos', 'Padrón e inscripciones (sus grupos)'],
    ['examenes', 'Exámenes'],
    ['habilitacion', 'Habilitación'],
    ['codigos_qr', 'Códigos QR'],
    ['punto_control', 'Punto de control'],
    ['seguimiento_vivo', 'Seguimiento en vivo'],
    ['reportes_universidad', 'Reportes de la universidad'],
    ['reportes_examenes', 'Reportes de sus exámenes'],
    ['usuarios_roles', 'Usuarios y roles'],
    ['bitacora', 'Bitácora'],
    ['respaldo_restauracion', 'Respaldo'],
].map(([clave, pantalla]) => ({ clave, pantalla }));
const CLAVES = CATALOGO.map((p) => p.clave);

const ROLES = [
    { id: 1, nombre: 'Administrador', es_sistema: true, permisos: CLAVES },
    {
        id: 2,
        nombre: 'Docente',
        es_sistema: true,
        permisos: [
            'mis_grupos',
            'examenes',
            'habilitacion',
            'codigos_qr',
            'punto_control',
            'seguimiento_vivo',
            'reportes_examenes',
        ],
    },
    {
        id: 3,
        nombre: 'Auxiliar',
        es_sistema: true,
        permisos: ['punto_control', 'seguimiento_vivo'],
    },
    {
        id: 4,
        nombre: 'Coordinador',
        es_sistema: false,
        permisos: ['periodo_oferta', 'aulas_docentes'],
    },
];
const INVITADO = { id: 5, nombre: 'Invitado', es_sistema: false, permisos: ['bitacora'] };

// 23 cuentas, las más recientes primero. La 7 es la de la sesión.
const CUENTAS = [
    {
        id: 41,
        nombre: 'Rojas Daniela',
        usuario: 'daniela.rojas',
        correo: 'coordinacion@umss.edu.bo',
        rol: 'Coordinador',
        estado: 'activo',
    },
    {
        id: 40,
        nombre: 'Mamani Torrez Diego',
        usuario: 'diego.mamani',
        correo: null,
        rol: 'Auxiliar',
        estado: 'activo',
    },
    {
        id: 30,
        nombre: 'Blanco Coca Leticia',
        usuario: 'leticia.blanco',
        correo: 'leticia.blanco@umss.edu.bo',
        rol: 'Docente',
        estado: 'activo',
    },
    {
        id: 8,
        nombre: 'Montano Quiroga Victor Hugo',
        usuario: 'victor.montano',
        correo: 'victor.montano@umss.edu.bo',
        rol: 'Docente',
        estado: 'bloqueado',
    },
    {
        id: 7,
        nombre: 'Administración académica',
        usuario: 'administracion.academica',
        correo: 'admin@umss.edu.bo',
        rol: 'Administrador',
        estado: 'activo',
    },
    ...Array.from({ length: 18 }, (_, i) => ({
        id: 100 + i,
        nombre: `Docente Número ${i + 1}`,
        usuario: `docente.${i + 1}`,
        correo: `docente.${i + 1}@umss.edu.bo`,
        rol: 'Docente',
        estado: 'activo',
    })),
];

const validacion = (errors) => errorHttp(422, { message: Object.values(errors)[0][0], errors });

// Un servidor mínimo en memoria con el contrato de las 8 rutas de HU-15.
function servidor({ roles: rolesIniciales = ROLES, propio = 'Administrador' } = {}) {
    let cuentas = CUENTAS.map((c) => ({ ...c }));
    let roles = rolesIniciales.map((r) => ({ ...r }));
    let siguiente = 500;

    const rolFuera = (r) => ({
        ...r,
        cuentas: cuentas.filter((c) => c.rol === r.nombre).length,
        permisos: CLAVES.filter((c) => r.permisos.includes(c)),
    });
    const conteos = () => ({
        ...Object.fromEntries(roles.map((r) => [r.nombre, rolFuera(r).cuentas])),
        bloqueadas: cuentas.filter((c) => c.estado === 'bloqueado').length,
    });

    const lista = ({ params = {} }) => {
        if (params.rol && !roles.some((r) => r.nombre === params.rol)) {
            return validacion({ rol: ['El rol no existe.'] });
        }
        const texto = (params.buscar ?? '').toLowerCase();
        const filtradas = cuentas.filter(
            (c) =>
                (!params.rol || c.rol === params.rol) &&
                (!params.bloqueadas || c.estado === 'bloqueado') &&
                (!texto ||
                    c.nombre.toLowerCase().includes(texto) ||
                    c.usuario.includes(texto) ||
                    (c.correo ?? '').includes(texto))
        );
        const pagina = params.pagina ?? 1;
        const porPagina = params.por_pagina ?? 20;
        return {
            data: filtradas
                .slice((pagina - 1) * porPagina, pagina * porPagina)
                .map((c) => ({ ...c })),
            meta: {
                total: filtradas.length,
                pagina,
                por_pagina: porPagina,
                cuentas: cuentas.length,
                conteos: conteos(),
            },
        };
    };

    const erroresDeCuenta = (data, exceptoId = null) => {
        const errors = {};
        const otras = cuentas.filter((c) => c.id !== exceptoId);
        if (otras.some((c) => c.usuario === data.usuario)) errors.usuario = ['Ya existe'];
        if (data.correo && otras.some((c) => c.correo === data.correo)) {
            errors.correo = ['Ya existe'];
        }
        if (!roles.some((r) => r.nombre === data.rol)) errors.rol = ['El rol no existe'];
        return Object.keys(errors).length > 0 ? validacion(errors) : null;
    };

    const crear = ({ data }) => {
        const rechazo = erroresDeCuenta(data);
        if (rechazo) return rechazo;
        const cuenta = { id: siguiente++, ...data, estado: 'activo' };
        cuentas = [cuenta, ...cuentas];
        return {
            data: cuenta,
            contrasena_temporal: TEMPORAL,
            enviada_a: data.correo,
            caduca_en: '2026-10-12T12:50:16-04:00',
            message: 'Usuario creado.',
        };
    };

    const editar = ({ url, data }) => {
        const id = Number(url.split('/')[2]);
        const cuenta = cuentas.find((c) => c.id === id);
        if (!cuenta) return errorHttp(404, { message: 'Usuario no encontrado.' });
        const rechazo = erroresDeCuenta(data, id);
        if (rechazo) return rechazo;
        if (data.activo === false && id === 7) {
            return errorHttp(422, { message: 'No puedes bloquear tu propia cuenta.' });
        }
        const { activo, ...campos } = data;
        Object.assign(cuenta, campos);
        if (activo !== undefined) cuenta.estado = activo ? 'activo' : 'bloqueado';
        return { data: { ...cuenta }, message: 'Cambios guardados.' };
    };

    const temporal = ({ url }) => {
        const cuenta = cuentas.find((c) => c.id === Number(url.split('/')[2]));
        if (!cuenta) return errorHttp(404, { message: 'Usuario no encontrado.' });
        return {
            contrasena_temporal: OTRA_TEMPORAL,
            enviada_a: cuenta.correo,
            caduca_en: '2026-10-12T12:50:27-04:00',
        };
    };

    const erroresDeRol = (data, rol = null) => {
        const errors = {};
        if (data.nombre !== undefined) {
            const repetido = roles.some(
                (r) => r !== rol && r.nombre.toLowerCase() === data.nombre.toLowerCase()
            );
            if (repetido) errors.nombre = ['Ya existe'];
            else if (rol?.es_sistema && data.nombre !== rol.nombre) {
                errors.nombre = ['El nombre de un rol de inicio no cambia.'];
            }
        }
        if (data.permisos.length === 0) errors.permisos = ['Al menos un permiso'];
        else if (rol?.nombre === propio && !data.permisos.includes('usuarios_roles')) {
            errors.permisos = ['No puedes quitar «Usuarios y roles» a tu propio rol.'];
        }
        return Object.keys(errors).length > 0 ? validacion(errors) : null;
    };

    const crearRol = ({ data }) => {
        const rechazo = erroresDeRol(data);
        if (rechazo) return rechazo;
        const rol = { id: siguiente++, nombre: data.nombre, es_sistema: false, ...data };
        roles = [...roles, rol];
        return { data: rolFuera(rol), message: 'Rol creado.' };
    };

    const editarRol = ({ url, data }) => {
        const rol = roles.find((r) => r.id === Number(url.split('/')[2]));
        if (!rol) return errorHttp(404, { message: 'Rol no encontrado.' });
        const rechazo = erroresDeRol(data, rol);
        if (rechazo) return rechazo;
        if (data.nombre !== undefined && data.nombre !== rol.nombre) {
            cuentas.forEach((c) => {
                if (c.rol === rol.nombre) c.rol = data.nombre;
            });
            rol.nombre = data.nombre;
        }
        rol.permisos = data.permisos;
        return { data: rolFuera(rol), message: 'Cambios guardados.' };
    };

    const eliminarRol = ({ url }) => {
        const rol = roles.find((r) => r.id === Number(url.split('/')[2]));
        if (!rol) return errorHttp(404, { message: 'Rol no encontrado.' });
        if (rol.es_sistema) {
            return errorHttp(409, {
                message: 'Los roles de inicio no se eliminan.',
                codigo: 'ROL_DE_INICIO',
            });
        }
        const n = rolFuera(rol).cuentas;
        if (n > 0) {
            return errorHttp(409, {
                message: `El rol tiene ${n} ${n === 1 ? 'cuenta asignada' : 'cuentas asignadas'}.`,
                codigo: 'ROL_CON_CUENTAS',
            });
        }
        roles = roles.filter((r) => r !== rol);
        return '';
    };

    simularApi(api, {
        'GET /usuarios': lista,
        'POST /usuarios': crear,
        'PUT /usuarios/*': editar,
        'POST /usuarios/*/contrasena-temporal': temporal,
        'GET /roles': () => ({ data: roles.map(rolFuera), meta: { permisos: CATALOGO } }),
        'POST /roles': crearRol,
        'PUT /roles/*': editarRol,
        'DELETE /roles/*': eliminarRol,
    });
}

const tabla = () => screen.getByRole('table', { name: 'Cuentas' });
const matriz = () => screen.getByRole('table', { name: 'Permisos por rol' });
const fila = (nombre) => within(tabla()).getByText(nombre).closest('tr');
const filas = () => within(tabla()).getAllByRole('row').slice(1);
const ultimaConsulta = () => api.get.mock.calls.filter(([url]) => url === '/usuarios').at(-1)[1];
const cifra = (rol, n) =>
    screen.getByRole('button', { name: new RegExp(`^${rol}\\s*${n} cuentas?$`) });
const opcion = (rol, n) => screen.getByRole('button', { name: new RegExp(`^${rol}\\s*${n}$`) });
const casilla = (pantalla, rol) =>
    within(matriz()).getByRole('checkbox', { name: `${pantalla} · ${rol}` });
const escribir = (dialogo, etiqueta, valor) =>
    fireEvent.change(within(dialogo).getByLabelText(new RegExp(`^${etiqueta}`)), {
        target: { value: valor },
    });

async function montar(opciones = {}) {
    const resultado = renderConSesion(<Usuarios />, {
        usuario: usuarioDePrueba('Administrador'),
        ruta: '/admin',
        ...opciones,
    });
    await within(await screen.findByRole('table', { name: 'Cuentas' })).findByText('Rojas Daniela');
    await within(matriz()).findByText('Bitácora');
    return resultado;
}

async function abrirEdicion(nombre) {
    fireEvent.click(within(tabla()).getByRole('button', { name: `Editar a ${nombre}` }));
    return screen.findByRole('dialog', { name: 'Editar usuario' });
}

function abrirNuevoUsuario() {
    fireEvent.click(screen.getByRole('button', { name: 'Nuevo usuario' }));
    return screen.getByRole('dialog', { name: 'Nuevo usuario' });
}

function abrirNuevoRol() {
    fireEvent.click(screen.getByRole('button', { name: 'Nuevo rol' }));
    return screen.getByRole('dialog', { name: 'Nuevo rol' });
}

beforeEach(() => {
    servidor();
});

describe('Usuarios y roles · cuentas', () => {
    it('muestra las cuentas por rol y la lista de a 20 con nombre, usuario, correo, rol y estado (criterio 1)', async () => {
        await montar();

        expect(ultimaConsulta().params).toEqual({ pagina: 1, por_pagina: 20 });
        expect(cifra('Administrador', 1)).toBeInTheDocument();
        expect(cifra('Docente', 20)).toBeInTheDocument();
        expect(cifra('Auxiliar', 1)).toBeInTheDocument();
        expect(cifra('Coordinador', 1)).toBeInTheDocument();

        expect(filas()).toHaveLength(20);
        expect(filas()[0]).toHaveTextContent('Rojas Daniela');
        const leticia = within(fila('Blanco Coca Leticia'));
        expect(leticia.getByText('leticia.blanco')).toBeInTheDocument();
        expect(leticia.getByText('leticia.blanco@umss.edu.bo')).toBeInTheDocument();
        expect(leticia.getByText('Docente')).toBeInTheDocument();
        expect(leticia.getByText('Activo')).toBeInTheDocument();
        expect(within(fila('Mamani Torrez Diego')).getByText('Sin correo')).toBeInTheDocument();
        expect(
            within(fila('Montano Quiroga Victor Hugo')).getByText('Bloqueado')
        ).toBeInTheDocument();

        expect(screen.getByText('1–20 de 23 cuentas')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        await waitFor(() => expect(filas()).toHaveLength(3));
        expect(ultimaConsulta().params).toEqual({ pagina: 2, por_pagina: 20 });
    });

    it('la búsqueda viaja al servidor y sin coincidencias dice «Sin resultados» (criterio 2)', async () => {
        await montar();

        fireEvent.change(screen.getByLabelText('Buscar cuenta'), {
            target: { value: 'leticia.blanco@' },
        });
        await waitFor(() => expect(filas()).toHaveLength(1));
        expect(ultimaConsulta().params).toEqual({
            buscar: 'leticia.blanco@',
            pagina: 1,
            por_pagina: 20,
        });
        expect(filas()[0]).toHaveTextContent('Blanco Coca Leticia');

        fireEvent.change(screen.getByLabelText('Buscar cuenta'), { target: { value: 'zzz' } });
        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
        expect(screen.queryByRole('table', { name: 'Cuentas' })).toBeNull();
    });

    it('el rol se elige en el filtro o pulsando su cifra, y cada opción lleva su conteo (criterio 3)', async () => {
        await montar();

        expect(opcion('Todos', 23)).toHaveAttribute('aria-pressed', 'true');
        expect(opcion('Docente', 20)).toBeInTheDocument();
        expect(opcion('Administrador', 1)).toBeInTheDocument();

        fireEvent.click(opcion('Coordinador', 1));
        await waitFor(() => expect(filas()).toHaveLength(1));
        expect(ultimaConsulta().params).toMatchObject({ rol: 'Coordinador', pagina: 1 });
        expect(filas()[0]).toHaveTextContent('Rojas Daniela');

        fireEvent.click(cifra('Auxiliar', 1));
        await waitFor(() => expect(filas()[0]).toHaveTextContent('Mamani Torrez Diego'));
        expect(ultimaConsulta().params).toMatchObject({ rol: 'Auxiliar' });
        expect(opcion('Auxiliar', 1)).toHaveAttribute('aria-pressed', 'true');

        // La misma cifra otra vez quita el filtro.
        fireEvent.click(cifra('Auxiliar', 1));
        await waitFor(() => expect(filas()).toHaveLength(20));
        expect(ultimaConsulta().params).not.toHaveProperty('rol');
    });

    it('«Bloqueadas» se combina con el rol y con la búsqueda (criterio 4)', async () => {
        await montar();

        const bloqueadas = screen.getByRole('button', { name: 'Bloqueadas 1' });
        fireEvent.click(bloqueadas);
        await waitFor(() => expect(filas()).toHaveLength(1));
        expect(ultimaConsulta().params).toEqual({ bloqueadas: 1, pagina: 1, por_pagina: 20 });
        expect(bloqueadas).toHaveAttribute('aria-pressed', 'true');

        fireEvent.click(opcion('Docente', 20));
        fireEvent.change(screen.getByLabelText('Buscar cuenta'), { target: { value: 'montano' } });
        await waitFor(() =>
            expect(ultimaConsulta().params).toEqual({
                rol: 'Docente',
                bloqueadas: 1,
                buscar: 'montano',
                pagina: 1,
                por_pagina: 20,
            })
        );
        expect(filas()[0]).toHaveTextContent('Montano Quiroga Victor Hugo');

        fireEvent.click(opcion('Auxiliar', 1));
        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
    });

    it('«Nuevo usuario» crea la cuenta, la deja primera y entrega la temporal una sola vez (criterio 5)', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        await montar();
        fireEvent.click(opcion('Coordinador', 1));
        await waitFor(() => expect(filas()).toHaveLength(1));

        const dialogo = abrirNuevoUsuario();
        // El rol del filtro vigente es el propuesto; los roles son los que existen.
        const selector = within(dialogo).getByLabelText(/^Rol/);
        expect(selector).toHaveValue('Coordinador');
        expect(within(selector).getAllByRole('option')).toHaveLength(4);
        escribir(dialogo, 'Nombre', '  Quispe Ana  ');
        escribir(dialogo, 'Usuario', 'Ana.Quispe');
        escribir(dialogo, 'Rol', 'Auxiliar');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Crear' }));

        const resultado = await screen.findByRole('dialog', { name: 'Usuario creado' });
        expect(api.post).toHaveBeenCalledWith('/usuarios', {
            nombre: 'Quispe Ana',
            usuario: 'ana.quispe',
            correo: null,
            rol: 'Auxiliar',
        });
        expect(within(resultado).getByText(TEMPORAL)).toBeInTheDocument();
        expect(within(resultado).getByText('ana.quispe · sin correo')).toBeInTheDocument();
        expect(within(resultado).getByText('Caduca en 72 horas')).toBeInTheDocument();
        fireEvent.click(within(resultado).getByRole('button', { name: 'Copiar' }));
        expect(writeText).toHaveBeenCalledWith(TEMPORAL);
        expect(await screen.findByText('Contraseña copiada')).toBeInTheDocument();

        // Sin filtros, primera en la lista y con las cifras al día.
        await waitFor(() => expect(filas()).toHaveLength(20));
        expect(filas()[0]).toHaveTextContent('Quispe Ana');
        expect(ultimaConsulta().params).toEqual({ pagina: 1, por_pagina: 20 });
        expect(cifra('Auxiliar', 2)).toBeInTheDocument();
        expect(opcion('Todos', 24)).toBeInTheDocument();

        fireEvent.click(within(resultado).getByText('Cerrar'));
        expect(screen.queryByText(TEMPORAL)).toBeNull();
    });

    it('con correo, la temporal dice a qué correo se envió (criterio 5)', async () => {
        await montar();

        const dialogo = abrirNuevoUsuario();
        expect(within(dialogo).getByLabelText(/^Rol/)).toHaveValue('Docente');
        escribir(dialogo, 'Nombre', 'Quispe Ana');
        escribir(dialogo, 'Usuario', 'ana.quispe');
        escribir(dialogo, 'Correo', 'Ana.Quispe@umss.edu.bo');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Crear' }));

        const resultado = await screen.findByRole('dialog', { name: 'Usuario creado' });
        expect(api.post.mock.calls[0][1].correo).toBe('ana.quispe@umss.edu.bo');
        expect(
            within(resultado).getByText('ana.quispe · enviada a ana.quispe@umss.edu.bo')
        ).toBeInTheDocument();
    });

    it('no guarda y señala cada campo: vacío, corto, caracteres, correo y «Ya existe» (criterio 6)', async () => {
        await montar();
        const dialogo = abrirNuevoUsuario();
        const crear = within(dialogo).getByRole('button', { name: 'Crear' });

        fireEvent.click(crear);
        expect(within(dialogo).getAllByText('Obligatorio')).toHaveLength(2);

        escribir(dialogo, 'Nombre', 'An');
        escribir(dialogo, 'Usuario', 'ana quispe');
        escribir(dialogo, 'Correo', 'ana@umss');
        fireEvent.click(crear);
        expect(within(dialogo).getByText('Mínimo 3 caracteres')).toBeInTheDocument();
        expect(
            within(dialogo).getByText('Solo letras, números, puntos y guiones')
        ).toBeInTheDocument();
        expect(within(dialogo).getByText('Correo no válido')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();

        // El usuario y el correo de otra cuenta los rechaza el servidor.
        escribir(dialogo, 'Nombre', 'Ana Quispe');
        escribir(dialogo, 'Usuario', 'leticia.blanco');
        escribir(dialogo, 'Correo', 'admin@umss.edu.bo');
        fireEvent.click(crear);
        await waitFor(() => expect(within(dialogo).getAllByText('Ya existe')).toHaveLength(2));
        expect(within(dialogo).getByLabelText(/^Usuario/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('dialog', { name: 'Nuevo usuario' })).toBeInTheDocument();

        // Al corregir el campo, su error se va.
        escribir(dialogo, 'Usuario', 'ana.quispe');
        expect(within(dialogo).getAllByText('Ya existe')).toHaveLength(1);
    });

    it('«Editar» guarda nombre, usuario, correo y rol y avisa «Cambios guardados» (criterio 7)', async () => {
        await montar();
        const dialogo = await abrirEdicion('Mamani Torrez Diego');
        expect(within(dialogo).getByLabelText(/^Nombre/)).toHaveValue('Mamani Torrez Diego');
        expect(within(dialogo).getByLabelText(/^Correo/)).toHaveValue('');

        escribir(dialogo, 'Usuario', 'victor.montano');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));
        expect(await within(dialogo).findByText('Ya existe')).toBeInTheDocument();

        escribir(dialogo, 'Nombre', 'Mamani Torrez Diego Luis');
        escribir(dialogo, 'Usuario', 'diego.mamani2');
        escribir(dialogo, 'Correo', 'diego.mamani@umss.edu.bo');
        escribir(dialogo, 'Rol', 'Coordinador');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await screen.findByText('Cambios guardados')).toBeInTheDocument();
        expect(api.put).toHaveBeenLastCalledWith('/usuarios/40', {
            nombre: 'Mamani Torrez Diego Luis',
            usuario: 'diego.mamani2',
            correo: 'diego.mamani@umss.edu.bo',
            rol: 'Coordinador',
        });
        expect(screen.queryByRole('dialog')).toBeNull();
        const editada = within(await waitFor(() => fila('Mamani Torrez Diego Luis')));
        expect(editada.getByText('diego.mamani2')).toBeInTheDocument();
        expect(editada.getByText('Coordinador')).toBeInTheDocument();
        await waitFor(() => expect(cifra('Coordinador', 2)).toBeInTheDocument());
    });

    it('«Bloquear» y «Desbloquear» cambian el estado al guardar, sin eliminar la cuenta (criterio 8)', async () => {
        await montar();
        let dialogo = await abrirEdicion('Blanco Coca Leticia');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Bloquear' }));
        expect(within(dialogo).getByText('Bloqueado')).toBeInTheDocument();
        expect(api.put).not.toHaveBeenCalled();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await screen.findByText('Cuenta bloqueada')).toBeInTheDocument();
        expect(api.put).toHaveBeenLastCalledWith(
            '/usuarios/30',
            expect.objectContaining({ usuario: 'leticia.blanco', activo: false })
        );
        await waitFor(() =>
            expect(within(fila('Blanco Coca Leticia')).getByText('Bloqueado')).toBeInTheDocument()
        );
        expect(screen.getByRole('button', { name: 'Bloqueadas 2' })).toBeInTheDocument();

        dialogo = await abrirEdicion('Blanco Coca Leticia');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Desbloquear' }));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));
        expect(await screen.findByText('Cuenta desbloqueada')).toBeInTheDocument();
        expect(api.put).toHaveBeenLastCalledWith(
            '/usuarios/30',
            expect.objectContaining({ activo: true })
        );
        await waitFor(() =>
            expect(within(fila('Blanco Coca Leticia')).getByText('Activo')).toBeInTheDocument()
        );
    });

    it('bloquear la cuenta propia se rechaza con el mensaje del servidor (criterio 8)', async () => {
        await montar();
        const dialogo = await abrirEdicion('Administración académica');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Bloquear' }));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await within(dialogo).findByRole('alert')).toHaveTextContent(
            'No puedes bloquear tu propia cuenta.'
        );
        expect(screen.getByRole('dialog', { name: 'Editar usuario' })).toBeInTheDocument();
        expect(within(fila('Administración académica')).getByText('Activo')).toBeInTheDocument();
    });

    it('«Restablecer contraseña» pide confirmar y muestra la temporal nueva una sola vez (criterio 9)', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        await montar();
        let dialogo = await abrirEdicion('Blanco Coca Leticia');

        fireEvent.click(within(dialogo).getByRole('button', { name: 'Restablecer contraseña' }));
        expect(api.post).not.toHaveBeenCalled();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'No restablecer' }));
        expect(api.post).not.toHaveBeenCalled();

        fireEvent.click(within(dialogo).getByRole('button', { name: 'Restablecer contraseña' }));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Confirmar' }));
        expect(await within(dialogo).findByText(OTRA_TEMPORAL)).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/usuarios/30/contrasena-temporal');
        expect(
            within(dialogo).getByText('leticia.blanco · enviada a leticia.blanco@umss.edu.bo')
        ).toBeInTheDocument();
        expect(within(dialogo).getByText('Caduca en 72 horas')).toBeInTheDocument();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Copiar' }));
        expect(writeText).toHaveBeenCalledWith(OTRA_TEMPORAL);

        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cancelar' }));
        expect(screen.queryByText(OTRA_TEMPORAL)).toBeNull();
        dialogo = await abrirEdicion('Blanco Coca Leticia');
        expect(within(dialogo).queryByText(OTRA_TEMPORAL)).toBeNull();
    });

    it('si la lista no carga ofrece «Reintentar»', async () => {
        let falla = true;
        const lista = api.get.getMockImplementation();
        api.get.mockImplementation((url, config) =>
            url === '/usuarios' && falla
                ? Promise.reject(errorHttp(500, { message: 'Error' }))
                : lista(url, config)
        );
        renderConSesion(<Usuarios />, { usuario: usuarioDePrueba('Administrador') });

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        falla = false;
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(
            await within(await screen.findByRole('table', { name: 'Cuentas' })).findByText(
                'Rojas Daniela'
            )
        ).toBeInTheDocument();
    });

    it('sin el permiso de usuarios y roles recibe una negativa explícita y no consulta (criterio 14)', () => {
        renderConSesion(
            <RutaProtegida ruta="/admin">
                <Usuarios />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Docente'), ruta: '/admin' }
        );

        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Usuarios y roles' })).toBeNull();
        expect(api.get).not.toHaveBeenCalled();
    });

    it('en móvil las tablas tienen su lista: cuentas y permisos del rol elegido (criterio 16)', async () => {
        const { container } = await montar();

        const [cuentas, permisos] = container.querySelectorAll('.sm\\:hidden');
        expect(within(cuentas).getAllByRole('listitem')).toHaveLength(20);
        expect(within(cuentas).getByText('diego.mamani · Sin correo')).toBeInTheDocument();
        expect(
            within(cuentas).getByRole('button', { name: 'Editar a Rojas Daniela' })
        ).toBeInTheDocument();

        // Un rol a la vez: se elige con las fichas y se marcan sus pantallas.
        expect(within(permisos).getByRole('button', { name: 'Administrador' })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        expect(within(permisos).queryByRole('button', { name: /^Editar rol/ })).toBeNull();
        fireEvent.click(within(permisos).getByRole('button', { name: 'Coordinador' }));
        expect(within(permisos).getAllByRole('checkbox')).toHaveLength(14);
        expect(
            within(permisos).getByRole('checkbox', { name: 'Aulas y docentes · Coordinador' })
        ).toBeChecked();
        expect(
            within(permisos).getByRole('checkbox', { name: 'Bitácora · Coordinador' })
        ).not.toBeChecked();
        expect(
            within(permisos).getByRole('button', { name: 'Eliminar rol Coordinador' })
        ).toBeInTheDocument();
    });
});

describe('Usuarios y roles · permisos por rol', () => {
    it('la matriz presenta las pantallas por rol, con los de inicio y los creados (criterio 10)', async () => {
        await montar();

        const columnas = within(matriz())
            .getAllByRole('columnheader')
            .map((th) => th.textContent);
        expect(columnas).toEqual([
            'Pantalla',
            'Administrador1 cuenta',
            'Docente20 cuentas',
            'Auxiliar1 cuenta',
            'Coordinador1 cuenta',
        ]);
        expect(within(matriz()).getAllByRole('rowheader')).toHaveLength(14);
        expect(within(matriz()).getAllByRole('checkbox')).toHaveLength(14 * 4);

        expect(casilla('Bitácora', 'Administrador')).toBeChecked();
        expect(casilla('Exámenes', 'Docente')).toBeChecked();
        expect(casilla('Bitácora', 'Docente')).not.toBeChecked();
        expect(casilla('Punto de control', 'Auxiliar')).toBeChecked();
        expect(casilla('Período y oferta académica', 'Coordinador')).toBeChecked();

        // Los roles de inicio no se renombran ni se eliminan.
        const acciones = within(matriz())
            .getAllByRole('button')
            .map((b) => b.getAttribute('aria-label'));
        expect(acciones).toEqual(['Editar rol Coordinador', 'Eliminar rol Coordinador']);
        expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled();
    });

    it('«Nuevo rol» señala el nombre vacío, el repetido y la falta de permisos (criterio 11)', async () => {
        await montar();
        const dialogo = abrirNuevoRol();
        const crear = within(dialogo).getByRole('button', { name: 'Crear' });
        expect(within(dialogo).getAllByRole('checkbox')).toHaveLength(14);

        fireEvent.click(crear);
        expect(within(dialogo).getByText('Obligatorio')).toBeInTheDocument();
        expect(within(dialogo).getByText('Al menos un permiso')).toBeInTheDocument();

        escribir(dialogo, 'Nombre', 'Su');
        fireEvent.click(crear);
        expect(within(dialogo).getByText('Mínimo 3 caracteres')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();

        escribir(dialogo, 'Nombre', 'docente');
        fireEvent.click(within(dialogo).getByLabelText('Bitácora'));
        expect(within(dialogo).queryByText('Al menos un permiso')).toBeNull();
        fireEvent.click(crear);
        expect(await within(dialogo).findByText('Ya existe')).toBeInTheDocument();
        expect(within(matriz()).getAllByRole('columnheader')).toHaveLength(5);
    });

    it('el rol nuevo aparece en la matriz, en el filtro y en el selector de las cuentas (criterio 11)', async () => {
        await montar();
        const dialogo = abrirNuevoRol();
        escribir(dialogo, 'Nombre', ' Supervisor ');
        fireEvent.click(within(dialogo).getByLabelText('Seguimiento en vivo'));
        fireEvent.click(within(dialogo).getByLabelText('Período y oferta académica'));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Crear' }));

        expect(await screen.findByText('Rol creado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/roles', {
            nombre: 'Supervisor',
            permisos: ['periodo_oferta', 'seguimiento_vivo'],
        });
        expect(screen.queryByRole('dialog')).toBeNull();

        await waitFor(() => expect(casilla('Seguimiento en vivo', 'Supervisor')).toBeChecked());
        expect(casilla('Bitácora', 'Supervisor')).not.toBeChecked();
        expect(
            within(matriz()).getByRole('button', { name: 'Eliminar rol Supervisor' })
        ).toBeInTheDocument();
        await waitFor(() => expect(opcion('Supervisor', 0)).toBeInTheDocument());
        expect(cifra('Supervisor', 0)).toBeInTheDocument();

        const cuenta = abrirNuevoUsuario();
        expect(
            within(within(cuenta).getByLabelText(/^Rol/))
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual(['Administrador', 'Docente', 'Auxiliar', 'Coordinador', 'Supervisor']);
    });

    it('marcar y desmarcar en la matriz se guarda por rol con «Guardar» (criterio 12)', async () => {
        const { sesion } = await montar();
        const guardar = screen.getByRole('button', { name: 'Guardar' });

        fireEvent.click(casilla('Bitácora', 'Coordinador'));
        fireEvent.click(casilla('Período y oferta académica', 'Coordinador'));
        fireEvent.click(casilla('Bitácora', 'Docente'));
        // Un cambio deshecho no cuenta.
        fireEvent.click(casilla('Bitácora', 'Auxiliar'));
        fireEvent.click(casilla('Bitácora', 'Auxiliar'));
        expect(casilla('Bitácora', 'Coordinador')).toBeChecked();
        expect(api.put).not.toHaveBeenCalled();
        expect(guardar).toBeEnabled();

        fireEvent.click(guardar);
        expect(await screen.findByText('Cambios guardados')).toBeInTheDocument();
        expect(api.put).toHaveBeenCalledTimes(2);
        expect(api.put).toHaveBeenCalledWith('/roles/2', {
            permisos: [...ROLES[1].permisos, 'bitacora'],
        });
        expect(api.put).toHaveBeenCalledWith('/roles/4', {
            permisos: ['aulas_docentes', 'bitacora'],
        });
        await waitFor(() => expect(guardar).toBeDisabled());
        expect(casilla('Bitácora', 'Docente')).toBeChecked();
        expect(casilla('Período y oferta académica', 'Coordinador')).not.toBeChecked();
        // No tocó el rol de la cuenta: la sesión no se vuelve a pedir.
        expect(sesion.actualizar).not.toHaveBeenCalled();
    });

    it('los permisos de un rol de inicio se editan, y al cambiar el propio se refresca la sesión (criterio 12)', async () => {
        const { sesion } = await montar();

        fireEvent.click(casilla('Respaldo', 'Administrador'));
        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        expect(await screen.findByText('Cambios guardados')).toBeInTheDocument();
        expect(api.put).toHaveBeenCalledWith('/roles/1', {
            permisos: CLAVES.filter((c) => c !== 'respaldo_restauracion'),
        });
        expect(sesion.actualizar).toHaveBeenCalledTimes(1);
    });

    it('quitarse «Usuarios y roles» o dejar un rol sin permisos se rechaza con su motivo; «Descartar» deshace (criterio 12)', async () => {
        await montar();
        const guardar = screen.getByRole('button', { name: 'Guardar' });

        fireEvent.click(casilla('Usuarios y roles', 'Administrador'));
        fireEvent.click(casilla('Punto de control', 'Auxiliar'));
        fireEvent.click(casilla('Seguimiento en vivo', 'Auxiliar'));
        fireEvent.click(casilla('Bitácora', 'Docente'));
        fireEvent.click(guardar);

        const alerta = await screen.findByRole('alert');
        expect(alerta).toHaveTextContent(
            'Administrador: No puedes quitar «Usuarios y roles» a tu propio rol.'
        );
        expect(alerta).toHaveTextContent('Auxiliar: Al menos un permiso');
        // El que sí se pudo queda guardado; los rechazados siguen pendientes.
        expect(api.put).toHaveBeenCalledTimes(3);
        expect(screen.queryByText('Cambios guardados')).toBeNull();
        expect(casilla('Bitácora', 'Docente')).toBeChecked();
        expect(casilla('Usuarios y roles', 'Administrador')).not.toBeChecked();
        expect(guardar).toBeEnabled();

        fireEvent.click(screen.getByRole('button', { name: 'Descartar' }));
        expect(casilla('Usuarios y roles', 'Administrador')).toBeChecked();
        expect(casilla('Punto de control', 'Auxiliar')).toBeChecked();
        expect(casilla('Bitácora', 'Docente')).toBeChecked();
        expect(screen.queryByRole('alert')).toBeNull();
        expect(guardar).toBeDisabled();
    });

    it('un rol creado se renombra y cambia de permisos desde «Editar rol» (criterio 12)', async () => {
        await montar();
        fireEvent.click(opcion('Coordinador', 1));
        await waitFor(() => expect(filas()).toHaveLength(1));

        fireEvent.click(within(matriz()).getByRole('button', { name: 'Editar rol Coordinador' }));
        const dialogo = screen.getByRole('dialog', { name: 'Editar rol' });
        expect(within(dialogo).getByLabelText(/^Nombre/)).toHaveValue('Coordinador');
        expect(within(dialogo).getByLabelText('Aulas y docentes')).toBeChecked();

        escribir(dialogo, 'Nombre', 'Auxiliar');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));
        expect(await within(dialogo).findByText('Ya existe')).toBeInTheDocument();

        escribir(dialogo, 'Nombre', 'Coordinación');
        fireEvent.click(within(dialogo).getByLabelText('Aulas y docentes'));
        fireEvent.click(within(dialogo).getByLabelText('Bitácora'));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await screen.findByText('Cambios guardados')).toBeInTheDocument();
        expect(api.put).toHaveBeenLastCalledWith('/roles/4', {
            nombre: 'Coordinación',
            permisos: ['periodo_oferta', 'bitacora'],
        });
        await waitFor(() => expect(casilla('Bitácora', 'Coordinación')).toBeChecked());
        // El filtro por el nombre anterior se quita y las cifras siguen al rol.
        await waitFor(() => expect(filas()).toHaveLength(20));
        expect(ultimaConsulta().params).not.toHaveProperty('rol');
        expect(cifra('Coordinación', 1)).toBeInTheDocument();
        expect(within(fila('Rojas Daniela')).getByText('Coordinación')).toBeInTheDocument();
    });

    it('eliminar un rol con cuentas se rechaza con el motivo; uno creado sin cuentas se elimina (criterio 13)', async () => {
        servidor({ roles: [...ROLES, INVITADO] });
        await montar();
        expect(within(matriz()).queryByRole('button', { name: 'Eliminar rol Docente' })).toBeNull();

        fireEvent.click(within(matriz()).getByRole('button', { name: 'Eliminar rol Coordinador' }));
        let dialogo = screen.getByRole('dialog', { name: 'Eliminar rol' });
        expect(within(dialogo).getByText('Coordinador')).toBeInTheDocument();
        expect(within(dialogo).getByText('1 cuenta')).toBeInTheDocument();
        expect(api.delete).not.toHaveBeenCalled();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Eliminar' }));
        expect(await within(dialogo).findByRole('alert')).toHaveTextContent(
            'El rol tiene 1 cuenta asignada.'
        );
        expect(api.delete).toHaveBeenCalledWith('/roles/4');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cancelar' }));
        expect(casilla('Aulas y docentes', 'Coordinador')).toBeChecked();

        fireEvent.click(within(matriz()).getByRole('button', { name: 'Eliminar rol Invitado' }));
        dialogo = screen.getByRole('dialog', { name: 'Eliminar rol' });
        expect(within(dialogo).getByText('0 cuentas')).toBeInTheDocument();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Eliminar' }));

        expect(await screen.findByText('Rol eliminado')).toBeInTheDocument();
        expect(api.delete).toHaveBeenLastCalledWith('/roles/5');
        await waitFor(() =>
            expect(
                within(matriz()).queryByRole('checkbox', { name: 'Bitácora · Invitado' })
            ).toBeNull()
        );
        expect(within(matriz()).getAllByRole('columnheader')).toHaveLength(5);
        await waitFor(() =>
            expect(screen.queryByRole('button', { name: /^Invitado\s*0 cuentas$/ })).toBeNull()
        );
    });

    it('si los roles no cargan, la matriz ofrece «Reintentar» y las cuentas siguen a la vista', async () => {
        let falla = true;
        const original = api.get.getMockImplementation();
        api.get.mockImplementation((url, config) =>
            url === '/roles' && falla
                ? Promise.reject(errorHttp(500, { message: 'Error' }))
                : original(url, config)
        );
        renderConSesion(<Usuarios />, { usuario: usuarioDePrueba('Administrador') });

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        expect(await within(tabla()).findByText('Rojas Daniela')).toBeInTheDocument();
        expect(screen.queryByRole('table', { name: 'Permisos por rol' })).toBeNull();

        falla = false;
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(await screen.findByRole('table', { name: 'Permisos por rol' })).toBeInTheDocument();
        expect(casilla('Bitácora', 'Administrador')).toBeChecked();
    });
});

import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';
import { FacultadesContexto, SesionContexto } from '../sesion/SesionContexto';

// Las 14 claves del catálogo de permisos.
export const CATALOGO_PERMISOS = [
    'periodo_oferta',
    'aulas_docentes',
    'padron_estudiantes',
    'mis_grupos',
    'examenes',
    'habilitacion',
    'codigos_qr',
    'punto_control',
    'seguimiento_vivo',
    'reportes_universidad',
    'reportes_examenes',
    'usuarios_roles',
    'bitacora',
    'respaldo_restauracion',
];

// Permisos de los tres roles de inicio. El Administrador no tiene las
// pantallas de docencia; un rol creado se prueba con `usuarioDePrueba(rol, { rol: '…', permisos: [...] })`.
export const PERMISOS = {
    Administrador: [
        'periodo_oferta',
        'aulas_docentes',
        'padron_estudiantes',
        'reportes_universidad',
        'usuarios_roles',
        'bitacora',
        'respaldo_restauracion',
    ],
    Docente: [
        'mis_grupos',
        'examenes',
        'habilitacion',
        'codigos_qr',
        'punto_control',
        'seguimiento_vivo',
        'reportes_examenes',
    ],
    Auxiliar: ['punto_control', 'seguimiento_vivo'],
};

const NOMBRES = {
    Administrador: ['Administración académica', 'administracion.academica', 'admin@umss.edu.bo'],
    Docente: ['Blanco Coca Leticia', 'leticia.blanco', 'leticia.blanco@umss.edu.bo'],
    Auxiliar: ['Mamani Torrez Diego', 'diego.mamani', 'auxiliar@umss.edu.bo'],
};

// El objeto `user` de la sesión, como lo devuelve la API (4.1): trae también
// `name` y `email`, que el backend conserva por compatibilidad.
export function usuarioDePrueba(rol = 'Docente', cambios = {}) {
    const [nombre, usuario, correo] = NOMBRES[rol] ?? [
        'Cuenta de prueba',
        'cuenta.prueba',
        'cuenta.prueba@umss.edu.bo',
    ];
    return {
        id: 7,
        nombre,
        name: nombre,
        usuario,
        correo,
        email: correo,
        rol,
        permisos: PERMISOS[rol] ?? [],
        debe_cambiar_contrasena: false,
        docente_id: rol === 'Docente' ? 412 : null,
        ...cambios,
    };
}

export const FACULTADES = [
    {
        id: 1,
        clave: 'fcyt',
        sigla: 'FCyT',
        nombre: 'Ciencias y Tecnología',
        color: '#B90813',
        edificios: 21,
        aulas: 93,
    },
    {
        id: 2,
        clave: 'fce',
        sigla: 'FCE',
        nombre: 'Ciencias Económicas',
        color: '#107C41',
        edificios: 16,
        aulas: 75,
    },
    {
        id: 3,
        clave: 'fhce',
        sigla: 'FHCE',
        nombre: 'Humanidades y Cs. de la Educación',
        color: '#ea580c',
        edificios: 12,
        aulas: 41,
    },
    {
        id: 4,
        clave: 'fach',
        sigla: 'FACH',
        nombre: 'Arquitectura, Cs. del Hábitat y Diseño',
        color: '#154075',
        edificios: 7,
        aulas: 28,
    },
];

// La sesión que reciben los componentes, sin red.
export function sesionDePrueba(usuario = usuarioDePrueba(), cambios = {}) {
    return {
        usuario,
        cargando: false,
        entrar: vi.fn(),
        salir: vi.fn(() => Promise.resolve()),
        actualizar: vi.fn(() => Promise.resolve(usuario)),
        puede: (permiso) => Boolean(usuario?.permisos?.includes(permiso)),
        ...cambios,
    };
}

// Monta `ui` con enrutador en memoria, sesión y facultades.
//
//   const { sesion } = renderConSesion(<Examenes />, { usuario: usuarioDePrueba('Docente'), ruta: '/examenes?examen=1' });
//
// `usuario: null` es sin sesión. Devuelve lo de `render` más `sesion`.
export function renderConSesion(
    ui,
    { usuario, ruta = '/', facultades = FACULTADES, sesion = {} } = {}
) {
    const cuenta = usuario === undefined ? usuarioDePrueba() : usuario;
    const valor = sesionDePrueba(cuenta, sesion);

    const resultado = render(
        <MemoryRouter initialEntries={[ruta]}>
            <SesionContexto.Provider value={valor}>
                <FacultadesContexto.Provider value={facultades}>{ui}</FacultadesContexto.Provider>
            </SesionContexto.Provider>
        </MemoryRouter>
    );

    return { ...resultado, sesion: valor };
}

// Un rechazo con la forma de los de axios.
export function errorHttp(estado, datos = {}, config = {}) {
    const error = new Error(`HTTP ${estado}`);
    error.isAxiosError = true;
    error.response = { status: estado, data: datos, headers: {} };
    error.config = config;
    return error;
}

// Una promesa que la prueba resuelve cuando quiere (estados de carga).
export function respuestaDiferida() {
    let resolver;
    let rechazar;
    const promesa = new Promise((bien, mal) => {
        resolver = bien;
        rechazar = mal;
    });
    return { promesa, resolver, rechazar };
}

function patron(ruta) {
    const texto = ruta.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '[^/]+');
    return new RegExp(`^${texto}$`);
}

// Respuestas simuladas por ruta sobre el cliente sustituido:
//
//   vi.mock('../../api/cliente');
//   import { api } from '../../api/cliente';
//   simularApi(api, {
//       'GET /examenes': { data: [], meta: {} },
//       'GET /examenes/*/habilitaciones': (config) => ({ data: [], meta: { total: 0 } }),
//       'POST /auth/login': errorHttp(401, { message: 'Credenciales incorrectas.' }),
//   });
//
// El valor es el cuerpo (se envuelve en `{ data, status: 200 }`), un
// `errorHttp` (se rechaza), una promesa o una función que recibe
// `{ url, params, data }` y devuelve cualquiera de los anteriores. `*`
// vale por un tramo de la ruta. Una ruta sin simular se rechaza con 404.
export function simularApi(api, rutas = {}) {
    const tabla = Object.entries(rutas).map(([clave, valor]) => {
        const [metodo, ruta] = clave.split(' ');
        return { metodo: metodo.toLowerCase(), patron: patron(ruta), valor };
    });

    const responder = (metodo) => async (url, segundo, tercero) => {
        const conCuerpo = metodo !== 'get' && metodo !== 'delete';
        const config = (conCuerpo ? tercero : segundo) ?? {};
        const pedido = {
            url,
            params: config.params,
            data: conCuerpo ? segundo : undefined,
            config,
        };
        const fila = tabla.find((f) => f.metodo === metodo && f.patron.test(url));
        if (!fila) throw errorHttp(404, { message: `Sin simular: ${metodo.toUpperCase()} ${url}` });

        let valor = typeof fila.valor === 'function' ? fila.valor(pedido) : fila.valor;
        valor = await valor;
        if (valor instanceof Error) throw valor;
        return { data: valor, status: 200, headers: {} };
    };

    ['get', 'post', 'put', 'patch', 'delete'].forEach((metodo) => {
        api[metodo].mockReset();
        api[metodo].mockImplementation(responder(metodo));
    });

    return api;
}

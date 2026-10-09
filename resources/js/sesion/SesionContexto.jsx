import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import PropTypes from 'prop-types';
import { api, suscribirSesion, SIN_SESION } from '../api/cliente';
import { olvidarEdificios } from '../api/usarEdificios';
import Logo from '../componentes/Logo';

const SIN_PROVEEDOR = {
    usuario: null,
    cargando: false,
    entrar: () => Promise.reject(new Error('Sin ProveedorSesion')),
    salir: () => Promise.resolve(),
    actualizar: () => Promise.resolve(null),
    puede: () => false,
};

export const SesionContexto = createContext(SIN_PROVEEDOR);
export const FacultadesContexto = createContext([]);

// { usuario, cargando, entrar(correo, password), salir(), actualizar(), puede(permiso) }
export function useSesion() {
    return useContext(SesionContexto);
}

// Las facultades de GET /api/facultades: [{ id, clave, sigla, nombre, color, edificios, aulas }].
export function useFacultades() {
    return useContext(FacultadesContexto);
}

// Los hooks se definen como `use…` (regla de ESLint) y se exportan también
// con el nombre en español que usan las páginas.
export { useSesion as usarSesion, useFacultades as usarFacultades };

export function PantallaDeCarga() {
    return (
        <div
            className="flex min-h-dvh items-center justify-center bg-slate-100"
            role="status"
            aria-busy="true"
            aria-label="Cargando"
        >
            <Logo className="h-14 w-14 animate-pulse" />
        </div>
    );
}

// La sesión del cliente. La verdad es la cookie: al montar se pregunta a
// GET /api/auth/sesion y no se guarda nada en el navegador.
export function ProveedorSesion({ children }) {
    const [usuario, setUsuario] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [facultades, setFacultades] = useState([]);

    const actualizar = useCallback(
        () =>
            api
                .get('/auth/sesion')
                .then((respuesta) => {
                    const cuenta = respuesta.data?.user ?? null;
                    setUsuario(cuenta);
                    return cuenta;
                })
                .catch(() => {
                    setUsuario(null);
                    return null;
                }),
        []
    );

    useEffect(() => {
        let vigente = true;
        actualizar().finally(() => vigente && setCargando(false));
        return () => {
            vigente = false;
        };
    }, [actualizar]);

    useEffect(
        () =>
            suscribirSesion((evento) => {
                if (evento === SIN_SESION) setUsuario(null);
            }),
        []
    );

    const usuarioId = usuario?.id ?? null;

    useEffect(() => {
        if (usuarioId === null) {
            setFacultades([]);
            olvidarEdificios();
            return undefined;
        }
        let vigente = true;
        api.get('/facultades')
            .then((respuesta) => vigente && setFacultades(respuesta.data?.data ?? []))
            .catch(() => {});
        return () => {
            vigente = false;
        };
    }, [usuarioId]);

    // El acceso es por correo: la API lo recibe como `email`.
    const entrar = useCallback(async (correo, password) => {
        const respuesta = await api.post('/auth/login', {
            email: String(correo).trim().toLowerCase(),
            password,
        });
        const cuenta = respuesta.data?.user ?? null;
        setUsuario(cuenta);
        return cuenta;
    }, []);

    const salir = useCallback(async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // La sesión del cliente se cierra igual.
        }
        setUsuario(null);
    }, []);

    const valor = useMemo(
        () => ({
            usuario,
            cargando,
            entrar,
            salir,
            actualizar,
            puede: (permiso) => Boolean(usuario?.permisos?.includes(permiso)),
        }),
        [usuario, cargando, entrar, salir, actualizar]
    );

    return (
        <SesionContexto.Provider value={valor}>
            <FacultadesContexto.Provider value={facultades}>
                {cargando ? <PantallaDeCarga /> : children}
            </FacultadesContexto.Provider>
        </SesionContexto.Provider>
    );
}

ProveedorSesion.propTypes = { children: PropTypes.node };

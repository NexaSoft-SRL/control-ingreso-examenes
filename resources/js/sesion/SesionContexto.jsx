import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import PropTypes from 'prop-types';
import { api, suscribirSesion, CAMBIAR_CONTRASENA, SIN_SESION } from '../api/cliente';
import { olvidarEdificios } from '../api/usarEdificios';
import Escudo from '../componentes/Escudo';

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

// { usuario, cargando, entrar(usuario, password), salir(), actualizar(), puede(permiso) }
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
            className="flex min-h-dvh items-center justify-center bg-fondo"
            role="status"
            aria-busy="true"
            aria-label="Cargando"
        >
            <Escudo className="h-16 animate-pulse" alt="" />
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
                if (evento === CAMBIAR_CONTRASENA) {
                    setUsuario((previo) =>
                        previo ? { ...previo, debe_cambiar_contrasena: true } : previo
                    );
                }
            }),
        []
    );

    const usuarioId = usuario?.id ?? null;
    const conAcceso = usuario !== null && !usuario.debe_cambiar_contrasena;

    useEffect(() => {
        if (usuarioId === null) {
            setFacultades([]);
            olvidarEdificios();
            return undefined;
        }
        if (!conAcceso) return undefined;
        let vigente = true;
        api.get('/facultades')
            .then((respuesta) => vigente && setFacultades(respuesta.data?.data ?? []))
            .catch(() => {});
        return () => {
            vigente = false;
        };
    }, [usuarioId, conAcceso]);

    const entrar = useCallback(async (nombre, password) => {
        const respuesta = await api.post('/auth/login', {
            usuario: String(nombre).trim().toLowerCase(),
            password,
        });
        const cuenta = respuesta.data?.user ?? null;
        setUsuario(cuenta);
        return cuenta;
    }, []);

    // Sin respuesta del servidor la sesión sigue abierta allá: se lanza el
    // error para que la pantalla lo diga. Con cualquier respuesta (401, 419)
    // la sesión ya no existe y el cliente se cierra.
    const salir = useCallback(async () => {
        try {
            await api.post('/auth/logout');
        } catch (error) {
            if (!error?.response) throw error;
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

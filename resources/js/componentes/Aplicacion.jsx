import React from 'react';
import PropTypes from 'prop-types';
import { BrowserRouter, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import UsuariosRoles from '../paginas/admin/UsuariosRoles.jsx';
import AsignaturasAmbientes from '../paginas/admin/AsignaturasAmbientes.jsx';
import Bitacora from '../paginas/admin/Bitacora.jsx';
import CargaMasiva from '../paginas/estudiantes/CargaMasiva.jsx';
import Login from '../paginas/auth/Login.jsx';
import { guardarSesion, limpiarSesion, obtenerSesion } from './sesion.js';

/**
 * Rutas reales por URL. Las de /admin exigen haber iniciado sesion; si la
 * API responde 401 en cualquier pantalla, se vuelve al login.
 */
const rutaPorClave = {
    usuarios: '/admin/usuarios',
    asignaturas: '/admin/asignaturas',
    bitacora: '/admin/bitacora',
    padron: '/admin/padron',
    login: '/login',
};

const rutaInicial = '/admin/usuarios';

function useNavegacionPorClave() {
    const navigate = useNavigate();

    return async (clave) => {
        if (clave === 'salir') {
            try {
                await window.axios.post('/api/auth/logout');
            } catch {
                // Aunque falle la llamada, en este navegador la sesion se cierra igual.
            }

            limpiarSesion();
            navigate('/login', { replace: true });
            return;
        }

        if (clave === 'login') {
            limpiarSesion();
        }

        const ruta = rutaPorClave[clave];

        if (ruta) {
            navigate(ruta);
        }
    };
}

function ManejadorSesionExpirada() {
    const navigate = useNavigate();

    React.useEffect(() => {
        const interceptores = window.axios?.interceptors?.response;

        if (!interceptores) {
            return undefined;
        }

        const id = interceptores.use(
            (respuesta) => respuesta,
            (error) => {
                const estado = error.response?.status;
                const url = error.config?.url ?? '';

                if ((estado === 401 || estado === 419) && !url.includes('/api/auth/')) {
                    limpiarSesion();
                    navigate('/login', { replace: true });
                }

                return Promise.reject(error);
            }
        );

        return () => interceptores.eject(id);
    }, [navigate]);

    return null;
}

function RutaProtegida({ children }) {
    const ubicacion = useLocation();

    if (!obtenerSesion()) {
        return <Navigate to="/login" replace state={{ desde: ubicacion.pathname }} />;
    }

    return children;
}

RutaProtegida.propTypes = {
    children: PropTypes.node.isRequired,
};

function PaginaUsuarios() {
    return <UsuariosRoles onNavigate={useNavegacionPorClave()} />;
}

function PaginaAsignaturas() {
    return <AsignaturasAmbientes onNavigate={useNavegacionPorClave()} />;
}

function PaginaBitacora() {
    return <Bitacora onNavigate={useNavegacionPorClave()} />;
}

function PaginaPadron() {
    return <CargaMasiva onNavigate={useNavegacionPorClave()} />;
}

function PaginaLogin() {
    const navigate = useNavigate();
    const ubicacion = useLocation();

    if (obtenerSesion()) {
        return <Navigate to={rutaInicial} replace />;
    }

    return (
        <Login
            onAutenticado={(usuario) => {
                guardarSesion(usuario);
                navigate(ubicacion.state?.desde ?? rutaInicial, { replace: true });
            }}
        />
    );
}

export default function Aplicacion() {
    return (
        <BrowserRouter>
            <ManejadorSesionExpirada />
            <Routes>
                <Route path="/login" element={<PaginaLogin />} />
                <Route
                    path="/admin/usuarios"
                    element={
                        <RutaProtegida>
                            <PaginaUsuarios />
                        </RutaProtegida>
                    }
                />
                <Route
                    path="/admin/asignaturas"
                    element={
                        <RutaProtegida>
                            <PaginaAsignaturas />
                        </RutaProtegida>
                    }
                />
                <Route
                    path="/admin/bitacora"
                    element={
                        <RutaProtegida>
                            <PaginaBitacora />
                        </RutaProtegida>
                    }
                />
                <Route
                    path="/admin/padron"
                    element={
                        <RutaProtegida>
                            <PaginaPadron />
                        </RutaProtegida>
                    }
                />
                <Route path="/" element={<Navigate to={rutaInicial} replace />} />
                <Route path="*" element={<Navigate to={rutaInicial} replace />} />
            </Routes>
        </BrowserRouter>
    );
}

import React from 'react';
import PropTypes from 'prop-types';
import { BrowserRouter, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import UsuariosRoles from '../paginas/admin/UsuariosRoles.jsx';
import AsignaturasAmbientes from '../paginas/admin/AsignaturasAmbientes.jsx';
import Bitacora from '../paginas/admin/Bitacora.jsx';
import CargaMasiva from '../paginas/estudiantes/CargaMasiva.jsx';
import RegistroEstudiantes from '../paginas/estudiantes/RegistroEstudiantes.jsx';
import ExamenesNormas from '../paginas/examenes/ExamenesNormasApi.jsx';
import ConsultaNormasControl from '../paginas/examenes/ConsultaNormasControl.jsx';
import Login from '../paginas/auth/Login.jsx';
import LayoutAdmin from './LayoutAdmin.jsx';
import PestanasPadron from './PestanasPadron.jsx';
import SinPermiso from './SinPermiso.jsx';
import {
    guardarSesion,
    limpiarSesion,
    obtenerSesion,
    rolDeSesion,
    tienePermiso,
} from './sesion.js';

/**
 * Rutas reales por URL. Las de /admin exigen haber iniciado sesion; si la
 * API responde 401 en cualquier pantalla, se vuelve al login.
 */
const rutaPorClave = {
    usuarios: '/admin/usuarios',
    asignaturas: '/admin/asignaturas',
    examenes: '/admin/examenes',
    controlNormas: '/control/normas',
    bitacora: '/admin/bitacora',
    padron: '/admin/padron',
    cargaMasiva: '/admin/padron/carga-masiva',
    login: '/login',
};

// Permiso que exige el backend en cada pantalla (HU-02).
const permisoPorClave = {
    usuarios: 'usuarios_roles',
    asignaturas: 'asignaturas_ambientes',
    examenes: 'examenes_normas',
    controlNormas: 'punto_control',
    bitacora: 'bitacora',
    padron: 'padron_estudiantes',
    cargaMasiva: 'padron_estudiantes',
};

const ordenDeEntrada = [
    'usuarios',
    'padron',
    'asignaturas',
    'examenes',
    'controlNormas',
    'bitacora',
];

// Tras iniciar sesion se entra por la primera pantalla que el rol tenga
// habilitada, no siempre por la de usuarios.
function rutaDeEntrada() {
    const clave = ordenDeEntrada.find((c) => tienePermiso(permisoPorClave[c]));

    return clave ? rutaPorClave[clave] : '/sin-permiso';
}

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

                // El rol no alcanza: se explica en pantalla en vez de dejar
                // la vista vacia (HU-02).
                if (estado === 403) {
                    navigate('/sin-permiso', {
                        state: {
                            mensaje: error.response?.data?.message,
                            permiso: error.response?.data?.permiso_requerido,
                        },
                    });
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

function RutaConPermiso({ permiso, children }) {
    const navigate = useNavigate();

    if (!tienePermiso(permiso)) {
        return (
            <SinPermiso
                permiso={permiso}
                onVolver={() => navigate(rutaDeEntrada(), { replace: true })}
            />
        );
    }

    return children;
}

RutaProtegida.propTypes = {
    children: PropTypes.node.isRequired,
};

RutaConPermiso.propTypes = {
    permiso: PropTypes.string.isRequired,
    children: PropTypes.node.isRequired,
};

function PaginaUsuarios() {
    const navegar = useNavegacionPorClave();

    return (
        <LayoutAdmin seleccionado="usuarios" onNavigate={navegar}>
            <UsuariosRoles onNavigate={navegar} />
        </LayoutAdmin>
    );
}

function PaginaAsignaturas() {
    return <AsignaturasAmbientes onNavigate={useNavegacionPorClave()} />;
}

function PaginaExamenes() {
    const navegar = useNavegacionPorClave();

    return (
        <LayoutAdmin seleccionado="examenes" onNavigate={navegar}>
            <ExamenesNormas />
        </LayoutAdmin>
    );
}

function PaginaConsultaNormasControl() {
    const navegar = useNavegacionPorClave();

    return (
        <LayoutAdmin seleccionado="controlNormas" onNavigate={navegar}>
            <ConsultaNormasControl />
        </LayoutAdmin>
    );
}

function PaginaBitacora() {
    return <Bitacora onNavigate={useNavegacionPorClave()} />;
}

function PaginaPadron() {
    const navegar = useNavegacionPorClave();

    return (
        <LayoutAdmin seleccionado="padron" onNavigate={navegar}>
            <PestanasPadron activa="padron" onNavigate={navegar} />
            <RegistroEstudiantes />
        </LayoutAdmin>
    );
}

function PaginaCargaMasiva() {
    return <CargaMasiva onNavigate={useNavegacionPorClave()} />;
}

function PaginaSinPermiso() {
    const navigate = useNavigate();
    const navegar = useNavegacionPorClave();
    const ubicacion = useLocation();
    const destino = rutaDeEntrada();

    // Si el rol no tiene ninguna pantalla de este sprint, no hay adonde
    // volver: lo unico sensato es cerrar la sesion.
    const sinNingunaSeccion = destino === '/sin-permiso';

    const mensaje =
        ubicacion.state?.mensaje ??
        (sinNingunaSeccion
            ? `Tu rol (${rolDeSesion() ?? 'sin rol asignado'}) todavía no tiene ninguna sección habilitada. Pide al administrador que revise sus permisos.`
            : undefined);

    return (
        <SinPermiso
            mensaje={mensaje}
            permiso={ubicacion.state?.permiso}
            textoBoton={sinNingunaSeccion ? 'Cerrar sesión' : 'Volver'}
            onVolver={() =>
                sinNingunaSeccion ? navegar('salir') : navigate(destino, { replace: true })
            }
        />
    );
}

function PaginaLogin() {
    const navigate = useNavigate();
    const ubicacion = useLocation();

    if (obtenerSesion()) {
        return <Navigate to={rutaDeEntrada()} replace />;
    }

    return (
        <Login
            onAutenticado={(usuario) => {
                guardarSesion(usuario);
                navigate(ubicacion.state?.desde ?? rutaDeEntrada(), { replace: true });
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
                    path="/admin/examenes"
                    element={
                        <RutaProtegida>
                            <RutaConPermiso permiso="examenes_normas">
                                <PaginaExamenes />
                            </RutaConPermiso>
                        </RutaProtegida>
                    }
                />
                <Route
                    path="/control/normas"
                    element={
                        <RutaProtegida>
                            <RutaConPermiso permiso="punto_control">
                                <PaginaConsultaNormasControl />
                            </RutaConPermiso>
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
                <Route
                    path="/admin/padron/carga-masiva"
                    element={
                        <RutaProtegida>
                            <PaginaCargaMasiva />
                        </RutaProtegida>
                    }
                />
                <Route
                    path="/sin-permiso"
                    element={
                        <RutaProtegida>
                            <PaginaSinPermiso />
                        </RutaProtegida>
                    }
                />
                <Route path="/" element={<Navigate to={rutaDeEntrada()} replace />} />
                <Route path="*" element={<Navigate to={rutaDeEntrada()} replace />} />
            </Routes>
        </BrowserRouter>
    );
}

import { Suspense, lazy } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes, useNavigate } from 'react-router-dom';
import { ProveedorSesion, PantallaDeCarga, usarSesion } from '../sesion/SesionContexto';
import RutaProtegida from '../sesion/RutaProtegida';
import EsquemaApp from '../componentes/EsquemaApp';
import EstadoCarga from '../componentes/EstadoCarga';
import Boton from '../componentes/Boton';
import Escudo from '../componentes/Escudo';
import { VISTAS, destinoDe, entradaDe } from './vistas';
import Login from '../paginas/auth/Login';
import PrimerIngreso from '../paginas/auth/PrimerIngreso';
import RestablecerContrasena from '../paginas/auth/RestablecerContrasena';
import AccesoAula from '../paginas/auth/AccesoAula';
import AccesoEstudiante from '../paginas/estudiante/AccesoEstudiante';

const PortalEstudiante = lazy(() => import('../paginas/estudiante/PortalEstudiante'));

const PuntoControl = lazy(() => import('../paginas/ingreso/PuntoControl'));
const Asistencia = lazy(() => import('../paginas/monitoreo/Asistencia'));

// Un componente por ruta. Quién ve cada una sale de navegacion/vistas.js.
export const PAGINAS = {
    '/periodo': lazy(() => import('../paginas/admin/Periodo')),
    '/oferta': lazy(() => import('../paginas/admin/Oferta')),
    '/aulas': lazy(() => import('../paginas/admin/Aulas')),
    '/docentes': lazy(() => import('../paginas/admin/Docentes')),
    '/estudiantes': lazy(() => import('../paginas/estudiantes/Padron')),
    '/reportes': lazy(() => import('../paginas/reportes/Reportes')),
    '/admin': lazy(() => import('../paginas/admin/Usuarios')),
    '/bitacora': lazy(() => import('../paginas/admin/Bitacora')),
    '/respaldo': lazy(() => import('../paginas/admin/Respaldo')),
    '/examenes': lazy(() => import('../paginas/examenes/Examenes')),
    '/examenes/nuevo': lazy(() => import('../paginas/examenes/RegistrarExamen')),
    '/examenes/detalle': lazy(() => import('../paginas/examenes/DetalleExamen')),
    '/habilitacion': lazy(() => import('../paginas/habilitacion/Habilitacion')),
    '/codigos-qr': lazy(() => import('../paginas/docente/CodigosQr')),
    '/en-curso': lazy(() => import('../paginas/docente/EnCurso')),
    '/en-curso/aula': Asistencia,
    '/control': PuntoControl,
    '/mis-grupos': lazy(() => import('../paginas/docente/MisGrupos')),
    '/mis-reportes': lazy(() => import('../paginas/reportes/ReportesDocente')),
    '/ingreso': PuntoControl,
    '/mi-aula': Asistencia,
};

// El armazón con menú: se monta una vez y cambia solo la página.
function ConMenu() {
    return (
        <RutaProtegida>
            <EsquemaApp>
                <Suspense fallback={<EstadoCarga cargando />}>
                    <Outlet />
                </Suspense>
            </EsquemaApp>
        </RutaProtegida>
    );
}

// `/` y cualquier dirección desconocida: la entrada de la cuenta o el acceso.
function Inicio() {
    const { usuario } = usarSesion();
    return <Navigate to={destinoDe(usuario)} replace />;
}

// Una cuenta sin ninguna pantalla en el menú.
function SinAcceso() {
    const { usuario, salir } = usarSesion();
    const navegar = useNavigate();

    if (!usuario) return <Navigate to="/login" replace />;
    if (usuario.debe_cambiar_contrasena) return <Navigate to="/primer-ingreso" replace />;
    if (entradaDe(usuario)) return <Navigate to={entradaDe(usuario)} replace />;

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-fondo px-4 text-center">
            <Escudo className="h-16" />
            <div className="min-w-0 max-w-full">
                <h1 className="text-lg font-bold text-umss-marino">Sin pantallas asignadas</h1>
                <p className="mt-1 break-words text-sm text-slate-600">
                    {[usuario.nombre, usuario.rol].filter(Boolean).join(' · ')}
                </p>
            </div>
            <Boton
                type="button"
                variante="secundario"
                onClick={async () => {
                    try {
                        try {
                            await salir();
                            navegar('/login', { replace: true });
                        } catch {
                            // Sin conexión la sesión sigue: se queda aquí.
                        }
                    } catch {
                        // Sin conexión la sesión sigue: se queda aquí.
                    }
                }}
            >
                Cerrar sesión
            </Boton>
        </div>
    );
}

// La tabla de rutas, sin el enrutador: las pruebas la montan en memoria.
export function Rutas() {
    return (
        <Suspense fallback={<PantallaDeCarga />}>
            <Routes>
                <Route path="/login" element={<Login />} />
                <Route path="/primer-ingreso" element={<PrimerIngreso />} />
                <Route path="/restablecer-contrasena" element={<RestablecerContrasena />} />
                <Route path="/acceso-aula" element={<AccesoAula />} />
                <Route path="/estudiante/ingreso" element={<AccesoEstudiante />} />
                <Route path="/estudiante" element={<PortalEstudiante />} />
                <Route path="/estudiante/foto" element={<PortalEstudiante />} />
                <Route path="/estudiante/*" element={<Navigate to="/estudiante" replace />} />
                <Route path="/sin-acceso" element={<SinAcceso />} />
                <Route element={<ConMenu />}>
                    {VISTAS.map((v) => {
                        const Pagina = PAGINAS[v.ruta];
                        return (
                            <Route
                                key={v.ruta}
                                path={v.ruta}
                                element={
                                    <RutaProtegida ruta={v.ruta}>
                                        <Pagina />
                                    </RutaProtegida>
                                }
                            />
                        );
                    })}
                </Route>
                <Route path="*" element={<Inicio />} />
            </Routes>
        </Suspense>
    );
}

export default function Aplicacion() {
    return (
        <BrowserRouter>
            <ProveedorSesion>
                <Rutas />
            </ProveedorSesion>
        </BrowserRouter>
    );
}

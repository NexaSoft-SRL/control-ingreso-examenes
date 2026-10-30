import { Suspense, lazy } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes, useNavigate } from 'react-router-dom';
import { ProveedorSesion, PantallaDeCarga, usarSesion } from '../sesion/SesionContexto';
import RutaProtegida from '../sesion/RutaProtegida';
import EsquemaApp from '../componentes/EsquemaApp';
import EstadoCarga from '../componentes/EstadoCarga';
import Boton from '../componentes/Boton';
import Logo from '../componentes/Logo';
import { VISTAS, destinoDe, entradaDe } from './vistas';
import CambiarContrasena from '../paginas/auth/CambiarContrasena';
import RecuperarContrasena from '../paginas/auth/RecuperarContrasena';

const Login = lazy(() => import('../paginas/auth/Login'));
const AccesoAula = lazy(() => import('../paginas/auth/AccesoAula'));
const AccesoEstudiante = lazy(() => import('../paginas/estudiante/AccesoEstudiante'));
const PortalEstudiante = lazy(() => import('../paginas/estudiante/PortalEstudiante'));

// Un componente por ruta. Quién ve cada una sale de navegacion/vistas.js.
export const PAGINAS = {
    '/periodo': lazy(() => import('../paginas/admin/Periodo')),
    '/oferta': lazy(() => import('../paginas/admin/Oferta')),
    '/aulas': lazy(() => import('../paginas/admin/Aulas')),
    '/docentes': lazy(() => import('../paginas/admin/Docentes')),
    '/estudiantes': lazy(() => import('../paginas/estudiantes/Padron')),
    '/examenes': lazy(() => import('../paginas/examenes/Examenes')),
    '/examenes/nuevo': lazy(() => import('../paginas/examenes/RegistrarExamen')),
    '/reportes': lazy(() => import('../paginas/examenes/Reportes')),
    '/reportes-universidad': lazy(() => import('../paginas/examenes/ReportesUniversidad')),
    '/bitacora-examen': lazy(() => import('../paginas/examenes/BitacoraExamen')),
    '/habilitacion': lazy(() => import('../paginas/habilitacion/Habilitacion')),
    '/codigos-qr': lazy(() => import('../paginas/examenes/CodigosQr')),
    '/en-curso': lazy(() => import('../paginas/monitoreo/EnCurso')),
    '/punto-control': lazy(() => import('../paginas/ingreso/PuntoControl')),
    '/en-curso/aula': lazy(() => import('../paginas/ingreso/Seguimiento')),
    '/mi-aula': lazy(() => import('../paginas/ingreso/Seguimiento')),
    '/mis-grupos': lazy(() => import('../paginas/docente/MisGrupos')),
    '/admin': lazy(() => import('../paginas/admin/Usuarios')),
    '/bitacora': lazy(() => import('../paginas/admin/Bitacora')),
    '/respaldo': lazy(() => import('../paginas/admin/Respaldo')),
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
    return (
        <Navigate
            to={usuario?.debe_cambiar_contrasena ? '/cambiar-contrasena' : destinoDe(usuario)}
            replace
        />
    );
}

function CambioContrasenaForzado() {
    const { usuario, cargando } = usarSesion();

    if (cargando) return <PantallaDeCarga />;
    if (!usuario) return <Navigate to="/login" replace />;
    if (!usuario.debe_cambiar_contrasena) return <Navigate to={destinoDe(usuario)} replace />;

    return <CambiarContrasena />;
}

// Una cuenta sin ninguna pantalla en el menú.
function SinAcceso() {
    const { usuario, salir } = usarSesion();
    const navegar = useNavigate();

    if (!usuario) return <Navigate to="/login" replace />;
    if (entradaDe(usuario)) return <Navigate to={entradaDe(usuario)} replace />;

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-slate-100 px-4 text-center">
            <Logo className="h-14 w-14" />
            <div className="min-w-0 max-w-full">
                <h1 className="text-lg font-semibold text-slate-900">Sin pantallas asignadas</h1>
                <p className="mt-1 break-words text-sm text-slate-600">
                    {[usuario.nombre, usuario.rol].filter(Boolean).join(' · ')}
                </p>
            </div>
            <Boton
                type="button"
                variante="secundario"
                onClick={async () => {
                    await salir();
                    navegar('/login', { replace: true });
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
                <Route path="/acceso-aula" element={<AccesoAula />} />
                <Route path="/recuperar-contrasena" element={<RecuperarContrasena />} />
                <Route path="/restablecer-contrasena" element={<RecuperarContrasena />} />
                <Route path="/cambiar-contrasena" element={<CambioContrasenaForzado />} />
                <Route path="/sin-acceso" element={<SinAcceso />} />
                {/* El estudiante entra sin cuenta, con un código por correo. */}
                <Route path="/estudiante/ingreso" element={<AccesoEstudiante />} />
                <Route path="/estudiante" element={<PortalEstudiante />} />
                <Route path="/estudiante/foto" element={<PortalEstudiante />} />
                <Route path="/estudiante/*" element={<Navigate to="/estudiante" replace />} />
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

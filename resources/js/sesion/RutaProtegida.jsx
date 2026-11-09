import PropTypes from 'prop-types';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { PantallaDeCarga, usarSesion } from './SesionContexto';
import SinPermiso from '../componentes/SinPermiso';
import { gemelaDe, vistasDe } from '../navegacion/vistas';

// Exige sesión, contraseña propia y, si se indica, que la `ruta` sea una
// vista de la cuenta o que tenga el `permiso`. Sin sesión va a /login; con
// contraseña temporal, a /primer-ingreso; una cuenta sin ninguna vista, a
// /sin-acceso. Ante una vista que la cuenta no puede abrir dibuja «Sin
// permiso» en su lugar (negativa explícita, no una redirección), salvo que
// tenga la gemela (`/control` ↔ `/ingreso`, `/en-curso` ↔ `/mi-aula`): ahí
// va a ella con los mismos parámetros. Sin `children` dibuja las rutas hijas.
export default function RutaProtegida({ ruta, permiso, children }) {
    const { usuario, cargando, puede } = usarSesion();
    const ubicacion = useLocation();

    if (cargando) return <PantallaDeCarga />;
    if (!usuario) return <Navigate to="/login" replace state={{ desde: ubicacion.pathname }} />;
    if (usuario.debe_cambiar_contrasena) return <Navigate to="/primer-ingreso" replace />;

    const vistas = vistasDe(usuario);
    if (vistas.length === 0) return <Navigate to="/sin-acceso" replace />;

    if (ruta && !vistas.some((v) => v.ruta === ruta)) {
        const gemela = gemelaDe(usuario, ruta);
        if (gemela) return <Navigate to={`${gemela}${ubicacion.search}`} replace />;
        return <SinPermiso />;
    }
    if (permiso && !puede(permiso)) return <SinPermiso />;

    return children ?? <Outlet />;
}

RutaProtegida.propTypes = {
    ruta: PropTypes.string,
    permiso: PropTypes.string,
    children: PropTypes.node,
};

import PropTypes from 'prop-types';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { PantallaDeCarga, usarSesion } from './SesionContexto';
import SinPermiso from '../componentes/SinPermiso';
import { vistasDe } from '../navegacion/vistas';

// Exige sesión y, si se indica, que la `ruta` sea una vista de la cuenta o
// que tenga el `permiso`. Sin sesión va a /login; una cuenta sin ninguna
// vista, a /sin-acceso; ante una vista que la cuenta no puede abrir dibuja
// «Sin permiso» en su lugar (negativa explícita, no una redirección).
// Sin `children` dibuja las rutas hijas.
export default function RutaProtegida({ ruta, permiso, children }) {
    const { usuario, cargando, puede } = usarSesion();
    const ubicacion = useLocation();

    let tieneTokenColaborador = false;
    try {
        tieneTokenColaborador =
            (ruta === '/punto-control' || ubicacion.pathname === '/punto-control') &&
            Boolean(localStorage.getItem('codigo_aula_token'));
    } catch {
        // Ignorar si localStorage no está disponible
    }

    if (tieneTokenColaborador && !usuario) {
        return children ?? <Outlet />;
    }

    if (cargando) return <PantallaDeCarga />;
    if (!usuario) return <Navigate to="/login" replace state={{ desde: ubicacion.pathname }} />;
    if (usuario.debe_cambiar_contrasena && ubicacion.pathname !== '/cambiar-contrasena') {
        return <Navigate to="/cambiar-contrasena" replace />;
    }

    const vistas = vistasDe(usuario);
    if (vistas.length === 0) return <Navigate to="/sin-acceso" replace />;

    const permitida =
        (!ruta || vistas.some((v) => v.ruta === ruta)) && (!permiso || puede(permiso));
    if (!permitida) return <SinPermiso />;

    return children ?? <Outlet />;
}

RutaProtegida.propTypes = {
    ruta: PropTypes.string,
    permiso: PropTypes.string,
    children: PropTypes.node,
};

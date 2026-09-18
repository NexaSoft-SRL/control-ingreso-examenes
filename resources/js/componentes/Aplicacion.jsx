import { BrowserRouter, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import UsuariosRoles from '../paginas/admin/UsuariosRoles.jsx';
import AsignaturasAmbientes from '../paginas/admin/AsignaturasAmbientes.jsx';
import Bitacora from '../paginas/admin/Bitacora.jsx';
import CargaMasiva from '../paginas/estudiantes/CargaMasiva.jsx';
import Login from '../paginas/auth/Login.jsx';

/**
 * Rutas reales por URL (antes era un switch en memoria sin cambiar la
 * direccion del navegador). Estructura minima por ahora: cuando se agreguen
 * las demas paginas (examenes, etc.) esto se termina de definir.
 */
const rutaPorClave = {
    usuarios: '/admin/usuarios',
    asignaturas: '/admin/asignaturas',
    bitacora: '/admin/bitacora',
    padron: '/admin/padron',
};

function useNavegacionPorClave() {
    const navigate = useNavigate();

    return (clave) => {
        const ruta = rutaPorClave[clave];

        if (ruta) {
            navigate(ruta);
        }
    };
}

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

    return <Login onAutenticado={() => navigate('/admin/usuarios')} />;
}

export default function Aplicacion() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<PaginaLogin />} />
                <Route path="/admin/usuarios" element={<PaginaUsuarios />} />
                <Route path="/admin/asignaturas" element={<PaginaAsignaturas />} />
                <Route path="/admin/bitacora" element={<PaginaBitacora />} />
                <Route path="/admin/padron" element={<PaginaPadron />} />
                <Route path="/" element={<Navigate to="/admin/usuarios" replace />} />
                <Route path="*" element={<Navigate to="/admin/usuarios" replace />} />
            </Routes>
        </BrowserRouter>
    );
}

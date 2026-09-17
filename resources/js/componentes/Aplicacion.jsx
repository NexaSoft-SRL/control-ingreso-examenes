<<<<<<< HEAD
import UsuariosRoles from "../paginas/admin/UsuariosRoles.jsx";

export default function Aplicacion() {
    return <UsuariosRoles />;
}
=======
import React from 'react';
import UsuariosRoles from '../paginas/admin/UsuariosRoles.jsx';
import AsignaturasAmbientes from '../paginas/admin/AsignaturasAmbientes.jsx';

export default function Aplicacion() {
    const [pagina, setPagina] = React.useState('usuarios');

    if (pagina === 'asignaturas') {
        return <AsignaturasAmbientes onNavigate={setPagina} />;
    }

    return <UsuariosRoles onNavigate={setPagina} />;
}
>>>>>>> ffeb480cfb257eca3b52b96ed9ba0437e1320e55

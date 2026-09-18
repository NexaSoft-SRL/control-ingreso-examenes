import React from 'react';
import UsuariosRoles from '../paginas/admin/UsuariosRoles.jsx';
import AsignaturasAmbientes from '../paginas/admin/AsignaturasAmbientes.jsx';
import RegistroEstudiantes from '../paginas/estudiantes/RegistroEstudiantes.jsx';

export default function Aplicacion() {
    const [pagina, setPagina] = React.useState('estudiantes');

    if (pagina === 'estudiantes') {
        return <RegistroEstudiantes onNavigate={setPagina} />;
    }

    if (pagina === 'asignaturas') {
        return <AsignaturasAmbientes onNavigate={setPagina} />;
    }

    return <UsuariosRoles onNavigate={setPagina} />;
}
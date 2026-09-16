import React from "react";
import UsuariosRoles from "../paginas/admin/UsuariosRoles.jsx";
import AsignaturasAmbientes from "../paginas/admin/AsignaturasAmbientes.jsx";

export default function Aplicacion() {
    const [pagina, setPagina] = React.useState("usuarios");

    if (pagina === "asignaturas") {
        return (
            <AsignaturasAmbientes
                onNavigate={setPagina}
            />
        );
    }

    return (
        <UsuariosRoles
            onNavigate={setPagina}
        />
    );
}
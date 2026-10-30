import {
    CalendarRange,
    MapPinned,
    UserCog,
    GraduationCap,
    Settings,
    History,
    UsersRound,
    FileText,
    UserCheck,
    ScanLine,
    Activity,
    Radio,
    QrCode,
    BarChart3,
} from 'lucide-react';

// Fuente única de las pantallas. El menú (EsquemaApp), las rutas
// (Aplicacion) y la protección (RutaProtegida) leen de aquí.
//
// Una cuenta ve una vista si tiene su `permiso`: los roles se crean y se
// editan, así que nada depende del nombre del rol.
//
// `grupo` arma las secciones del menú; `movil` elige lo que entra en la barra
// inferior del teléfono; `oculta` deja la vista con ruta pero fuera del menú
// y `padre` es la vista del menú que queda marcada. `permiso` es la clave
// que exige la API para esa pantalla.
export const VISTAS = [
    {
        ruta: '/periodo',
        titulo: 'Período',
        Icono: CalendarRange,
        grupo: 'Preparación',
        movil: true,
        permiso: 'periodo_oferta',
    },

    {
        ruta: '/oferta',
        titulo: 'Oferta',
        Icono: GraduationCap,
        grupo: 'Preparación',
        movil: true,
        permiso: 'periodo_oferta',
    },

    {
        ruta: '/aulas',
        titulo: 'Aulas y mapa',
        Icono: MapPinned,
        grupo: 'Preparación',
        permiso: 'aulas_docentes',
    },
    {
        ruta: '/docentes',
        titulo: 'Docentes',
        Icono: UserCog,
        grupo: 'Preparación',
        movil: true,
        permiso: 'aulas_docentes',
    },
    {
        ruta: '/estudiantes',
        titulo: 'Padrón',
        Icono: GraduationCap,
        grupo: 'Preparación',
        movil: true,
        permiso: 'padron_estudiantes',
    },
    {
        ruta: '/examenes',
        titulo: 'Exámenes',
        Icono: FileText,
        grupo: 'Docencia',
        movil: true,
        permiso: 'examenes',
    },
    {
        ruta: '/reportes',
        titulo: 'Reportes',
        Icono: BarChart3,
        grupo: 'Docencia',
        movil: true,
        permiso: 'reportes_examenes',
    },
    // Registrar examen y Habilitación son de un examen: se llega a ellas
    // desde la lista de exámenes, no desde el menú (`oculta`).
    {
        ruta: '/examenes/nuevo',
        titulo: 'Registrar examen',
        Icono: FileText,
        grupo: 'Docencia',
        oculta: true,
        padre: '/examenes',
        permiso: 'examenes',
    },
    {
        ruta: '/habilitacion',
        titulo: 'Habilitación',
        Icono: UserCheck,
        grupo: 'Docencia',
        oculta: true,
        padre: '/examenes',
        permiso: 'habilitacion',
    },
    // HU-20: los códigos QR también son de un examen: se llega desde su
    // acción «Emitir códigos» o su enlace «Códigos QR» en la lista.
    {
        ruta: '/codigos-qr',
        titulo: 'Códigos QR',
        Icono: QrCode,
        grupo: 'Docencia',
        oculta: true,
        padre: '/examenes',
        permiso: 'codigos_qr',
    },
    // HU-22: el examen en curso del docente. Es de quien ve sus exámenes.
    {
        ruta: '/en-curso',
        titulo: 'En curso',
        Icono: Radio,
        grupo: 'Exámenes',
        movil: true,
        permiso: 'examenes',
    },
    {
        ruta: '/punto-control',
        titulo: 'Punto de control',
        Icono: ScanLine,
        grupo: 'Exámenes',
        movil: true,
        permiso: 'punto_control',
    },
    // HU-21: la lista del aula que sigue el docente desde «En curso».
    {
        ruta: '/en-curso/aula',
        titulo: 'Lista del aula',
        Icono: Activity,
        grupo: 'Exámenes',
        oculta: true,
        padre: '/en-curso',
        permiso: 'examenes',
    },
    {
        ruta: '/mi-aula',
        titulo: 'Mi aula',
        Icono: Activity,
        grupo: 'Exámenes',
        movil: true,
        permiso: 'seguimiento_vivo',
    },
    {
        ruta: '/mis-grupos',
        titulo: 'Mis grupos',
        Icono: UsersRound,
        grupo: 'Docencia',
        movil: true,
        permiso: 'mis_grupos',
    },
    {
        ruta: '/admin',
        titulo: 'Usuarios y roles',
        Icono: Settings,
        grupo: 'Sistema',
        permiso: 'usuarios_roles',
    },
    {
        ruta: '/bitacora',
        titulo: 'Bitácora',
        Icono: History,
        grupo: 'Sistema',
        permiso: 'bitacora',
    },
];

function puede(usuario, permiso) {
    return Array.isArray(usuario?.permisos) && usuario.permisos.includes(permiso);
}

// Las vistas de la cuenta (con las ocultas): aquellas cuyo permiso tiene.
export function vistasDe(usuario) {
    return usuario ? VISTAS.filter((v) => puede(usuario, v.permiso)) : [];
}

// La pantalla de entrada: la primera del menú. Nula si no tiene ninguna.
export function entradaDe(usuario) {
    return vistasDe(usuario).find((v) => !v.oculta)?.ruta ?? null;
}

// La vista a la que pertenece una dirección ('/examenes/nuevo' antes que '/examenes').
export function vistaDeRuta(vistas, ruta) {
    return (
        [...vistas]
            .sort((a, b) => b.ruta.length - a.ruta.length)
            .find((v) => ruta === v.ruta || ruta.startsWith(`${v.ruta}/`)) ?? null
    );
}

// A dónde va una cuenta recién autenticada.
export function destinoDe(usuario) {
    if (!usuario) return '/login';
    return entradaDe(usuario) ?? '/sin-acceso';
}

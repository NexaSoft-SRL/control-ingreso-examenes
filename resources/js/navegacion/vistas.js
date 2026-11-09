import {
    CalendarRange,
    BookMarked,
    MapPinned,
    UserCog,
    GraduationCap,
    Settings,
    History,
    DatabaseBackup,
    UsersRound,
    FileText,
    ListChecks,
    QrCode,
    BarChart3,
    ScanLine,
    Radio,
    UserCheck,
} from 'lucide-react';

// Fuente única de las pantallas. El menú (EsquemaApp), las rutas
// (Aplicacion) y la protección (RutaProtegida) leen de aquí.
//
// Una cuenta ve una vista si tiene su `permiso`: los roles se crean y se
// editan, así que nada depende del nombre del rol.
//
// `grupo` arma las secciones del menú; `movil` elige lo que entra en la barra
// inferior del teléfono, donde va `corto` si la vista lo tiene; `oculta` deja la vista con ruta pero fuera del menú
// y `padre` es la vista del menú que queda marcada. `enVivo` lleva «En vivo»
// mientras hay un examen abierto (navegacion/usarEnVivo). `permiso` es la clave
// que exige la API para esa pantalla.
//
// El punto de control y el seguimiento tienen dos juegos de pantallas con el
// mismo permiso: el de quien toma el examen (`con: 'examenes'`: En curso,
// Asistencia del aula, Control) y el de quien solo atiende un aula
// (`sin: 'examenes'`: Control, Mi aula). `con` exige además ese otro
// permiso; `sin` quita la vista a quien lo tiene. `gemela` es la misma
// pantalla en el otro juego: RutaProtegida lleva a ella a quien abre la que
// no le toca.
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
        Icono: BookMarked,
        grupo: 'Preparación',
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
        ruta: '/examenes/nuevo',
        titulo: 'Registrar examen',
        Icono: FileText,
        grupo: 'Docencia',
        oculta: true,
        padre: '/examenes',
        permiso: 'examenes',
    },
    {
        ruta: '/examenes/detalle',
        titulo: 'Examen',
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
    {
        ruta: '/codigos-qr',
        titulo: 'Códigos QR',
        Icono: QrCode,
        grupo: 'Docencia',
        oculta: true,
        padre: '/examenes',
        permiso: 'codigos_qr',
    },
    {
        ruta: '/en-curso',
        titulo: 'En curso',
        enVivo: true,
        Icono: Radio,
        grupo: 'Docencia',
        movil: true,
        permiso: 'seguimiento_vivo',
        con: 'examenes',
        gemela: '/mi-aula',
    },
    {
        ruta: '/en-curso/aula',
        titulo: 'Asistencia del aula',
        Icono: ListChecks,
        grupo: 'Docencia',
        oculta: true,
        padre: '/en-curso',
        permiso: 'seguimiento_vivo',
        con: 'examenes',
        gemela: '/mi-aula',
    },
    {
        ruta: '/control',
        titulo: 'Punto de control',
        enVivo: true,
        corto: 'Control',
        Icono: ScanLine,
        grupo: 'Docencia',
        movil: true,
        permiso: 'punto_control',
        con: 'examenes',
        gemela: '/ingreso',
    },
    {
        ruta: '/ingreso',
        titulo: 'Punto de control',
        enVivo: true,
        corto: 'Control',
        Icono: ScanLine,
        grupo: 'Docencia',
        movil: true,
        permiso: 'punto_control',
        sin: 'examenes',
        gemela: '/control',
    },
    {
        ruta: '/mi-aula',
        titulo: 'Mi aula',
        enVivo: true,
        Icono: ListChecks,
        grupo: 'Docencia',
        movil: true,
        permiso: 'seguimiento_vivo',
        sin: 'examenes',
        gemela: '/en-curso',
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
        ruta: '/mis-reportes',
        titulo: 'Reportes',
        Icono: BarChart3,
        grupo: 'Docencia',
        permiso: 'reportes_examenes',
    },
    {
        ruta: '/reportes',
        titulo: 'Reportes',
        Icono: BarChart3,
        grupo: 'Sistema',
        movil: true,
        permiso: 'reportes_universidad',
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
    {
        ruta: '/respaldo',
        titulo: 'Respaldo',
        Icono: DatabaseBackup,
        grupo: 'Sistema',
        permiso: 'respaldo_restauracion',
    },
];

function puede(usuario, permiso) {
    return Array.isArray(usuario?.permisos) && usuario.permisos.includes(permiso);
}

function leToca(usuario, vista) {
    return (
        puede(usuario, vista.permiso) &&
        (!vista.con || puede(usuario, vista.con)) &&
        (!vista.sin || !puede(usuario, vista.sin))
    );
}

// Las vistas de la cuenta (con las ocultas): aquellas cuyo permiso tiene.
export function vistasDe(usuario) {
    return usuario ? VISTAS.filter((v) => leToca(usuario, v)) : [];
}

// La pantalla de entrada: la primera del menú. Nula si no tiene ninguna.
export function entradaDe(usuario) {
    return vistasDe(usuario).find((v) => !v.oculta)?.ruta ?? null;
}

// La vista a la que pertenece una dirección ('/en-curso/aula' antes que '/en-curso').
export function vistaDeRuta(vistas, ruta) {
    return (
        [...vistas]
            .sort((a, b) => b.ruta.length - a.ruta.length)
            .find((v) => ruta === v.ruta || ruta.startsWith(`${v.ruta}/`)) ?? null
    );
}

// La misma pantalla en el juego que sí le toca a la cuenta, o nulo.
export function gemelaDe(usuario, ruta) {
    const gemela = VISTAS.find((v) => v.ruta === ruta)?.gemela;
    return gemela && vistasDe(usuario).some((v) => v.ruta === gemela) ? gemela : null;
}

// A dónde va una cuenta recién autenticada.
export function destinoDe(usuario) {
    if (!usuario) return '/login';
    if (usuario.debe_cambiar_contrasena) return '/primer-ingreso';
    return entradaDe(usuario) ?? '/sin-acceso';
}

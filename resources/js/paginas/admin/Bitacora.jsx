import React from 'react';
import PropTypes from 'prop-types';

/**
 * Bitacora (HU-07), lado Frontend. El backend de esta historia (Jofre) hoy
 * solo registra operaciones automaticamente; todavia no existe un endpoint
 * para listarlas ni filtrarlas (no hay BitacoraController ni ruta GET). Esto
 * es el esqueleto de la pantalla con datos de ejemplo, listo para cambiar la
 * fuente de datos por una llamada real en cuanto ese endpoint exista.
 *
 * El filtro por usuario y por rango de fechas ya funciona sobre estos datos
 * de ejemplo, para no tener que rehacer esa logica despues.
 */
const eventosIniciales = [
    {
        fecha: '2026-11-18T10:14:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Generación de códigos QR',
        entidad: 'Redes de Computadoras - Examen Final',
    },
    {
        fecha: '2026-11-18T09:45:00',
        usuario: 'Ing. Patricia Villarroel Siles',
        accion: 'Habilitación de estudiante',
        entidad: 'Estudiante 202104821 (Alvarado Claros)',
    },
    {
        fecha: '2026-11-18T08:30:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Inicio de sesión',
        entidad: 'Cuenta Administrador',
    },
    {
        fecha: '2026-11-17T16:20:00',
        usuario: 'Lic. Marco Antonio Arnez Claros',
        accion: 'Registro de examen',
        entidad: 'Base de Datos I - 2do Parcial',
    },
    {
        fecha: '2026-11-17T14:15:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Carga masiva de padrón',
        entidad: 'Archivo padron_2026_fcyt.csv (184 reg.)',
    },
    {
        fecha: '2026-11-16T11:05:00',
        usuario: 'Dr. Carlos Eduardo Vargas Rojas',
        accion: 'Modificación de aula',
        entidad: 'Laboratorio de Sistemas 1 (Mantenimiento)',
    },
    {
        fecha: '2026-11-16T09:12:00',
        usuario: 'Ing. Marcelo Guzmán Flores',
        accion: 'Habilitación de estudiante',
        entidad: 'Estudiante 201901349 (Camacho Zeballos)',
    },
    {
        fecha: '2026-11-15T17:50:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Actualización de rol',
        entidad: 'Lic. Valeria Bustamante Torrico (Personal de control)',
    },
    {
        fecha: '2026-11-15T15:30:00',
        usuario: 'Ing. Patricia Villarroel Siles',
        accion: 'Inicio de sesión',
        entidad: 'Cuenta Docente',
    },
    {
        fecha: '2026-11-14T10:00:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Respaldo del sistema',
        entidad: 'Backup_FCyT_20261114.sql',
    },
];

const totalEventosDelServidor = 248;

function formatearFechaHora(isoFecha) {
    const fecha = new Date(isoFecha);
    const meses = [
        'Ene',
        'Feb',
        'Mar',
        'Abr',
        'May',
        'Jun',
        'Jul',
        'Ago',
        'Sep',
        'Oct',
        'Nov',
        'Dic',
    ];

    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = meses[fecha.getMonth()];
    const horas = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');

    return `${dia}/${mes}/${fecha.getFullYear()} ${horas}:${minutos}`;
}

function Bitacora({ onNavigate }) {
    const usuarios = React.useMemo(
        () => [...new Set(eventosIniciales.map((evento) => evento.usuario))],
        []
    );

    const [usuarioFiltro, setUsuarioFiltro] = React.useState('');
    const [desde, setDesde] = React.useState('');
    const [hasta, setHasta] = React.useState('');
    const [filtrosAplicados, setFiltrosAplicados] = React.useState({
        usuario: '',
        desde: '',
        hasta: '',
    });

    const eventosFiltrados = React.useMemo(() => {
        return eventosIniciales.filter((evento) => {
            if (filtrosAplicados.usuario && evento.usuario !== filtrosAplicados.usuario) {
                return false;
            }

            const fechaEvento = evento.fecha.slice(0, 10);

            if (filtrosAplicados.desde && fechaEvento < filtrosAplicados.desde) {
                return false;
            }

            if (filtrosAplicados.hasta && fechaEvento > filtrosAplicados.hasta) {
                return false;
            }

            return true;
        });
    }, [filtrosAplicados]);

    function manejarFiltrar(evento) {
        evento.preventDefault();
        setFiltrosAplicados({ usuario: usuarioFiltro, desde, hasta });
    }

    return (
        <div style={styles.app}>
            <aside style={styles.sidebar}>
                <div style={styles.logoContainer}>
                    <div style={styles.logo}>✓</div>

                    <div>
                        <div style={styles.logoTitle}>UMSS FCyT</div>
                        <div style={styles.logoSubtitle}>CONTROL DE INGRESO</div>
                    </div>
                </div>

                <div style={styles.menuSection}>
                    <div style={styles.menuTitle}>ADMINISTRADOR</div>

                    <MenuItem icon="♙" text="Padrón" onClick={() => onNavigate?.('padron')} />
                    <MenuItem
                        icon="▤"
                        text="Asignaturas y ambientes"
                        onClick={() => onNavigate?.('asignaturas')}
                    />
                    <MenuItem icon="⌗" text="Códigos QR" onClick={() => onNavigate?.('qr')} />
                    <MenuItem
                        icon="♙"
                        text="Usuarios y roles"
                        onClick={() => onNavigate?.('usuarios')}
                    />
                    <MenuItem icon="▧" text="Bitácora" selected />
                    <MenuItem icon="↻" text="Respaldo" onClick={() => onNavigate?.('respaldo')} />
                </div>
            </aside>

            <main style={styles.main}>
                <header style={styles.header}>
                    <div style={styles.headerTitle}>
                        <span style={styles.headerIcon}>♢</span>
                        Sistema Institucional de Verificación
                    </div>

                    <div style={styles.userCircle}>●</div>
                </header>

                <section style={styles.content}>
                    <h1 style={styles.title}>Bitácora</h1>
                    <p style={styles.description}>Registro de eventos y operaciones del sistema</p>

                    <form style={styles.filtros} onSubmit={manejarFiltrar}>
                        <div style={styles.campoFiltro}>
                            <label style={styles.etiquetaFiltro} htmlFor="usuario-filtro">
                                Usuario
                            </label>

                            <select
                                id="usuario-filtro"
                                style={styles.selectFiltro}
                                value={usuarioFiltro}
                                onChange={(e) => setUsuarioFiltro(e.target.value)}
                            >
                                <option value="">Todos los usuarios</option>
                                {usuarios.map((usuario) => (
                                    <option key={usuario} value={usuario}>
                                        {usuario}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div style={styles.campoFiltro}>
                            <label style={styles.etiquetaFiltro} htmlFor="desde-filtro">
                                Desde
                            </label>

                            <input
                                id="desde-filtro"
                                type="date"
                                style={styles.inputFiltro}
                                value={desde}
                                onChange={(e) => setDesde(e.target.value)}
                            />
                        </div>

                        <div style={styles.campoFiltro}>
                            <label style={styles.etiquetaFiltro} htmlFor="hasta-filtro">
                                Hasta
                            </label>

                            <input
                                id="hasta-filtro"
                                type="date"
                                style={styles.inputFiltro}
                                value={hasta}
                                onChange={(e) => setHasta(e.target.value)}
                            />
                        </div>

                        <button type="submit" style={styles.botonFiltrar}>
                            Filtrar
                        </button>
                    </form>

                    <div style={styles.tableContainer}>
                        <div style={styles.tableHeader}>
                            <div>FECHA Y HORA</div>
                            <div>USUARIO</div>
                            <div>ACCIÓN</div>
                            <div>ENTIDAD AFECTADA</div>
                        </div>

                        {eventosFiltrados.map((evento, indice) => (
                            <div style={styles.tableRow} key={indice}>
                                <div style={styles.celdaFecha}>
                                    {formatearFechaHora(evento.fecha)}
                                </div>
                                <div style={styles.celdaUsuario}>{evento.usuario}</div>
                                <div>{evento.accion}</div>
                                <div style={styles.celdaEntidad}>{evento.entidad}</div>
                            </div>
                        ))}
                    </div>

                    <p style={styles.pie}>
                        Mostrando {eventosFiltrados.length} de {totalEventosDelServidor} eventos
                    </p>
                </section>
            </main>
        </div>
    );
}

Bitacora.propTypes = {
    onNavigate: PropTypes.func,
};

function MenuItem({ icon, text, selected, onClick }) {
    return (
        <div
            style={{
                ...styles.menuItem,
                ...(selected ? styles.menuSelected : {}),
            }}
            onClick={onClick}
        >
            <span style={styles.menuIcon}>{icon}</span>
            <span>{text}</span>
        </div>
    );
}

MenuItem.propTypes = {
    icon: PropTypes.string.isRequired,
    text: PropTypes.string.isRequired,
    selected: PropTypes.bool,
    onClick: PropTypes.func,
};

const styles = {
    app: {
        display: 'flex',
        minHeight: '100vh',
        width: '100%',
        backgroundColor: '#ffffff',
        fontFamily:
            "Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        color: '#172033',
    },

    sidebar: {
        width: '182px',
        minWidth: '182px',
        backgroundColor: '#ffffff',
        borderRight: '1px solid #e5e9ef',
        minHeight: '100vh',
    },

    logoContainer: {
        height: '64px',
        display: 'flex',
        alignItems: 'center',
        gap: '9px',
        padding: '0 17px',
        borderBottom: '1px solid #edf0f4',
    },

    logo: {
        width: '30px',
        height: '30px',
        borderRadius: '7px',
        backgroundColor: '#2563eb',
        color: '#ffffff',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '17px',
        fontWeight: '700',
    },

    logoTitle: {
        fontSize: '12px',
        fontWeight: '700',
        lineHeight: '14px',
        color: '#1d2737',
    },

    logoSubtitle: {
        fontSize: '7px',
        letterSpacing: '0.4px',
        color: '#687386',
        marginTop: '2px',
    },

    menuSection: {
        padding: '17px 10px',
    },

    menuTitle: {
        fontSize: '7px',
        fontWeight: '600',
        color: '#8b95a5',
        letterSpacing: '0.7px',
        padding: '0 14px',
        marginBottom: '9px',
    },

    menuItem: {
        height: '30px',
        display: 'flex',
        alignItems: 'center',
        gap: '9px',
        padding: '0 10px',
        marginBottom: '3px',
        borderRadius: '6px',
        fontSize: '10px',
        color: '#536074',
        cursor: 'pointer',
    },

    menuSelected: {
        backgroundColor: '#2864df',
        color: '#ffffff',
        fontWeight: '600',
    },

    menuIcon: {
        width: '16px',
        textAlign: 'center',
        fontSize: '13px',
    },

    main: {
        flex: 1,
        minWidth: 0,
        backgroundColor: '#f8faff',
    },

    header: {
        height: '45px',
        borderBottom: '1px solid #e9edf2',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '0 20px',
        backgroundColor: '#ffffff',
    },

    headerTitle: {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        fontSize: '11px',
        color: '#1e293b',
        fontWeight: '500',
    },

    headerIcon: {
        color: '#2563eb',
        fontSize: '17px',
    },

    userCircle: {
        width: '23px',
        height: '23px',
        borderRadius: '50%',
        backgroundColor: '#2864df',
        color: '#ffffff',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '8px',
    },

    content: {
        padding: '23px 22px',
    },

    title: {
        margin: 0,
        fontSize: '22px',
        lineHeight: '26px',
        fontWeight: '700',
        color: '#182233',
    },

    description: {
        margin: '3px 0 0',
        fontSize: '9px',
        color: '#7b8797',
    },

    filtros: {
        display: 'flex',
        alignItems: 'flex-end',
        gap: '14px',
        marginTop: '20px',
        padding: '16px',
        backgroundColor: '#ffffff',
        border: '1px solid #e1e6ec',
        borderRadius: '9px',
    },

    campoFiltro: {
        display: 'flex',
        flexDirection: 'column',
        gap: '5px',
    },

    etiquetaFiltro: {
        fontSize: '9px',
        fontWeight: '600',
        color: '#374151',
    },

    selectFiltro: {
        border: '1px solid #d9e0e8',
        borderRadius: '6px',
        padding: '7px 9px',
        fontSize: '9px',
        color: '#1f2937',
        backgroundColor: '#fbfcfd',
        minWidth: '200px',
    },

    inputFiltro: {
        border: '1px solid #d9e0e8',
        borderRadius: '6px',
        padding: '7px 9px',
        fontSize: '9px',
        color: '#1f2937',
        backgroundColor: '#fbfcfd',
    },

    botonFiltrar: {
        border: 'none',
        borderRadius: '6px',
        backgroundColor: '#2864df',
        color: '#ffffff',
        fontSize: '9px',
        fontWeight: '600',
        padding: '8px 16px',
        cursor: 'pointer',
    },

    tableContainer: {
        marginTop: '16px',
        border: '1px solid #e1e6ec',
        borderRadius: '9px',
        overflow: 'hidden',
    },

    tableHeader: {
        minHeight: '35px',
        display: 'grid',
        gridTemplateColumns: '1fr 1.3fr 1.2fr 1.6fr',
        alignItems: 'center',
        padding: '0 16px',
        backgroundColor: '#fbfcfd',
        borderBottom: '1px solid #e5e9ee',
        color: '#687486',
        fontSize: '7px',
        fontWeight: '600',
        letterSpacing: '0.4px',
    },

    tableRow: {
        minHeight: '47px',
        display: 'grid',
        gridTemplateColumns: '1fr 1.3fr 1.2fr 1.6fr',
        alignItems: 'center',
        padding: '0 16px',
        borderBottom: '1px solid #e7ebef',
        fontSize: '9px',
        gap: '8px',
    },

    celdaFecha: {
        fontSize: '8px',
        color: '#687486',
        fontFamily: 'monospace',
    },

    celdaUsuario: {
        fontWeight: '600',
        color: '#1d2635',
    },

    celdaEntidad: {
        color: '#4b5563',
    },

    pie: {
        margin: '12px 3px',
        color: '#788494',
        fontSize: '9px',
    },
};

export default Bitacora;

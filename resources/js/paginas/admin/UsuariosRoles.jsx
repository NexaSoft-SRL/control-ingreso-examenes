import React from 'react';
import PropTypes from 'prop-types';

const usuarios = [
    {
        nombre: 'Dr. Rolando J. Torrico Mendoza',
        correo: 'rolando.torrico@fcyt.umss.edu.bo',
        rol: 'Administrador',
        tipo: 'administrador',
        activo: true,
    },
    {
        nombre: 'Lic. Marco Antonio Arnez',
        correo: 'marco.arnez@fcyt.umss.edu.bo',
        rol: 'Responsable académico',
        tipo: 'academico',
        activo: true,
    },
    {
        nombre: 'Ing. Patricia Villarroel Siles',
        correo: 'patricia.villarroel@fcyt.umss.edu.bo',
        rol: 'Docente',
        tipo: 'docente',
        activo: true,
    },
    {
        nombre: 'Ing. Marcelo Guzmán Flores',
        correo: 'marcelo.guzman@fcyt.umss.edu.bo',
        rol: 'Personal de control',
        tipo: 'control',
        activo: true,
    },
    {
        nombre: 'Dr. Carlos Eduardo Vargas',
        correo: 'carlos.vargas@fcyt.umss.edu.bo',
        rol: 'Docente',
        tipo: 'docente',
        activo: false,
    },
    {
        nombre: 'Lic. Valeria Bustamante Torrico',
        correo: 'valeria.bustamante@fcyt.umss.edu.bo',
        rol: 'Personal de control',
        tipo: 'control',
        activo: true,
    },
];

function UsuariosRoles({ onNavigate }) {
    const [mostrarFormulario, setMostrarFormulario] = React.useState(false);
    const [, setUsuarioEditando] = React.useState(null);

    const [nuevoUsuario, setNuevoUsuario] = React.useState({
        nombre: '',
        correo: '',
        rol: 'Docente',
    });

    return (
        <div style={styles.app}>
            {/* BARRA LATERAL */}
            <aside style={styles.sidebar}>
                {/* LOGO */}
                <div style={styles.logoContainer}>
                    <div style={styles.logo}>✓</div>

                    <div>
                        <div style={styles.logoTitle}>UMSS FCyT</div>
                        <div style={styles.logoSubtitle}>CONTROL DE INGRESO</div>
                    </div>
                </div>

                {/* MENU */}
                <div style={styles.menuSection}>
                    <div style={styles.menuTitle}>ADMINISTRADOR</div>

                    <MenuItem icon="♙" text="Padrón" />

                    <MenuItem
                        icon="▤"
                        text="Asignaturas y ambientes"
                        onClick={() => onNavigate('asignaturas')}
                    />
                    <MenuItem icon="⌗" text="Códigos QR" />
                    <MenuItem
                        icon="♙"
                        text="Usuarios y roles"
                        selected
                        onClick={() => onNavigate('usuarios')}
                    />
                    <MenuItem icon="▧" text="Bitácora" />
                    <MenuItem icon="↻" text="Respaldo" />
                </div>
            </aside>

            {/* CONTENIDO PRINCIPAL */}
            <main style={styles.main}>
                {/* BARRA SUPERIOR */}
                <header style={styles.header}>
                    <div style={styles.headerTitle}>
                        <span style={styles.headerIcon}>♢</span>
                        Sistema Institucional de Verificación
                    </div>

                    <div style={styles.userCircle}>●</div>
                </header>

                {/* CONTENIDO */}
                <section style={styles.content}>
                    {/* TITULO Y BOTON */}
                    <div style={styles.titleRow}>
                        <div>
                            <h1 style={styles.title}>Usuarios y roles</h1>

                            <p style={styles.description}>
                                Gestión de cuentas y permisos del sistema
                            </p>
                        </div>

                        <button style={styles.newButton} onClick={() => setMostrarFormulario(true)}>
                            + Nuevo usuario
                        </button>
                    </div>

                    {/* PESTAÑAS */}
                    <div style={styles.tabs}>
                        <button style={styles.tabActive}>Usuarios</button>

                        <button style={styles.tab}>Roles</button>
                    </div>

                    {/* TABLA */}
                    <div style={styles.tableContainer}>
                        <div style={styles.tableHeader}>
                            <div>NOMBRE</div>
                            <div>CORREO</div>
                            <div>ROL</div>
                            <div>ESTADO</div>
                            <div>ACCIONES</div>
                        </div>

                        {usuarios.map((usuario, index) => (
                            <div style={styles.tableRow} key={index}>
                                {/* NOMBRE */}
                                <div style={styles.name}>{usuario.nombre}</div>

                                {/* CORREO */}
                                <div style={styles.email}>{usuario.correo}</div>

                                {/* ROL */}
                                <div>
                                    <span
                                        style={{
                                            ...styles.role,
                                            ...roleStyles[usuario.tipo],
                                        }}
                                    >
                                        {usuario.rol}
                                    </span>
                                </div>

                                {/* ESTADO */}
                                <div>
                                    <div
                                        style={{
                                            ...styles.switch,
                                            ...(usuario.activo
                                                ? styles.switchActive
                                                : styles.switchInactive),
                                        }}
                                    >
                                        <div
                                            style={{
                                                ...styles.switchCircle,
                                                ...(usuario.activo
                                                    ? styles.circleActive
                                                    : styles.circleInactive),
                                            }}
                                        />
                                    </div>
                                </div>

                                {/* ACCIONES */}
                                <div>
                                    <button
                                        style={styles.editButton}
                                        onClick={() => {
                                            setUsuarioEditando(usuario);
                                            setNuevoUsuario({
                                                nombre: usuario.nombre,
                                                correo: usuario.correo,
                                                rol: usuario.rol,
                                            });
                                            setMostrarFormulario(true);
                                        }}
                                    >
                                        Editar
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* PIE */}
                    <p style={styles.footerText}>Las cuentas las crea el Administrador.</p>
                </section>
                {mostrarFormulario && (
                    <div style={styles.modalOverlay}>
                        <div style={styles.modal}>
                            <div style={styles.modalHeader}>
                                <div>
                                    <h2 style={styles.modalTitle}>Nuevo usuario</h2>

                                    <p style={styles.modalDescription}>
                                        Crear una nueva cuenta del sistema
                                    </p>
                                </div>

                                <button
                                    style={styles.closeButton}
                                    onClick={() => setMostrarFormulario(false)}
                                >
                                    ×
                                </button>
                            </div>

                            <div style={styles.formGroup}>
                                <label style={styles.label}>Nombre completo</label>

                                <input
                                    style={styles.input}
                                    value={nuevoUsuario.nombre}
                                    onChange={(e) =>
                                        setNuevoUsuario({
                                            ...nuevoUsuario,
                                            nombre: e.target.value,
                                        })
                                    }
                                    placeholder="Ingrese el nombre completo"
                                />
                            </div>

                            <div style={styles.formGroup}>
                                <label style={styles.label}>Correo</label>

                                <input
                                    style={styles.input}
                                    value={nuevoUsuario.correo}
                                    onChange={(e) =>
                                        setNuevoUsuario({
                                            ...nuevoUsuario,
                                            correo: e.target.value,
                                        })
                                    }
                                    placeholder="correo@fcyt.umss.edu.bo"
                                />
                            </div>

                            <div style={styles.formGroup}>
                                <label style={styles.label}>Rol</label>

                                <select
                                    style={styles.input}
                                    value={nuevoUsuario.rol}
                                    onChange={(e) =>
                                        setNuevoUsuario({
                                            ...nuevoUsuario,
                                            rol: e.target.value,
                                        })
                                    }
                                >
                                    <option>Administrador</option>
                                    <option>Responsable académico</option>
                                    <option>Docente</option>
                                    <option>Personal de control</option>
                                </select>
                            </div>

                            <div style={styles.modalActions}>
                                <button
                                    style={styles.cancelButton}
                                    onClick={() => setMostrarFormulario(false)}
                                >
                                    Cancelar
                                </button>

                                <button
                                    style={styles.saveButton}
                                    onClick={() => {
                                        alert('Usuario creado correctamente');

                                        setMostrarFormulario(false);

                                        setNuevoUsuario({
                                            nombre: '',
                                            correo: '',
                                            rol: 'Docente',
                                        });
                                    }}
                                >
                                    Crear usuario
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </main>
        </div>
    );
}

UsuariosRoles.propTypes = {
    onNavigate: PropTypes.func.isRequired,
};

/* ================================
   COMPONENTE DEL MENU
================================ */

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

/* ================================
   ESTILOS
================================ */

const roleStyles = {
    administrador: {
        backgroundColor: '#111827',
        color: '#ffffff',
    },

    academico: {
        backgroundColor: '#f1d9ff',
        color: '#8b1dbd',
    },

    docente: {
        backgroundColor: '#dcecff',
        color: '#3977c5',
    },

    control: {
        backgroundColor: '#d5f5e5',
        color: '#15915b',
    },
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

    modalOverlay: {
        position: 'fixed',
        inset: 0,
        backgroundColor: 'rgba(15, 23, 42, 0.35)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
    },
    modal: {
        width: '420px',
        backgroundColor: '#ffffff',
        borderRadius: '10px',
        padding: '22px',
        boxShadow: '0 20px 50px rgba(0, 0, 0, 0.18)',
    },

    modalHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
        marginBottom: '20px',
    },

    modalTitle: {
        margin: 0,
        fontSize: '18px',
        fontWeight: '700',
        color: '#182233',
    },

    modalDescription: {
        margin: '4px 0 0',
        fontSize: '9px',
        color: '#7b8797',
    },

    closeButton: {
        border: 'none',
        background: 'transparent',
        fontSize: '22px',
        color: '#647084',
        cursor: 'pointer',
    },

    formGroup: {
        marginBottom: '15px',
    },

    label: {
        display: 'block',
        marginBottom: '6px',
        fontSize: '9px',
        fontWeight: '600',
        color: '#374151',
    },

    input: {
        width: '100%',
        boxSizing: 'border-box',
        border: '1px solid #d9e0e8',
        borderRadius: '6px',
        padding: '9px 10px',
        fontSize: '10px',
        color: '#1f2937',
        outline: 'none',
        backgroundColor: '#ffffff',
    },

    modalActions: {
        display: 'flex',
        justifyContent: 'flex-end',
        gap: '8px',
        marginTop: '20px',
    },

    cancelButton: {
        border: '1px solid #d9e0e8',
        backgroundColor: '#ffffff',
        color: '#4b5563',
        borderRadius: '6px',
        padding: '8px 13px',
        fontSize: '9px',
        cursor: 'pointer',
    },

    saveButton: {
        border: 'none',
        backgroundColor: '#2864df',
        color: '#ffffff',
        borderRadius: '6px',
        padding: '8px 13px',
        fontSize: '9px',
        fontWeight: '600',
        cursor: 'pointer',
    },

    /* SIDEBAR */

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

    /* MAIN */

    main: {
        flex: 1,
        minWidth: 0,
        backgroundColor: '#ffffff',
    },

    header: {
        height: '45px',
        borderBottom: '1px solid #e9edf2',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '0 20px',
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

    /* CONTENT */

    content: {
        padding: '23px 22px',
    },

    titleRow: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
    },

    title: {
        margin: 0,
        fontSize: '18px',
        lineHeight: '22px',
        fontWeight: '700',
        color: '#182233',
    },

    description: {
        margin: '3px 0 0',
        fontSize: '9px',
        color: '#7b8797',
    },

    newButton: {
        border: 'none',
        borderRadius: '5px',
        backgroundColor: '#2864df',
        color: '#ffffff',
        fontSize: '9px',
        fontWeight: '600',
        padding: '8px 13px',
        cursor: 'pointer',
        marginTop: '-1px',
    },

    /* TABS */

    tabs: {
        display: 'flex',
        gap: '22px',
        marginTop: '20px',
        borderBottom: '1px solid #e9edf2',
    },

    tabActive: {
        border: 'none',
        borderBottom: '2px solid #2864df',
        background: 'transparent',
        color: '#2864df',
        fontSize: '9px',
        fontWeight: '600',
        padding: '0 3px 10px',
        cursor: 'pointer',
    },

    tab: {
        border: 'none',
        background: 'transparent',
        color: '#647084',
        fontSize: '9px',
        padding: '0 3px 10px',
        cursor: 'pointer',
    },

    /* TABLE */

    tableContainer: {
        marginTop: '12px',
        border: '1px solid #e1e6ec',
        borderRadius: '9px',
        overflow: 'hidden',
    },

    tableHeader: {
        minHeight: '35px',
        display: 'grid',
        gridTemplateColumns: '1.25fr 1.15fr 0.95fr 0.42fr 0.38fr',
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
        gridTemplateColumns: '1.25fr 1.15fr 0.95fr 0.42fr 0.38fr',
        alignItems: 'center',
        padding: '0 16px',
        borderBottom: '1px solid #e7ebef',
        fontSize: '9px',
    },

    name: {
        fontSize: '9px',
        lineHeight: '12px',
        fontWeight: '600',
        color: '#1d2635',
        paddingRight: '8px',
    },

    email: {
        fontSize: '7px',
        color: '#737e8d',
        paddingRight: '8px',
    },

    role: {
        display: 'inline-block',
        padding: '4px 8px',
        borderRadius: '12px',
        fontSize: '7px',
        fontWeight: '500',
        lineHeight: '10px',
    },

    /* SWITCH */

    switch: {
        width: '28px',
        height: '15px',
        borderRadius: '15px',
        position: 'relative',
        transition: '0.2s',
    },

    switchActive: {
        backgroundColor: '#2864df',
    },

    switchInactive: {
        backgroundColor: '#d6dde6',
    },

    switchCircle: {
        position: 'absolute',
        width: '11px',
        height: '11px',
        top: '2px',
        borderRadius: '50%',
        backgroundColor: '#ffffff',
        transition: '0.2s',
    },

    circleActive: {
        right: '2px',
    },

    circleInactive: {
        left: '2px',
    },

    editButton: {
        border: 'none',
        background: 'transparent',
        color: '#2864df',
        fontSize: '8px',
        cursor: 'pointer',
        padding: 0,
    },

    footerText: {
        margin: '10px 3px',
        color: '#788494',
        fontSize: '7px',
    },
};

export default UsuariosRoles;
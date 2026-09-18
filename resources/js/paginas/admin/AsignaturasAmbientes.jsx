import React from 'react';
import PropTypes from 'prop-types';

const asignaturasIniciales = [
    {
        materia: 'Redes de Computadoras',
        docente: 'Ing. Rolando J. Torrico Mendoza',
    },
    {
        materia: 'Base de Datos I',
        docente: 'Lic. Marco Antonio Arnez Claros',
    },
    {
        materia: 'Sistemas Operativos',
        docente: 'Ing. Patricia Villarroel Siles',
    },
    {
        materia: 'Inteligencia Artificial',
        docente: 'Dr. Carlos Eduardo Vargas Rojas',
    },
    {
        materia: 'Taller de Ingeniería de Software',
        docente: 'Ing. Marcelo Guzmán Flores',
    },
];

const ambientesIniciales = [
    {
        nombre: 'Aula Magna - FCyT',
        capacidad: '120 personas',
        estado: 'Disponible',
    },
    {
        nombre: 'Módulo 3 - Aula 205',
        capacidad: '60',
        estado: 'Disponible',
    },
    {
        nombre: 'Laboratorio de Sistemas 1',
        capacidad: '45',
        estado: 'Mantenimiento',
    },
    {
        nombre: 'Aula 691B',
        capacidad: '88',
        estado: 'Disponible',
    },
    {
        nombre: 'Auditorio Edificio Nuevo',
        capacidad: '150',
        estado: 'Disponible',
    },
];

function AsignaturasAmbientes({ onNavigate }) {
    const [asignaturas, setAsignaturas] = React.useState(asignaturasIniciales);

    const [ambientes, setAmbientes] = React.useState(ambientesIniciales);

    /* =========================
       ESTADO ASIGNATURAS
    ========================== */

    const [mostrarFormularioAsignatura, setMostrarFormularioAsignatura] = React.useState(false);

    const [asignaturaEditando, setAsignaturaEditando] = React.useState(null);

    const [nuevaAsignatura, setNuevaAsignatura] = React.useState({
        materia: '',
        docente: '',
    });

    /* =========================
       ESTADO AMBIENTES
    ========================== */

    const [mostrarFormularioAmbiente, setMostrarFormularioAmbiente] = React.useState(false);

    const [ambienteEditando, setAmbienteEditando] = React.useState(null);

    const [nuevoAmbiente, setNuevoAmbiente] = React.useState({
        nombre: '',
        capacidad: '',
        estado: 'Disponible',
    });

    /* =========================
       FUNCIONES ASIGNATURAS
    ========================== */

    const abrirNuevaAsignatura = () => {
        setAsignaturaEditando(null);

        setNuevaAsignatura({
            materia: '',
            docente: '',
        });

        setMostrarFormularioAsignatura(true);
    };

    const abrirEditarAsignatura = (index) => {
        const asignatura = asignaturas[index];

        setAsignaturaEditando(index);

        setNuevaAsignatura({
            materia: asignatura.materia,
            docente: asignatura.docente,
        });

        setMostrarFormularioAsignatura(true);
    };

    const cancelarAsignatura = () => {
        setMostrarFormularioAsignatura(false);
        setAsignaturaEditando(null);

        setNuevaAsignatura({
            materia: '',
            docente: '',
        });
    };

    const guardarAsignatura = () => {
        if (nuevaAsignatura.materia.trim() === '' || nuevaAsignatura.docente.trim() === '') {
            alert('Completa todos los campos de la asignatura.');
            return;
        }

        if (asignaturaEditando === null) {
            setAsignaturas([
                ...asignaturas,
                {
                    materia: nuevaAsignatura.materia.trim(),
                    docente: nuevaAsignatura.docente.trim(),
                },
            ]);

            alert('Asignatura creada correctamente.');
        } else {
            const asignaturasActualizadas = [...asignaturas];

            asignaturasActualizadas[asignaturaEditando] = {
                materia: nuevaAsignatura.materia.trim(),
                docente: nuevaAsignatura.docente.trim(),
            };

            setAsignaturas(asignaturasActualizadas);

            alert('Asignatura actualizada correctamente.');
        }

        cancelarAsignatura();
    };

    /* =========================
       FUNCIONES AMBIENTES
    ========================== */

    const abrirNuevoAmbiente = () => {
        setAmbienteEditando(null);

        setNuevoAmbiente({
            nombre: '',
            capacidad: '',
            estado: 'Disponible',
        });

        setMostrarFormularioAmbiente(true);
    };

    const abrirEditarAmbiente = (index) => {
        const ambiente = ambientes[index];

        setAmbienteEditando(index);

        setNuevoAmbiente({
            nombre: ambiente.nombre,
            capacidad: ambiente.capacidad,
            estado: ambiente.estado,
        });

        setMostrarFormularioAmbiente(true);
    };

    const cancelarAmbiente = () => {
        setMostrarFormularioAmbiente(false);
        setAmbienteEditando(null);

        setNuevoAmbiente({
            nombre: '',
            capacidad: '',
            estado: 'Disponible',
        });
    };

    const guardarAmbiente = () => {
        if (nuevoAmbiente.nombre.trim() === '' || nuevoAmbiente.capacidad.trim() === '') {
            alert('Completa todos los campos del ambiente.');
            return;
        }

        if (ambienteEditando === null) {
            setAmbientes([
                ...ambientes,
                {
                    nombre: nuevoAmbiente.nombre.trim(),
                    capacidad: nuevoAmbiente.capacidad.trim(),
                    estado: nuevoAmbiente.estado,
                },
            ]);

            alert('Ambiente creado correctamente.');
        } else {
            const ambientesActualizados = [...ambientes];

            ambientesActualizados[ambienteEditando] = {
                nombre: nuevoAmbiente.nombre.trim(),
                capacidad: nuevoAmbiente.capacidad.trim(),
                estado: nuevoAmbiente.estado,
            };

            setAmbientes(ambientesActualizados);

            alert('Ambiente actualizado correctamente.');
        }

        cancelarAmbiente();
    };

    return (
        <div style={styles.app}>
            {/* =========================
                BARRA LATERAL
            ========================== */}

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

                    <MenuItem
                        icon="♙"
                        text="Padrón"
                        onClick={() => onNavigate('estudiantes')}

                    />

                    <MenuItem
                        icon="▤"
                        text="Asignaturas y ambientes"
                        selected
                        onClick={() => onNavigate('asignaturas')}
                    />

                    <MenuItem
                        icon="⌗"
                        text="Códigos QR"
                        onClick={() => {
                            alert('Códigos QR: próximamente');
                        }}
                    />

                    <MenuItem
                        icon="♙"
                        text="Usuarios y roles"
                        onClick={() => onNavigate('usuarios')}
                    />

                    <MenuItem
                        icon="▧"
                        text="Bitácora"
                        onClick={() => {
                            alert('Bitácora: próximamente');
                        }}
                    />

                    <MenuItem
                        icon="↻"
                        text="Respaldo"
                        onClick={() => {
                            alert('Respaldo: próximamente');
                        }}
                    />
                </div>
            </aside>

            {/* =========================
                CONTENIDO PRINCIPAL
            ========================== */}

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
                    {/* TITULO */}

                    <div style={styles.titleRow}>
                        <div>
                            <h1 style={styles.title}>Asignaturas y ambientes</h1>

                            <p style={styles.description}>
                                Configuración de materias y aulas disponibles
                            </p>
                        </div>
                    </div>

                    {/* =========================
                        ASIGNATURAS
                    ========================== */}

                    <div style={styles.sectionHeader}>
                        <div style={styles.sectionTitleContainer}>
                            <span style={styles.blueLine}></span>

                            <h2 style={styles.sectionTitle}>Asignaturas</h2>

                            <span style={styles.count}>{asignaturas.length} registros</span>
                        </div>

                        <button style={styles.newButton} onClick={abrirNuevaAsignatura}>
                            + Nueva
                        </button>
                    </div>

                    <div style={styles.tableContainer}>
                        <div style={styles.subjectHeader}>
                            <div>MATERIA</div>
                            <div>DOCENTE ASIGNADO</div>
                            <div>ACCIONES</div>
                        </div>

                        {asignaturas.map((asignatura, index) => (
                            <div style={styles.subjectRow} key={index}>
                                <div style={styles.name}>{asignatura.materia}</div>

                                <div style={styles.teacher}>{asignatura.docente}</div>

                                <div>
                                    <button
                                        style={styles.editButton}
                                        onClick={() => abrirEditarAsignatura(index)}
                                    >
                                        Editar
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* SEPARADOR */}

                    <div style={styles.infrastructure}>
                        <span>● FCyT INFRAESTRUCTURA</span>

                        <span>EDIF. CENTRAL & NUEVO</span>
                    </div>

                    {/* =========================
                        AMBIENTES
                    ========================== */}

                    <div style={styles.sectionHeader}>
                        <div style={styles.sectionTitleContainer}>
                            <span style={styles.greenLine}></span>

                            <h2 style={styles.sectionTitle}>Ambientes</h2>

                            <span style={styles.count}>{ambientes.length} registros</span>
                        </div>

                        <button style={styles.newButton} onClick={abrirNuevoAmbiente}>
                            + Nuevo
                        </button>
                    </div>

                    <div style={styles.tableContainer}>
                        <div style={styles.environmentHeader}>
                            <div>NOMBRE DE AULA</div>

                            <div>CAPACIDAD</div>

                            <div>ESTADO</div>

                            <div>ACCIONES</div>
                        </div>

                        {ambientes.map((ambiente, index) => (
                            <div style={styles.environmentRow} key={index}>
                                <div style={styles.name}>{ambiente.nombre}</div>

                                <div style={styles.capacity}>{ambiente.capacidad}</div>

                                <div>
                                    <span
                                        style={{
                                            ...styles.status,
                                            ...(ambiente.estado === 'Disponible'
                                                ? styles.available
                                                : styles.maintenance),
                                        }}
                                    >
                                        ● {ambiente.estado}
                                    </span>
                                </div>

                                <div>
                                    <button
                                        style={styles.editButton}
                                        onClick={() => abrirEditarAmbiente(index)}
                                    >
                                        Editar
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </main>

            {/* =========================
                MODAL ASIGNATURA
            ========================== */}

            {mostrarFormularioAsignatura && (
                <div style={styles.modalOverlay}>
                    <div style={styles.modal}>
                        <h2 style={styles.modalTitle}>
                            {asignaturaEditando === null ? 'Nueva asignatura' : 'Editar asignatura'}
                        </h2>

                        <p style={styles.modalDescription}>
                            {asignaturaEditando === null
                                ? 'Registra una nueva materia'
                                : 'Modifica los datos de la asignatura'}
                        </p>

                        <label style={styles.label}>Materia</label>

                        <input
                            type="text"
                            value={nuevaAsignatura.materia}
                            onChange={(e) =>
                                setNuevaAsignatura({
                                    ...nuevaAsignatura,
                                    materia: e.target.value,
                                })
                            }
                            placeholder="Ej. Redes de Computadoras"
                            style={styles.input}
                        />

                        <label style={styles.label}>Docente asignado</label>

                        <input
                            type="text"
                            value={nuevaAsignatura.docente}
                            onChange={(e) =>
                                setNuevaAsignatura({
                                    ...nuevaAsignatura,
                                    docente: e.target.value,
                                })
                            }
                            placeholder="Ej. Ing. Juan Pérez"
                            style={styles.input}
                        />

                        <div style={styles.modalButtons}>
                            <button style={styles.cancelButton} onClick={cancelarAsignatura}>
                                Cancelar
                            </button>

                            <button style={styles.saveButton} onClick={guardarAsignatura}>
                                {asignaturaEditando === null ? 'Guardar' : 'Guardar cambios'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* =========================
                MODAL AMBIENTE
            ========================== */}

            {mostrarFormularioAmbiente && (
                <div style={styles.modalOverlay}>
                    <div style={styles.modal}>
                        <h2 style={styles.modalTitle}>
                            {ambienteEditando === null ? 'Nuevo ambiente' : 'Editar ambiente'}
                        </h2>

                        <p style={styles.modalDescription}>
                            {ambienteEditando === null
                                ? 'Registra un nuevo ambiente'
                                : 'Modifica los datos del ambiente'}
                        </p>

                        <label style={styles.label}>Nombre del aula</label>

                        <input
                            type="text"
                            value={nuevoAmbiente.nombre}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    nombre: e.target.value,
                                })
                            }
                            placeholder="Ej. Aula 302"
                            style={styles.input}
                        />

                        <label style={styles.label}>Capacidad</label>

                        <input
                            type="text"
                            value={nuevoAmbiente.capacidad}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    capacidad: e.target.value,
                                })
                            }
                            placeholder="Ej. 50"
                            style={styles.input}
                        />

                        <label style={styles.label}>Estado</label>

                        <select
                            value={nuevoAmbiente.estado}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    estado: e.target.value,
                                })
                            }
                            style={styles.input}
                        >
                            <option value="Disponible">Disponible</option>

                            <option value="Mantenimiento">Mantenimiento</option>
                        </select>

                        <div style={styles.modalButtons}>
                            <button style={styles.cancelButton} onClick={cancelarAmbiente}>
                                Cancelar
                            </button>

                            <button style={styles.saveButton} onClick={guardarAmbiente}>
                                {ambienteEditando === null ? 'Guardar' : 'Guardar cambios'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

AsignaturasAmbientes.propTypes = {
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

    titleRow: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
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

    sectionHeader: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginTop: '27px',
        marginBottom: '8px',
    },

    sectionTitleContainer: {
        display: 'flex',
        alignItems: 'center',
        gap: '7px',
    },

    blueLine: {
        width: '4px',
        height: '12px',
        borderRadius: '4px',
        backgroundColor: '#2864df',
    },

    greenLine: {
        width: '4px',
        height: '12px',
        borderRadius: '4px',
        backgroundColor: '#16866c',
    },

    sectionTitle: {
        margin: 0,
        fontSize: '12px',
        fontWeight: '700',
        color: '#263246',
    },

    count: {
        backgroundColor: '#e9eef9',
        color: '#8a96a9',
        borderRadius: '4px',
        padding: '3px 6px',
        fontSize: '7px',
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
    },

    tableContainer: {
        borderRadius: '9px',
        overflow: 'hidden',
        backgroundColor: '#ffffff',
        boxShadow: '0 1px 3px rgba(0,0,0,0.03)',
    },

    subjectHeader: {
        minHeight: '27px',
        display: 'grid',
        gridTemplateColumns: '1.05fr 1.2fr 0.2fr',
        alignItems: 'center',
        padding: '0 10px',
        backgroundColor: '#eef3ff',
        color: '#687486',
        fontSize: '7px',
        fontWeight: '600',
        letterSpacing: '0.3px',
    },

    subjectRow: {
        minHeight: '31px',
        display: 'grid',
        gridTemplateColumns: '1.05fr 1.2fr 0.2fr',
        alignItems: 'center',
        padding: '0 10px',
        borderBottom: '1px solid #f0f2f5',
        fontSize: '8px',
    },

    environmentHeader: {
        minHeight: '27px',
        display: 'grid',
        gridTemplateColumns: '1.2fr 0.55fr 0.7fr 0.2fr',
        alignItems: 'center',
        padding: '0 10px',
        backgroundColor: '#eef3ff',
        color: '#687486',
        fontSize: '7px',
        fontWeight: '600',
        letterSpacing: '0.3px',
    },

    environmentRow: {
        minHeight: '31px',
        display: 'grid',
        gridTemplateColumns: '1.2fr 0.55fr 0.7fr 0.2fr',
        alignItems: 'center',
        padding: '0 10px',
        borderBottom: '1px solid #f0f2f5',
        fontSize: '8px',
    },

    name: {
        fontWeight: '600',
        color: '#263246',
    },

    teacher: {
        color: '#6f7888',
    },

    capacity: {
        color: '#6f7888',
    },

    status: {
        display: 'inline-block',
        padding: '3px 7px',
        borderRadius: '10px',
        fontSize: '7px',
        fontWeight: '600',
    },

    available: {
        backgroundColor: '#a9f3d1',
        color: '#12855d',
    },

    maintenance: {
        backgroundColor: '#ffd5dc',
        color: '#b53b50',
    },

    editButton: {
        border: 'none',
        background: 'transparent',
        color: '#2864df',
        fontSize: '8px',
        cursor: 'pointer',
        padding: 0,
    },

    infrastructure: {
        display: 'flex',
        justifyContent: 'space-between',
        margin: '38px 5px 8px',
        color: '#c0c6d0',
        fontSize: '7px',
        letterSpacing: '0.5px',
    },

    /* =========================
       MODALES
    ========================== */

    modalOverlay: {
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: 'rgba(15, 23, 42, 0.35)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
    },

    modal: {
        width: '360px',
        backgroundColor: '#ffffff',
        borderRadius: '10px',
        padding: '22px',
        boxShadow: '0 10px 30px rgba(0,0,0,0.15)',
    },

    modalTitle: {
        margin: 0,
        fontSize: '17px',
        fontWeight: '700',
        color: '#182233',
    },

    modalDescription: {
        margin: '5px 0 18px',
        fontSize: '9px',
        color: '#7b8797',
    },

    label: {
        display: 'block',
        marginBottom: '5px',
        fontSize: '9px',
        fontWeight: '600',
        color: '#344054',
    },

    input: {
        width: '100%',
        boxSizing: 'border-box',
        padding: '9px 10px',
        marginBottom: '14px',
        border: '1px solid #d9dee7',
        borderRadius: '5px',
        outline: 'none',
        fontSize: '10px',
        color: '#263246',
        backgroundColor: '#ffffff',
    },

    modalButtons: {
        display: 'flex',
        justifyContent: 'flex-end',
        gap: '8px',
        marginTop: '5px',
    },

    cancelButton: {
        border: '1px solid #d9dee7',
        backgroundColor: '#ffffff',
        color: '#536074',
        borderRadius: '5px',
        padding: '8px 13px',
        fontSize: '9px',
        cursor: 'pointer',
    },

    saveButton: {
        border: 'none',
        backgroundColor: '#2864df',
        color: '#ffffff',
        borderRadius: '5px',
        padding: '8px 13px',
        fontSize: '9px',
        fontWeight: '600',
        cursor: 'pointer',
    },
};

export default AsignaturasAmbientes;

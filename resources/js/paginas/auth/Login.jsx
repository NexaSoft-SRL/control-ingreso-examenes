import React from 'react';
import PropTypes from 'prop-types';

/**
 * Pantalla de inicio de sesion (HU-01). Consume el endpoint ya construido en
 * el backend: POST /api/auth/login, con {email, password}.
 *
 * No hay registro publico ni recuperacion de contrasena automatica todavia
 * (no existe el endpoint): el enlace "Olvidaste tu contrasena" solo explica
 * como proceder mientras tanto, no dispara ninguna llamada.
 */
function Login({ onAutenticado }) {
    const [correo, setCorreo] = React.useState('');
    const [contrasena, setContrasena] = React.useState('');
    const [mostrarContrasena, setMostrarContrasena] = React.useState(false);
    const [cargando, setCargando] = React.useState(false);
    const [error, setError] = React.useState(null);
    const [ayudaContrasena, setAyudaContrasena] = React.useState(false);

    async function manejarEnvio(evento) {
        evento.preventDefault();
        setError(null);
        setCargando(true);

        try {
            const respuesta = await window.axios.post('/api/auth/login', {
                email: correo,
                password: contrasena,
            });

            onAutenticado(respuesta.data.user);
        } catch (excepcion) {
            const estado = excepcion.response?.status;

            if (estado === 401) {
                setError('Credenciales incorrectas.');
            } else if (estado === 422) {
                setError('Ingresa un correo y una contraseña válidos.');
            } else {
                setError('No se pudo conectar con el sistema. Intenta de nuevo.');
            }
        } finally {
            setCargando(false);
        }
    }

    return (
        <div style={styles.fondo}>
            <div style={styles.tarjeta}>
                <div style={styles.insignia}>
                    <ShieldIcon />
                </div>

                <p style={styles.institucion}>FCYT · UMSS</p>

                <h1 style={styles.titulo}>Control de ingreso a exámenes</h1>

                <p style={styles.subtitulo}>Ingresá con tu cuenta institucional</p>

                <form onSubmit={manejarEnvio} noValidate>
                    <div style={styles.grupoCampo}>
                        <label style={styles.etiqueta} htmlFor="correo">
                            Correo
                        </label>

                        <input
                            id="correo"
                            type="email"
                            style={styles.campo}
                            placeholder="usuario@fcyt.umss.edu.bo"
                            value={correo}
                            onChange={(evento) => setCorreo(evento.target.value)}
                            autoComplete="username"
                            required
                        />
                    </div>

                    <div style={styles.grupoCampo}>
                        <label style={styles.etiqueta} htmlFor="contrasena">
                            Contraseña
                        </label>

                        <div style={styles.campoConIcono}>
                            <input
                                id="contrasena"
                                type={mostrarContrasena ? 'text' : 'password'}
                                style={styles.campoPassword}
                                placeholder="••••••••"
                                value={contrasena}
                                onChange={(evento) => setContrasena(evento.target.value)}
                                autoComplete="current-password"
                                required
                            />

                            <button
                                type="button"
                                style={styles.botonOjo}
                                onClick={() => setMostrarContrasena((valor) => !valor)}
                                aria-label={
                                    mostrarContrasena ? 'Ocultar contraseña' : 'Mostrar contraseña'
                                }
                            >
                                <EyeIcon tachado={mostrarContrasena} />
                            </button>
                        </div>
                    </div>

                    {error && (
                        <p role="alert" style={styles.error}>
                            {error}
                        </p>
                    )}

                    <button type="submit" style={styles.botonEnviar} disabled={cargando}>
                        {cargando ? 'Ingresando…' : 'Iniciar sesión'}
                    </button>
                </form>

                <button
                    type="button"
                    style={styles.enlaceOlvido}
                    onClick={() => setAyudaContrasena((valor) => !valor)}
                >
                    ¿Olvidaste tu contraseña?
                </button>

                {ayudaContrasena && (
                    <p style={styles.textoAyuda}>
                        Pídele al Administrador que te la restablezca; todavía no hay recuperación
                        automática.
                    </p>
                )}
            </div>

            <p style={styles.pie}>
                Sin registro público. Las cuentas las crea el Administrador.
                <br />
                Bloqueo tras 5 intentos fallidos.
            </p>
        </div>
    );
}

Login.propTypes = {
    onAutenticado: PropTypes.func.isRequired,
};

function ShieldIcon() {
    return (
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M12 2.5 4.5 5.5v5.2c0 4.7 3.2 9 7.5 10.3 4.3-1.3 7.5-5.6 7.5-10.3V5.5L12 2.5Z"
                fill="#2864df"
            />
            <path
                d="m8.6 12 2.3 2.3 4.5-4.6"
                stroke="#ffffff"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function EyeIcon({ tachado }) {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"
                stroke="#8b95a5"
                strokeWidth="1.4"
            />
            <circle cx="12" cy="12" r="3" stroke="#8b95a5" strokeWidth="1.4" />
            {tachado && <line x1="3" y1="21" x2="21" y2="3" stroke="#8b95a5" strokeWidth="1.4" />}
        </svg>
    );
}

EyeIcon.propTypes = {
    tachado: PropTypes.bool,
};

const styles = {
    fondo: {
        minHeight: '100vh',
        width: '100%',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '18px',
        padding: '24px',
        backgroundColor: '#eef1f6',
        fontFamily:
            "Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        boxSizing: 'border-box',
    },

    tarjeta: {
        width: '100%',
        maxWidth: '380px',
        backgroundColor: '#ffffff',
        borderRadius: '16px',
        padding: '36px 32px',
        boxShadow: '0 20px 45px rgba(15, 23, 42, 0.08)',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        boxSizing: 'border-box',
    },

    insignia: {
        width: '56px',
        height: '56px',
        borderRadius: '50%',
        backgroundColor: '#eaf0fe',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: '14px',
    },

    institucion: {
        margin: 0,
        fontSize: '11px',
        fontWeight: '700',
        letterSpacing: '0.5px',
        color: '#2864df',
    },

    titulo: {
        margin: '8px 0 0',
        fontSize: '20px',
        fontWeight: '700',
        color: '#182233',
        textAlign: 'center',
        lineHeight: '26px',
    },

    subtitulo: {
        margin: '6px 0 24px',
        fontSize: '12px',
        color: '#7b8797',
        textAlign: 'center',
    },

    grupoCampo: {
        width: '100%',
        marginBottom: '14px',
        textAlign: 'left',
    },

    etiqueta: {
        display: 'block',
        marginBottom: '6px',
        fontSize: '9px',
        fontWeight: '700',
        letterSpacing: '0.4px',
        color: '#374151',
        textTransform: 'uppercase',
    },

    campo: {
        width: '100%',
        boxSizing: 'border-box',
        border: '1px solid #d9e0e8',
        borderRadius: '8px',
        padding: '10px 12px',
        fontSize: '12px',
        color: '#1f2937',
        outline: 'none',
        backgroundColor: '#fbfcfd',
    },

    campoConIcono: {
        position: 'relative',
        display: 'flex',
        alignItems: 'center',
    },

    campoPassword: {
        width: '100%',
        boxSizing: 'border-box',
        border: '1px solid #d9e0e8',
        borderRadius: '8px',
        padding: '10px 36px 10px 12px',
        fontSize: '12px',
        color: '#1f2937',
        outline: 'none',
        backgroundColor: '#fbfcfd',
    },

    botonOjo: {
        position: 'absolute',
        right: '10px',
        border: 'none',
        background: 'transparent',
        padding: 0,
        cursor: 'pointer',
        display: 'flex',
    },

    error: {
        margin: '0 0 12px',
        fontSize: '11px',
        color: '#c0392b',
        textAlign: 'left',
    },

    botonEnviar: {
        width: '100%',
        border: 'none',
        borderRadius: '8px',
        backgroundColor: '#2864df',
        color: '#ffffff',
        fontSize: '13px',
        fontWeight: '600',
        padding: '11px 0',
        cursor: 'pointer',
        marginTop: '4px',
    },

    enlaceOlvido: {
        border: 'none',
        background: 'transparent',
        color: '#2864df',
        fontSize: '11px',
        fontWeight: '600',
        marginTop: '16px',
        cursor: 'pointer',
        padding: 0,
    },

    textoAyuda: {
        marginTop: '10px',
        fontSize: '10px',
        color: '#7b8797',
        textAlign: 'center',
        maxWidth: '280px',
    },

    pie: {
        margin: 0,
        fontSize: '10px',
        color: '#8b95a5',
        textAlign: 'center',
        lineHeight: '16px',
    },
};

export default Login;

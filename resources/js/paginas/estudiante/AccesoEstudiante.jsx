import { useEffect, useRef, useState } from 'react';
import {
    ArrowLeft,
    CheckCircle2,
    GraduationCap,
    Hash,
    IdCard,
    AlertTriangle,
    Info,
    LogIn,
    Send,
    UserPlus,
    UserRound,
} from 'lucide-react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import FondoAcceso, { CabeceraAcceso, TARJETA_ACCESO } from '../../componentes/FondoAcceso';
import Campo from '../../componentes/Campo';
import Seleccion from '../../componentes/Seleccion';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ESPERA_REENVIO = 30;

// Primero se quitan los no dígitos y después se recorta: así un código
// pegado con espacios o guiones («2024 100 07») no pierde dígitos.
const soloDigitos = (texto, largo) => texto.replace(/\D/g, '').slice(0, largo);

function errorDeSis(valor) {
    if (valor === '') return 'Obligatorio';
    return /^\d{5,10}$/.test(valor) ? null : 'Entre 5 y 10 dígitos';
}

const DATOS_VACIOS = {
    nombres: '',
    apellidos: '',
    documento: '',
    facultad_id: '',
    carrera_id: '',
    confirmo: false,
};

function errorDeDatos({
    nombres,
    apellidos,
    documento,
    facultad_id: facultad,
    carrera_id: carrera,
    confirmo,
}) {
    const errores = {};
    if (nombres.trim() === '') errores.nombres = 'Obligatorio';
    if (apellidos.trim() === '') errores.apellidos = 'Obligatorio';
    if (documento.trim() === '') errores.documento = 'Obligatorio';
    if (facultad === '') errores.facultad_id = 'Elija su facultad';
    else if (carrera === '') errores.carrera_id = 'Elija su carrera';
    if (!confirmo) errores.confirmo = 'Confirme que revisó sus datos';
    return errores;
}

function errorDeCodigo(valor) {
    if (valor === '') return 'Obligatorio';
    return /^\d{6}$/.test(valor) ? null : 'Seis dígitos';
}

// Acceso del estudiante, sin cuenta: su código SIS y el código de seis
// dígitos que le llega al correo institucional.
export default function AccesoEstudiante() {
    usarTituloPestana('Acceso de estudiantes');
    const navegar = useNavigate();
    const [conSesion, setConSesion] = useState(false);
    const [sis, setSis] = useState('');
    const [codigo, setCodigo] = useState('');
    const [envio, setEnvio] = useState(null);
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [reenviado, setReenviado] = useState(false);
    // Hasta cuándo espera cada SIS para reenviar: «Otro código SIS» no
    // salta la espera del que ya pidió.
    const [esperas, setEsperas] = useState({});
    const [ahora, setAhora] = useState(() => Date.now());
    const [enviando, setEnviando] = useState(false);
    // Un SIS que aún no está en el padrón: con el código correcto, sus datos.
    const [registro, setRegistro] = useState(false);
    const [carreras, setCarreras] = useState([]);
    const [datos, setDatos] = useState(DATOS_VACIOS);
    const facultades = [
        ...new Map(
            carreras.map((carrera) => [
                carrera.facultad_id,
                { id: carrera.facultad_id, nombre: carrera.facultad },
            ])
        ).values(),
    ];
    const campoCodigo = useRef(null);
    const enviosPorSis = useRef({});
    const espera = Math.max(0, Math.ceil(((esperas[sis] ?? 0) - ahora) / 1000));

    // Con la sesión de estudiante abierta, el acceso lleva al portal.
    useEffect(() => {
        let vigente = true;
        api.get('/estudiante/sesion', { silencioso: true })
            .then(() => vigente && setConSesion(true))
            .catch(() => {});
        return () => {
            vigente = false;
        };
    }, []);

    useEffect(() => {
        if (espera <= 0) return undefined;
        const reloj = setTimeout(() => setAhora(Date.now()), 1000);
        return () => clearTimeout(reloj);
    }, [espera, ahora]);

    if (conSesion) return <Navigate to="/estudiante" replace />;

    async function pedirCodigo(e, reenvio = false) {
        e?.preventDefault();
        const falta = errorDeSis(sis);
        if (falta) return setErrores({ sis: falta });
        if (enviando) return undefined;
        // Ya pidió hace menos de 30 s: se vuelve al código, sin otro correo.
        if (espera > 0 && envio === null && esperas[sis]) {
            setEnvio(enviosPorSis.current[sis] ?? null);
            return undefined;
        }
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        setReenviado(false);
        try {
            const { data } = await api.post('/auth/estudiante/codigo', {
                codigo_universitario: sis,
            });
            setEnvio(data);
            enviosPorSis.current[sis] = data;
            setCodigo('');
            setReenviado(reenvio);
            setEsperas((previas) => ({ ...previas, [sis]: Date.now() + ESPERA_REENVIO * 1000 }));
            setAhora(Date.now());
            if (reenvio) setTimeout(() => campoCodigo.current?.focus(), 0);
        } catch (error) {
            const mensaje =
                estadoDe(error) === 429
                    ? mensajeDe(error, 'Demasiados pedidos')
                    : (erroresDe(error).codigo_universitario ??
                      mensajeDe(error, 'No se pudo enviar'));
            if (envio) setGeneral(mensaje);
            else setErrores({ sis: mensaje });
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    async function entrar(e) {
        e.preventDefault();
        const falta = errorDeCodigo(codigo);
        if (falta) return setErrores({ codigo: falta });
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        setReenviado(false);
        try {
            const { data } = await api.post('/auth/estudiante', {
                codigo_universitario: sis,
                codigo,
            });
            if (data?.registro) {
                const respuesta = await api.get('/auth/estudiante/carreras');
                setCarreras(respuesta.data?.data ?? []);
                setRegistro(true);
                return undefined;
            }
            navegar('/estudiante', { replace: true });
        } catch (error) {
            if (estadoDe(error) === 429) setGeneral(mensajeDe(error, 'Demasiados intentos'));
            else setErrores({ codigo: erroresDe(error).codigo ?? 'Código no válido o vencido' });
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    async function empadronarse(e) {
        e.preventDefault();
        const faltas = errorDeDatos(datos);
        if (Object.keys(faltas).length > 0) return setErrores(faltas);
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        try {
            await api.post('/auth/estudiante/registro', {
                nombres: datos.nombres,
                apellidos: datos.apellidos,
                documento: datos.documento,
                carrera_id: Number(datos.carrera_id),
                confirmo: true,
            });
            navegar('/estudiante', { replace: true });
        } catch (error) {
            if (estadoDe(error) === 422) setErrores(erroresDe(error));
            else setGeneral(mensajeDe(error, 'No se pudo registrar'));
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    const cambiarDato = (campo) => (e) => {
        const valor = e.target.value;
        setDatos((previos) => ({ ...previos, [campo]: valor }));
        setErrores({});
    };

    function otroSis() {
        setRegistro(false);
        setDatos(DATOS_VACIOS);
        setEnvio(null);
        setErrores({});
        setGeneral(null);
        setReenviado(false);
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-md">
                <div className={TARJETA_ACCESO}>
                    <CabeceraAcceso
                        icono={GraduationCap}
                        titulo="Estudiantes"
                        subtitulo="Exámenes y foto"
                    />

                    {registro ? (
                        <form className="space-y-4" noValidate onSubmit={empadronarse}>
                            <div className="flex gap-3 rounded-lg border border-primary-100 bg-primary-50 px-3.5 py-3 text-sm text-slate-700">
                                <Info
                                    className="mt-0.5 h-4 w-4 shrink-0 text-primary-600"
                                    aria-hidden="true"
                                />
                                <p>
                                    Código SIS{' '}
                                    <span className="font-medium text-slate-900">{sis}</span>
                                </p>
                            </div>
                            {general && (
                                <p
                                    role="alert"
                                    className="rounded-lg bg-danger-50 px-3 py-2 text-sm font-medium text-danger-700"
                                >
                                    {general}
                                </p>
                            )}
                            <Campo
                                etiqueta="Nombres"
                                iconoEtiqueta={UserRound}
                                autoComplete="given-name"
                                autoFocus
                                value={datos.nombres}
                                error={errores.nombres}
                                onChange={cambiarDato('nombres')}
                            />
                            <Campo
                                etiqueta="Apellidos"
                                iconoEtiqueta={UserRound}
                                autoComplete="family-name"
                                value={datos.apellidos}
                                error={errores.apellidos}
                                onChange={cambiarDato('apellidos')}
                            />
                            <Campo
                                etiqueta="Carnet"
                                iconoEtiqueta={IdCard}
                                placeholder="5003748"
                                value={datos.documento}
                                error={errores.documento}
                                onChange={cambiarDato('documento')}
                            />
                            <Seleccion
                                etiqueta="Facultad"
                                value={datos.facultad_id}
                                onChange={(e) => {
                                    const facultad = e.target.value;
                                    setDatos((previos) => ({
                                        ...previos,
                                        facultad_id: facultad,
                                        carrera_id: '',
                                    }));
                                    setErrores({});
                                }}
                            >
                                <option value="">Elegir</option>
                                {facultades.map((facultad) => (
                                    <option key={facultad.id} value={facultad.id}>
                                        {facultad.nombre}
                                    </option>
                                ))}
                            </Seleccion>
                            {errores.facultad_id && (
                                <p role="alert" className="-mt-2 text-sm text-danger-700">
                                    {errores.facultad_id}
                                </p>
                            )}
                            <Seleccion
                                etiqueta="Carrera"
                                value={datos.carrera_id}
                                disabled={datos.facultad_id === ''}
                                onChange={cambiarDato('carrera_id')}
                            >
                                <option value="">Elegir</option>
                                {carreras
                                    .filter(
                                        (carrera) =>
                                            String(carrera.facultad_id) === datos.facultad_id
                                    )
                                    .map((carrera) => (
                                        <option key={carrera.id} value={carrera.id}>
                                            {carrera.nombre}
                                        </option>
                                    ))}
                            </Seleccion>
                            {errores.carrera_id && (
                                <p role="alert" className="-mt-2 text-sm text-danger-700">
                                    {errores.carrera_id}
                                </p>
                            )}
                            <p
                                role="alert"
                                className="flex items-start gap-2 rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                            >
                                <AlertTriangle
                                    className="mt-0.5 h-4 w-4 shrink-0"
                                    aria-hidden="true"
                                />
                                Revise bien sus datos · después no se pueden cambiar
                            </p>
                            <label className="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-slate-800">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 rounded border-slate-300"
                                    checked={datos.confirmo}
                                    onChange={(e) => {
                                        const marcado = e.target.checked;
                                        setDatos((previos) => ({ ...previos, confirmo: marcado }));
                                        setErrores({});
                                    }}
                                />
                                Mis datos son correctos
                            </label>
                            {errores.confirmo && (
                                <p role="alert" className="-mt-2 text-sm text-danger-700">
                                    {errores.confirmo}
                                </p>
                            )}
                            <Boton type="submit" className="w-full" cargando={enviando}>
                                {!enviando && <UserPlus className="h-4 w-4" aria-hidden="true" />}
                                Registrarme
                            </Boton>
                            <div className="flex justify-start">
                                <Boton
                                    type="button"
                                    variante="enlace"
                                    tamano="chico"
                                    onClick={otroSis}
                                >
                                    Otro código SIS
                                </Boton>
                            </div>
                        </form>
                    ) : envio ? (
                        <form className="space-y-4" noValidate onSubmit={entrar}>
                            <div className="flex gap-3 rounded-lg border border-primary-100 bg-primary-50 px-3.5 py-3 text-sm text-slate-700">
                                <Info
                                    className="mt-0.5 h-4 w-4 shrink-0 text-primary-600"
                                    aria-hidden="true"
                                />
                                <div className="min-w-0">
                                    <p>
                                        Código SIS{' '}
                                        <span className="font-medium text-slate-900">{sis}</span>
                                    </p>
                                    <p>
                                        Enviado a{' '}
                                        <span className="break-all font-medium text-slate-900">
                                            {envio.correo}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            {reenviado && (
                                <p
                                    role="status"
                                    className="flex items-center gap-2 rounded-lg bg-success-50 px-3 py-2 text-sm font-medium text-success-700"
                                >
                                    <CheckCircle2 className="h-4 w-4 shrink-0" /> Código reenviado
                                </p>
                            )}
                            {general && (
                                <p
                                    role="alert"
                                    className="rounded-lg bg-danger-50 px-3 py-2 text-sm font-medium text-danger-700"
                                >
                                    {general}
                                </p>
                            )}
                            {envio.codigo_prueba && (
                                <p className="rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700">
                                    Modo de prueba · {envio.codigo_prueba}
                                </p>
                            )}
                            <Campo
                                ref={campoCodigo}
                                etiqueta="Código"
                                iconoEtiqueta={Hash}
                                ayuda={`Vence en ${envio.minutos ?? 10} min`}
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                placeholder="000000"
                                autoFocus
                                value={codigo}
                                error={errores.codigo}
                                onChange={(e) => {
                                    setCodigo(soloDigitos(e.target.value, 6));
                                    setErrores({});
                                }}
                            />
                            <Boton type="submit" className="w-full" cargando={enviando}>
                                {!enviando && <LogIn className="h-4 w-4" aria-hidden="true" />}
                                Iniciar sesión
                            </Boton>
                            <div className="flex justify-between gap-2">
                                <Boton
                                    type="button"
                                    variante="enlace"
                                    tamano="chico"
                                    onClick={otroSis}
                                >
                                    Otro código SIS
                                </Boton>
                                <Boton
                                    type="button"
                                    variante="enlace"
                                    tamano="chico"
                                    disabled={enviando || espera > 0}
                                    onClick={() => pedirCodigo(null, true)}
                                >
                                    {espera > 0 ? `Reenviar en ${espera} s` : 'Reenviar'}
                                </Boton>
                            </div>
                        </form>
                    ) : (
                        <form className="space-y-4" noValidate onSubmit={pedirCodigo}>
                            <Campo
                                etiqueta="Código SIS"
                                iconoEtiqueta={IdCard}
                                inputMode="numeric"
                                autoComplete="username"
                                placeholder="202100417"
                                autoFocus
                                value={sis}
                                // El correo es el código SIS con el dominio institucional
                                // (`umss.dominio_correo_estudiantes` en el servidor).
                                ayuda={
                                    /^\d{5,10}$/.test(sis)
                                        ? `Se enviará a ${sis}@est.umss.edu`
                                        : undefined
                                }
                                error={errores.sis}
                                onChange={(e) => {
                                    setSis(soloDigitos(e.target.value, 10));
                                    setErrores({});
                                }}
                            />
                            <Boton type="submit" className="w-full" cargando={enviando}>
                                {!enviando && <Send className="h-4 w-4" aria-hidden="true" />}
                                Enviar código al correo
                            </Boton>
                        </form>
                    )}
                </div>

                <Link
                    to="/login"
                    className={`mt-4 flex min-h-11 w-full items-center justify-center rounded-lg text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                >
                    <ArrowLeft className="mr-1.5 h-4 w-4" aria-hidden="true" /> Cuenta institucional
                </Link>
            </div>
        </FondoAcceso>
    );
}

import { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { CalendarDays, Camera, LogOut, MapPin } from 'lucide-react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import CampoImagen from '../../componentes/CampoImagen';
import EstadoCarga from '../../componentes/EstadoCarga';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import Insignia from '../../componentes/Insignia';
import Logo from '../../componentes/Logo';
import Tarjeta from '../../componentes/Tarjeta';
import { PantallaDeCarga } from '../../sesion/SesionContexto';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import { colorDeAsignatura, diaMes, fechaCorta, fechaLarga, normalizar } from '../../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// La ubicación del aula sin repetir su nombre: en la fuente algunos
// edificios se llaman como el aula («Aula 624»).
function ubicacionSin(aula, ...partes) {
    const propio = normalizar(`Aula ${aula}`);
    return partes
        .filter(Boolean)
        .flatMap((p) => String(p).split(' · '))
        .filter(
            (p) =>
                normalizar(p.trim()) !== propio && normalizar(p.trim()) !== normalizar(String(aula))
        )
        .join(' · ');
}

const ESTADOS = {
    HABILITADO: { texto: 'Habilitado', tono: 'exito' },
    NO_HABILITADO: { texto: 'No habilitado', tono: 'peligro' },
    PENDIENTE: { texto: 'Sin revisar', tono: 'neutro' },
};

const PESTANAS = [
    { ruta: '/estudiante', titulo: 'Mis exámenes', Icono: CalendarDays },
    { ruta: '/estudiante/foto', titulo: 'Mi foto', Icono: Camera },
];

function Examen({ examen, hoy }) {
    const estado = ESTADOS[examen.estado] ?? ESTADOS.PENDIENTE;
    const rendido = examen.fecha < hoy;
    const esHoy = examen.fecha === hoy;

    return (
        <li
            className={`rounded-xl border bg-white p-4 ${esHoy ? 'border-primary-500 ring-1 ring-primary-500' : 'border-slate-200'} ${rendido ? 'opacity-70' : ''}`}
        >
            <div className="flex items-start gap-3">
                <IconoAsignatura
                    nombre={examen.asignatura}
                    color={colorDeAsignatura(examen.asignatura)}
                    tamano="sm"
                />
                <div className="min-w-0 flex-1">
                    <p className="font-semibold leading-snug text-slate-900">{examen.asignatura}</p>
                    <p className="text-sm text-slate-600">{examen.tipo}</p>
                </div>
                {examen.ingreso ? (
                    <Insignia tono={rendido ? 'neutro' : 'exito'} punto>
                        {rendido ? 'Rendido' : `Ingresó ${examen.ingreso}`}
                    </Insignia>
                ) : esHoy ? (
                    <Insignia tono="primario">Hoy</Insignia>
                ) : (
                    rendido && (
                        <Insignia tono={examen.estado === 'HABILITADO' ? 'peligro' : 'neutro'}>
                            {examen.estado === 'HABILITADO' ? 'No asistió' : 'Pasado'}
                        </Insignia>
                    )
                )}
            </div>
            <p className="mt-3 text-sm font-medium text-slate-800">
                {fechaLarga(examen.fecha)} · {examen.hora}
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                <Insignia tono={estado.tono} punto>
                    {estado.texto}
                </Insignia>
                {examen.aula && (
                    <span className="flex min-w-0 items-start gap-1.5 text-sm text-slate-700">
                        <MapPin className="mt-1 h-4 w-4 shrink-0 text-slate-500" />
                        <span className="min-w-0">
                            <span className="block text-lg font-bold leading-snug text-slate-900">
                                Aula {examen.aula}
                            </span>
                            <span className="block break-words">
                                {ubicacionSin(examen.aula, examen.edificio, examen.piso)}
                            </span>
                        </span>
                    </span>
                )}
            </div>
            {examen.motivo && <p className="mt-2 text-sm text-danger-700">{examen.motivo}</p>}
        </li>
    );
}

Examen.propTypes = {
    examen: PropTypes.shape({
        asignatura: PropTypes.string,
        tipo: PropTypes.string,
        fecha: PropTypes.string,
        hora: PropTypes.string,
        estado: PropTypes.string,
        motivo: PropTypes.string,
        ingreso: PropTypes.string,
        aula: PropTypes.string,
        edificio: PropTypes.string,
        piso: PropTypes.string,
    }).isRequired,
    hoy: PropTypes.string.isRequired,
};

function MisExamenes() {
    const consulta = usarConsulta('/estudiante/examenes');
    const examenes = consulta.datos ?? [];
    const hoy = consulta.cuerpo?.hoy ?? '';

    return (
        <EstadoCarga
            cargando={consulta.cargando}
            error={consulta.error}
            vacio={examenes.length === 0}
            textoVacio="Sin exámenes"
            onReintentar={consulta.recargar}
            className="rounded-xl border border-slate-200 bg-white"
        >
            <ul className="space-y-3">
                {examenes.map((x) => (
                    <Examen key={x.id} examen={x} hoy={hoy} />
                ))}
            </ul>
        </EstadoCarga>
    );
}

// Si puede subir la foto ahora: la primera dentro del plazo general, o
// cuando un docente le habilitó subirla o cambiarla.
const puedeSubir = (sesion) =>
    sesion.foto?.puede_subir ??
    (!sesion.estudiante.con_foto && Boolean(sesion.plazo_foto?.abierto));

function MiFoto({ sesion, alGuardar }) {
    const { estudiante, plazo_foto: plazo } = sesion;
    const puede = puedeSubir(sesion);
    const habilitada = sesion.foto?.habilitada_hasta ?? null;
    const [url, setUrl] = useState(null);
    const [archivo, setArchivo] = useState(null);
    const [error, setError] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();

    useEffect(() => {
        if (!estudiante.con_foto) return undefined;
        let vigente = true;
        let creada = null;
        api.get('/estudiante/foto', { responseType: 'blob' })
            .then((r) => {
                if (!vigente) return;
                creada = URL.createObjectURL(r.data);
                setUrl(creada);
            })
            .catch(() => {});
        return () => {
            vigente = false;
            if (creada) URL.revokeObjectURL(creada);
        };
    }, [estudiante.con_foto]);

    const [confirmando, setConfirmando] = useState(false);
    const confirmar = useRef(null);
    const guardarRef = useRef(null);

    // La confirmación lleva el foco a «Confirmar»; al cerrarse vuelve a
    // «Guardar foto».
    useEffect(() => {
        if (confirmando) confirmar.current?.focus();
    }, [confirmando]);

    async function guardar() {
        if (!archivo) return setError('Obligatoria');
        if (!confirmando) return setConfirmando(true);
        if (enviando) return undefined;
        setEnviando(true);
        setError(null);
        try {
            const formulario = new FormData();
            formulario.append('foto', archivo);
            await api.post('/estudiante/foto', formulario);
            setArchivo(null);
            avisar('Foto registrada');
            await alGuardar();
        } catch (fallo) {
            const campo = erroresDe(fallo).foto;
            if (campo) setError(campo);
            else avisar(mensajeDe(fallo, 'No se pudo guardar').replace(/\.$/, ''), 'error');
            // Ya registrada en otra pestaña: se muestra la que quedó.
            if (estadoDe(fallo) === 409) await alGuardar();
        } finally {
            setEnviando(false);
            setConfirmando(false);
        }
        return undefined;
    }

    const rango = plazo ? `${diaMes(plazo.desde)} – ${fechaCorta(plazo.hasta)}` : null;

    return (
        <Tarjeta titulo="Mi foto">
            {estudiante.con_foto && !puede ? (
                <div className="flex flex-col items-center gap-3">
                    {url && (
                        <img
                            src={url}
                            alt="Mi foto"
                            className="h-56 w-auto rounded-xl border border-slate-200 object-cover"
                        />
                    )}
                    <span className="flex justify-center">
                        <Insignia tono="exito" punto>
                            Registrada
                        </Insignia>
                    </span>
                </div>
            ) : puede ? (
                <div className="space-y-4">
                    {habilitada ? (
                        <p className="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-800">
                            {estudiante.con_foto ? 'Cambio de foto' : 'Subida'} habilitada hasta el{' '}
                            {fechaCorta(habilitada)}
                            {sesion.foto?.habilitada_por && ` · por ${sesion.foto.habilitada_por}`}
                        </p>
                    ) : (
                        <p className="text-sm text-slate-700">Plazo · {rango}</p>
                    )}
                    {estudiante.con_foto && url && (
                        <div className="flex items-center gap-3">
                            <img
                                src={url}
                                alt="Foto actual"
                                className="h-20 w-auto rounded-lg border border-slate-200 object-cover"
                            />
                            <span className="text-xs text-slate-600">
                                Foto actual · se reemplaza
                            </span>
                        </div>
                    )}
                    <ul className="list-inside list-disc text-sm text-slate-700">
                        <li>Rostro de frente</li>
                        <li>Sin lentes oscuros ni gorra</li>
                        <li>Fondo claro</li>
                    </ul>
                    <CampoImagen
                        etiqueta="Foto"
                        archivo={archivo}
                        onCambiar={(f) => {
                            setArchivo(f);
                            setError(null);
                        }}
                        error={error}
                        camara={null}
                        minimo={240}
                    />
                    {confirmando ? (
                        <div
                            role="alertdialog"
                            aria-label="Confirmar foto"
                            className="space-y-3 rounded-xl border border-warning-200 bg-warning-50 p-4"
                        >
                            <p className="text-sm font-medium text-warning-800">
                                {estudiante.con_foto
                                    ? 'Reemplaza la foto actual'
                                    : 'Después no se puede cambiar'}
                            </p>
                            <div className="flex gap-2">
                                <Boton
                                    type="button"
                                    variante="secundario"
                                    className="flex-1"
                                    onClick={() => {
                                        // Quita la elegida para tomar otra.
                                        setArchivo(null);
                                        setConfirmando(false);
                                        setTimeout(() => guardarRef.current?.focus(), 0);
                                    }}
                                >
                                    Elegir otra
                                </Boton>
                                <Boton
                                    ref={confirmar}
                                    type="button"
                                    className="flex-1"
                                    onClick={guardar}
                                    disabled={enviando}
                                >
                                    Confirmar
                                </Boton>
                            </div>
                        </div>
                    ) : (
                        <>
                            <Boton
                                ref={guardarRef}
                                type="button"
                                className="w-full"
                                onClick={guardar}
                            >
                                Guardar foto
                            </Boton>
                            <p className="text-xs text-slate-500">Una sola vez</p>
                        </>
                    )}
                </div>
            ) : (
                <p className="py-6 text-center text-sm text-slate-600">
                    {rango ? `Plazo cerrado · ${rango}` : 'Sin plazo de fotos'}
                </p>
            )}
            {aviso}
        </Tarjeta>
    );
}

MiFoto.propTypes = {
    sesion: PropTypes.shape({
        estudiante: PropTypes.shape({ con_foto: PropTypes.bool }).isRequired,
        plazo_foto: PropTypes.shape({
            desde: PropTypes.string,
            hasta: PropTypes.string,
            abierto: PropTypes.bool,
        }),
        foto: PropTypes.shape({
            puede_subir: PropTypes.bool,
            habilitada_hasta: PropTypes.string,
            habilitada_por: PropTypes.string,
        }),
    }).isRequired,
    alGuardar: PropTypes.func.isRequired,
};

// Lo que ve el estudiante: sus exámenes (hora, aula, si está habilitado) y
// su foto. Sin sesión vuelve al acceso por código.
export default function PortalEstudiante() {
    usarTituloPestana('Mis exámenes');
    const { pathname } = useLocation();
    const navegar = useNavigate();
    const [sesion, setSesion] = useState(null);
    const [sinSesion, setSinSesion] = useState(false);
    const [fallo, setFallo] = useState(false);
    const [saliendo, setSaliendo] = useState(false);
    const [avisoSalida, avisarSalida] = useAviso();

    const cargar = () => {
        setFallo(false);
        return api
            .get('/estudiante/sesion', { silencioso: true })
            .then((r) => setSesion(r.data))
            .catch((error) => {
                if (estadoDe(error) === 401) setSinSesion(true);
                else setFallo(true);
            });
    };

    useEffect(() => {
        cargar();
    }, []);

    if (sinSesion) return <Navigate to="/estudiante/ingreso" replace />;
    if (fallo && !sesion) {
        return (
            <div className="flex min-h-dvh items-center justify-center bg-slate-100 px-4">
                <EstadoCarga error={new Error('sin conexión')} onReintentar={cargar} />
            </div>
        );
    }
    if (!sesion) return <PantallaDeCarga />;

    const { estudiante } = sesion;

    // Sin respuesta del servidor la sesión sigue abierta allá: se avisa y se
    // queda aquí, para no dejarla abierta en una computadora compartida.
    async function salir() {
        if (saliendo) return;
        setSaliendo(true);
        try {
            await api.delete('/auth/estudiante', { silencioso: true });
        } catch (error) {
            if (!error?.response) {
                setSaliendo(false);
                avisarSalida('No se pudo cerrar sesión · revise la conexión', 'error');
                return;
            }
        }
        navegar('/estudiante/ingreso', { replace: true });
    }

    return (
        <div className="flex min-h-dvh flex-col bg-slate-100">
            <header className="sticky top-0 z-10 border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-2.5">
                    <div className="flex min-w-0 items-center gap-3">
                        <Logo className="h-8 w-8" />
                        <span className="min-w-0">
                            <span className="line-clamp-2 break-words text-sm font-medium leading-tight text-slate-800">
                                {estudiante.nombre}
                            </span>
                            <span className="block truncate text-xs text-slate-600">
                                Estudiante · {estudiante.codigo}
                            </span>
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={salir}
                        className={`flex h-11 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-medium text-slate-700 hover:bg-danger-50 hover:text-danger-700 ${FOCO}`}
                    >
                        <LogOut className="h-[18px] w-[18px]" /> Cerrar sesión
                    </button>
                </div>
                <nav aria-label="Estudiante" className="mx-auto flex max-w-2xl gap-1 px-4">
                    {PESTANAS.map(({ ruta, titulo, Icono }) => {
                        const activa = pathname === ruta;
                        return (
                            <Link
                                key={ruta}
                                to={ruta}
                                aria-current={activa ? 'page' : undefined}
                                className={`flex min-h-11 items-center gap-2 border-b-2 px-3 text-sm font-medium ${activa ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-600 hover:text-slate-900'} ${FOCO}`}
                            >
                                <Icono className="h-4 w-4" />
                                {titulo}
                                {ruta === '/estudiante/foto' && puedeSubir(sesion) && (
                                    <span
                                        className="h-2 w-2 rounded-full bg-danger-600"
                                        aria-label="Pendiente"
                                    />
                                )}
                            </Link>
                        );
                    })}
                </nav>
            </header>
            <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-5">
                <h1 className="sr-only">
                    {PESTANAS.find((p) => p.ruta === pathname)?.titulo ?? 'Mis exámenes'}
                </h1>
                {pathname === '/estudiante/foto' ? (
                    <MiFoto sesion={sesion} alGuardar={cargar} />
                ) : (
                    <MisExamenes />
                )}
            </main>
            {avisoSalida}
        </div>
    );
}

import React from 'react';
import PropTypes from 'prop-types';
import {
    DatabaseBackup,
    FileSpreadsheet,
    History,
    LayoutGrid,
    Menu,
    MonitorCheck,
    QrCode,
    ShieldCheck,
    UploadCloud,
    User,
    UserCog,
    Users,
} from 'lucide-react';

/**
 * Carga masiva de estudiantes (HU-04), lado Frontend. Solo frontend: no hay
 * backend todavia para esta historia (no existe rama ni endpoint), asi que
 * el parseo y la validacion del archivo se hacen aca mismo, en el navegador,
 * contra un padron de ejemplo. El dia que exista el endpoint real, este
 * mismo flujo (elegir archivo -> parsear -> mostrar resultado) se reutiliza,
 * solo cambia de donde sale la validacion.
 *
 * HU-03 (registro individual de estudiantes, el resto del "Padron") no es
 * parte de esta tarea y no esta implementado aca.
 *
 * Plantilla esperada, en este orden de columnas:
 * codigo_universitario, documento_identidad, nombres, apellidos, carrera
 */
const columnas = ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos', 'carrera'];

const padronExistente = [
    { codigo_universitario: '201901349', documento_identidad: '8452110' },
    { codigo_universitario: '202104821', documento_identidad: '9013452' },
];

function descargarPlantilla() {
    const contenido = columnas.join(',') + '\n';
    const enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(new Blob([contenido], { type: 'text/csv' }));
    enlace.download = 'plantilla_padron.csv';
    enlace.click();
    URL.revokeObjectURL(enlace.href);
}

function parsearLineaCsv(linea) {
    return linea.split(',').map((valor) => valor.trim().replace(/^"|"$/g, ''));
}

function procesarContenido(texto) {
    const lineas = texto
        .split(/\r?\n/)
        .map((linea) => linea.trim())
        .filter((linea) => linea.length > 0);

    if (lineas.length === 0) {
        return { aceptados: 0, actualizados: 0, detalles: [] };
    }

    const primeraFilaEsEncabezado = parsearLineaCsv(lineas[0])
        .map((valor) => valor.toLowerCase())
        .join(',')
        .includes('codigo');

    const filas = primeraFilaEsEncabezado ? lineas.slice(1) : lineas;

    const codigosVistos = new Set();
    const documentosVistos = new Set();
    let aceptados = 0;
    let actualizados = 0;
    const detalles = [];

    filas.forEach((linea, indice) => {
        const numeroFila = indice + (primeraFilaEsEncabezado ? 2 : 1);
        const valores = parsearLineaCsv(linea);
        const [codigo, documento, nombres, apellidos, carrera] = valores;

        if (
            !codigo?.trim() ||
            !documento?.trim() ||
            !nombres?.trim() ||
            !apellidos?.trim() ||
            !carrera?.trim()
        ) {
            detalles.push({
                fila: numeroFila,
                motivo: 'Faltan campos obligatorios (se esperan 5 columnas).',
                tipo: 'rechazado',
            });
            return;
        }

        if (codigosVistos.has(codigo)) {
            detalles.push({
                fila: numeroFila,
                motivo: `Código universitario "${codigo}" repetido en el archivo.`,
                tipo: 'rechazado',
            });
            return;
        }

        if (documentosVistos.has(documento)) {
            detalles.push({
                fila: numeroFila,
                motivo: `Documento de identidad "${documento}" repetido en el archivo.`,
                tipo: 'rechazado',
            });
            return;
        }

        codigosVistos.add(codigo);
        documentosVistos.add(documento);

        const yaExiste = padronExistente.some(
            (estudiante) =>
                estudiante.codigo_universitario === codigo ||
                estudiante.documento_identidad === documento
        );

        if (yaExiste) {
            actualizados += 1;
            detalles.push({
                fila: numeroFila,
                motivo: `Estudiante ya registrado: se actualiza en vez de duplicarse.`,
                tipo: 'actualizado',
            });
        } else {
            aceptados += 1;
        }
    });

    return { aceptados, actualizados, detalles };
}

function CargaMasiva({ onNavigate }) {
    const [menuAbierto, setMenuAbierto] = React.useState(false);
    const [archivo, setArchivo] = React.useState(null);
    const [procesando, setProcesando] = React.useState(false);
    const [resultado, setResultado] = React.useState(null);
    const [error, setError] = React.useState(null);

    function navegar(clave) {
        setMenuAbierto(false);
        onNavigate?.(clave);
    }

    function manejarSeleccionArchivo(evento) {
        const archivoSeleccionado = evento.target.files?.[0] ?? null;
        setArchivo(archivoSeleccionado);
        setResultado(null);
        setError(null);
    }

    function manejarCargar() {
        if (!archivo) {
            setError('Selecciona un archivo antes de cargarlo.');
            return;
        }

        const extension = archivo.name.split('.').pop()?.toLowerCase();

        if (!['csv', 'xlsx', 'xls'].includes(extension ?? '')) {
            setError('El archivo debe ser una hoja de cálculo (.xlsx, .xls) o CSV.');
            return;
        }

        if (extension !== 'csv') {
            setError(
                'La vista previa en el navegador por ahora solo procesa CSV; .xlsx/.xls quedan para cuando exista el backend real.'
            );
            return;
        }

        setProcesando(true);
        setError(null);

        const lector = new FileReader();

        lector.onload = () => {
            const resultadoProcesado = procesarContenido(String(lector.result ?? ''));
            setResultado(resultadoProcesado);
            setProcesando(false);
        };

        lector.onerror = () => {
            setError('No se pudo leer el archivo. Intenta de nuevo.');
            setProcesando(false);
        };

        lector.readAsText(archivo);
    }

    const rechazados = resultado?.detalles.filter((d) => d.tipo === 'rechazado') ?? [];

    return (
        <div className="flex min-h-screen w-full bg-white font-sans text-slate-800">
            {menuAbierto && (
                <div
                    className="fixed inset-0 z-30 bg-slate-900/40 md:hidden"
                    onClick={() => setMenuAbierto(false)}
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-200 md:static md:translate-x-0 ${
                    menuAbierto ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center gap-3 border-b border-slate-100 px-5">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white">
                        <ShieldCheck className="h-5 w-5" strokeWidth={2} />
                    </div>

                    <div>
                        <div className="text-sm leading-tight font-bold text-slate-800">UMSS</div>
                        <div className="mt-0.5 text-[11px] tracking-wide text-slate-500">
                            CONTROL DE INGRESO
                        </div>
                    </div>
                </div>

                <div className="px-3 py-4">
                    <div className="px-3 pb-2 text-[11px] font-semibold tracking-wide text-slate-400">
                        ADMINISTRADOR
                    </div>

                    <MenuItem
                        icon={<Users className="h-[18px] w-[18px]" />}
                        text="Padrón"
                        selected
                    />
                    <MenuItem
                        icon={<LayoutGrid className="h-[18px] w-[18px]" />}
                        text="Asignaturas y ambientes"
                        onClick={() => navegar('asignaturas')}
                    />
                    <MenuItem
                        icon={<QrCode className="h-[18px] w-[18px]" />}
                        text="Códigos QR"
                        onClick={() => alert('Códigos QR: próximamente')}
                    />
                    <MenuItem
                        icon={<UserCog className="h-[18px] w-[18px]" />}
                        text="Usuarios y roles"
                        onClick={() => navegar('usuarios')}
                    />
                    <MenuItem
                        icon={<History className="h-[18px] w-[18px]" />}
                        text="Bitácora"
                        onClick={() => navegar('bitacora')}
                    />
                    <MenuItem
                        icon={<DatabaseBackup className="h-[18px] w-[18px]" />}
                        text="Respaldo"
                        onClick={() => alert('Respaldo: próximamente')}
                    />
                </div>
            </aside>

            <main className="min-w-0 flex-1 bg-slate-50">
                <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 md:hidden"
                            onClick={() => setMenuAbierto(true)}
                            aria-label="Abrir menú"
                        >
                            <Menu className="h-5 w-5" strokeWidth={1.75} />
                        </button>

                        <div className="flex items-center gap-2 text-sm font-medium text-slate-800">
                            <MonitorCheck className="h-[18px] w-[18px] text-blue-600" />
                            <span className="hidden sm:inline">
                                Sistema Institucional de Verificación
                            </span>
                        </div>
                    </div>

                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white">
                        <User className="h-[18px] w-[18px]" strokeWidth={1.75} />
                    </div>
                </header>

                <section className="p-4 md:p-6 lg:p-8">
                    <h1 className="text-2xl font-bold text-slate-900">Padrón</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Carga masiva de estudiantes desde un archivo
                    </p>

                    <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-col items-center rounded-lg border-2 border-dashed border-slate-300 px-6 py-10 text-center">
                            <UploadCloud className="h-9 w-9 text-blue-600" strokeWidth={1.5} />

                            <p className="mt-3 text-sm font-semibold text-slate-700">
                                {archivo ? archivo.name : 'Selecciona un archivo para cargar'}
                            </p>

                            <p className="mt-1 text-xs text-slate-500">
                                Hoja de cálculo (.xlsx, .xls) o CSV
                            </p>

                            <label className="mt-4 cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Elegir archivo
                                <input
                                    type="file"
                                    accept=".csv,.xlsx,.xls"
                                    className="hidden"
                                    onChange={manejarSeleccionArchivo}
                                />
                            </label>
                        </div>

                        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                type="button"
                                className="flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700"
                                onClick={descargarPlantilla}
                            >
                                <FileSpreadsheet className="h-4 w-4" />
                                Descargar plantilla
                            </button>

                            <button
                                type="button"
                                className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                onClick={manejarCargar}
                                disabled={procesando}
                            >
                                {procesando ? 'Procesando…' : 'Cargar archivo'}
                            </button>
                        </div>

                        {error && (
                            <p role="alert" className="mt-4 text-sm text-red-600">
                                {error}
                            </p>
                        )}
                    </div>

                    {resultado && (
                        <div className="mt-6">
                            <div className="flex flex-wrap gap-3">
                                <span className="rounded-lg bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700">
                                    {resultado.aceptados} nuevos
                                </span>
                                <span className="rounded-lg bg-sky-100 px-4 py-2 text-sm font-semibold text-sky-700">
                                    {resultado.actualizados} actualizados
                                </span>
                                <span className="rounded-lg bg-rose-100 px-4 py-2 text-sm font-semibold text-rose-700">
                                    {rechazados.length} rechazados
                                </span>
                            </div>

                            {resultado.detalles.length > 0 && (
                                <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                                    <div className="grid min-w-[480px] grid-cols-[0.3fr_1fr] items-center gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                                        <div>FILA</div>
                                        <div>DETALLE</div>
                                    </div>

                                    {resultado.detalles.map((detalle, indice) => (
                                        <div
                                            key={indice}
                                            className="grid min-w-[480px] grid-cols-[0.3fr_1fr] items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0"
                                        >
                                            <div className="font-mono text-slate-500">
                                                {detalle.fila}
                                            </div>
                                            <div
                                                className={
                                                    detalle.tipo === 'rechazado'
                                                        ? 'text-rose-600'
                                                        : 'text-sky-600'
                                                }
                                            >
                                                {detalle.motivo}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    <p className="mt-6 text-xs text-slate-400">
                        Esta pantalla valida el archivo en el navegador, contra un padrón de
                        ejemplo: todavía no hay un backend real para HU-04. El registro individual
                        de estudiantes (HU-03) es una pantalla aparte, sin construir todavía.
                    </p>
                </section>
            </main>
        </div>
    );
}

CargaMasiva.propTypes = {
    onNavigate: PropTypes.func,
};

function MenuItem({ icon, text, selected, onClick }) {
    return (
        <div
            className={`mb-1 flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm ${
                selected
                    ? 'bg-blue-600 font-semibold text-white'
                    : 'text-slate-600 hover:bg-slate-100'
            }`}
            onClick={onClick}
        >
            <span className="flex w-5 items-center justify-center">{icon}</span>
            <span>{text}</span>
        </div>
    );
}

MenuItem.propTypes = {
    icon: PropTypes.node.isRequired,
    text: PropTypes.string.isRequired,
    selected: PropTypes.bool,
    onClick: PropTypes.func,
};

export default CargaMasiva;

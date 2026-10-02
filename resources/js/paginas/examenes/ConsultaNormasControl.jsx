import React from 'react';
import SelectorEstudiante from '../../componentes/SelectorEstudiante';

function errorDe(error, fallback) {
    const data = error?.response?.data;
    return data?.message ?? fallback;
}

export default function ConsultaNormasControl() {
    const [examenes, setExamenes] = React.useState([]);
    const [estudiantes, setEstudiantes] = React.useState([]);
    const [examenId, setExamenId] = React.useState('');
    const [estudianteId, setEstudianteId] = React.useState('');
    const [normas, setNormas] = React.useState([]);
    const [cargando, setCargando] = React.useState(true);
    const [consultando, setConsultando] = React.useState(false);
    const [error, setError] = React.useState('');
    const [consultado, setConsultado] = React.useState(false);

    React.useEffect(() => {
        let vigente = true;

        Promise.all([
            window.axios.get('/api/control/examenes'),
            window.axios.get('/api/control/estudiantes'),
        ])
            .then(([respuestaExamenes, respuestaEstudiantes]) => {
                if (!vigente) return;
                setExamenes(respuestaExamenes.data.data ?? []);
                setEstudiantes(respuestaEstudiantes.data.data ?? []);
            })
            .catch((requestError) => {
                if (vigente) setError(errorDe(requestError, 'No se pudieron cargar los datos.'));
            })
            .finally(() => {
                if (vigente) setCargando(false);
            });

        return () => {
            vigente = false;
        };
    }, []);

    async function consultar(event) {
        event.preventDefault();
        setError('');
        setNormas([]);
        setConsultado(false);

        if (!examenId || !estudianteId) {
            setError('Selecciona un examen y un estudiante.');
            return;
        }

        setConsultando(true);
        try {
            const response = await window.axios.get(
                `/api/control/examenes/${examenId}/estudiantes/${estudianteId}/normas`
            );
            setNormas(response.data.data ?? []);
            setConsultado(true);
        } catch (requestError) {
            setError(errorDe(requestError, 'No se pudieron consultar las normas.'));
        } finally {
            setConsultando(false);
        }
    }

    const examen = examenes.find((item) => String(item.id) === examenId);
    const estudiante = estudiantes.find((item) => String(item.id) === estudianteId);

    return (
        <section className="min-w-0 p-4 sm:p-8">
            <header className="mb-6">
                <h1 className="text-2xl font-bold text-slate-900">Consulta de normas</h1>
                <p className="mt-1 text-sm text-slate-500">
                    En puerta, consulta las normas generales del examen y las particulares del
                    estudiante.
                </p>
            </header>

            <form
                onSubmit={consultar}
                className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="block text-sm font-medium text-slate-700">
                        Examen
                        <select
                            value={examenId}
                            onChange={(event) => setExamenId(event.target.value)}
                            disabled={cargando}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                            <option value="">Selecciona un examen</option>
                            {examenes.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.grupo?.asignatura?.nombre} · {item.nombre} · grupo{' '}
                                    {item.grupo?.codigo_grupo} · {String(item.fecha).slice(0, 10)}
                                </option>
                            ))}
                        </select>
                        {!cargando && examenes.length === 0 && (
                            <span className="mt-1 block text-xs text-amber-700">
                                No hay exámenes disponibles para consultar.
                            </span>
                        )}
                    </label>
                    <div className="min-w-0 text-sm font-medium text-slate-700">
                        <label htmlFor="consulta-estudiante">Estudiante</label>
                        <SelectorEstudiante
                            id="consulta-estudiante"
                            estudiantes={estudiantes}
                            value={estudianteId}
                            onChange={setEstudianteId}
                            disabled={cargando}
                        />
                        {!cargando && estudiantes.length === 0 && (
                            <span className="mt-1 block text-xs text-amber-700">
                                No hay estudiantes disponibles en el padrón.
                            </span>
                        )}
                    </div>
                </div>
                <button
                    type="submit"
                    disabled={cargando || consultando}
                    className="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                >
                    {cargando ? 'Cargando…' : consultando ? 'Consultando…' : 'Consultar normas'}
                </button>
            </form>

            {error && (
                <p
                    role="alert"
                    className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                >
                    {error}
                </p>
            )}

            {consultado && (
                <section className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-bold text-slate-900">Normas aplicables</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {estudiante?.nombre} · {estudiante?.codigo_universitario} —{' '}
                        {examen?.grupo?.asignatura?.nombre} · {examen?.nombre}
                    </p>
                    {normas.length ? (
                        <ol className="mt-4 divide-y divide-slate-100">
                            {normas.map((norma) => (
                                <li key={norma.id} className="py-4">
                                    <span
                                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${norma.alcance === 'general' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800'}`}
                                    >
                                        {norma.alcance === 'general' ? 'General' : 'Particular'}
                                    </span>
                                    <p className="mt-2 text-sm font-medium text-slate-800">
                                        {norma.texto}
                                    </p>
                                    {norma.alcance === 'particular' && norma.motivo && (
                                        <p className="mt-1 text-sm text-slate-600">
                                            Motivo: {norma.motivo}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="mt-4 text-sm text-slate-500">
                            Este estudiante no tiene normas particulares y el examen no tiene normas
                            generales.
                        </p>
                    )}
                </section>
            )}
        </section>
    );
}

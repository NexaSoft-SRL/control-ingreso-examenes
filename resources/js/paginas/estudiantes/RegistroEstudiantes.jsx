import React, { useEffect, useState } from 'react';
import axios from 'axios';

export default function RegistroEstudiantes() {
    const [estudiantes, setEstudiantes] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState('');

    // Estados para el formulario y edición
    const [mostrarModal, setMostrarModal] = useState(false);
    const [estudianteEditando, setEstudianteEditando] = useState(null);
    const [codigoUniversitario, setCodigoUniversitario] = useState('');
    const [carrera, setCarrera] = useState('');
    const [apellido, setApellido] = useState('');
    const [ci, setCi] = useState('');
    const [nombre, setNombre] = useState('');
    const [correo, setCorreo] = useState('');
    const [activo, setActivo] = useState(true);

    useEffect(() => {
        const cargarEstudiantes = async () => {
            try {
                const response = await axios.get('/api/students');
                setEstudiantes(response.data);
            } catch {
                setError('No se pudo cargar el padrón estudiantil.');
            } finally {
                setCargando(false);
            }
        };

        cargarEstudiantes();
    }, []);

    const abrirNuevoEstudiante = () => {
        setEstudianteEditando(null);
        setCodigoUniversitario('');
        setCarrera('');
        setApellido('');
        setCi('');
        setNombre('');
        setCorreo('');
        setActivo(true);
        setError('');
        setMostrarModal(true);
    };

    const abrirEditarEstudiante = (index) => {
        const est = estudiantes[index];
        setEstudianteEditando(index);
        setCodigoUniversitario(est.codigo_universitario ?? '');
        setCarrera(est.carrera ?? '');
        setApellido(est.apellido);
        setCi(est.ci);
        setNombre(est.nombre);
        setCorreo(est.correo);
        setActivo(est.activo);
        setError('');
        setMostrarModal(true);
    };

    const limpiarFormulario = () => {
        setEstudianteEditando(null);
        setCodigoUniversitario('');
        setCarrera('');
        setApellido('');
        setCi('');
        setNombre('');
        setCorreo('');
        setActivo(true);
        setMostrarModal(false);
    };

    // HU-03: la baja no borra al estudiante, lo deja inactivo. Reactivarlo
    // es el mismo camino que una edicion.
    const cambiarEstado = async (index) => {
        const est = estudiantes[index];
        setError('');

        try {
            const response = est.activo
                ? await axios.delete(`/api/students/${est.id}`)
                : await axios.put(`/api/students/${est.id}`, {
                      nombre: est.nombre,
                      apellido: est.apellido,
                      ci: est.ci,
                      correo: est.correo,
                      codigo_universitario: est.codigo_universitario,
                      carrera: est.carrera,
                      activo: true,
                  });

            setEstudiantes((actuales) =>
                actuales.map((actual, posicion) => (posicion === index ? response.data : actual))
            );
        } catch (estadoError) {
            const mensaje = estadoError.response?.data?.message;
            setError(mensaje || 'No se pudo cambiar el estado del estudiante.');
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');

        // El backlog pide codigo universitario, documento, nombres,
        // apellidos y carrera: los dos primeros faltaban en el formulario.
        const datos = {
            codigo_universitario: codigoUniversitario.trim() || null,
            carrera: carrera.trim() || null,
            nombre,
            apellido,
            ci,
            correo: correo.trim() || null,
            activo,
        };

        try {
            if (estudianteEditando === null) {
                const response = await axios.post('/api/students', datos);
                setEstudiantes((actuales) => [...actuales, response.data]);
            } else {
                const estudiante = estudiantes[estudianteEditando];
                const response = await axios.put(`/api/students/${estudiante.id}`, datos);
                setEstudiantes((actuales) =>
                    actuales.map((actual, index) =>
                        index === estudianteEditando ? response.data : actual
                    )
                );
            }

            limpiarFormulario();
        } catch (submitError) {
            const mensaje = submitError.response?.data?.message;
            setError(mensaje || 'No se pudo guardar el estudiante.');
        }
    };

    return (
        <div className="min-w-0 p-4 sm:p-8">
            {/* Cabecera Padrón */}
            <div className="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Padrón</h1>
                    <p className="text-sm text-gray-500">Gestión del padrón estudiantil</p>
                </div>
                <button
                    onClick={abrirNuevoEstudiante}
                    className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 sm:w-auto sm:text-base"
                >
                    + Nuevo estudiante
                </button>
            </div>

            {error && (
                <div className="mb-6 rounded-lg border-l-4 border-red-400 bg-red-50 p-4 text-sm text-red-700">
                    {error}
                </div>
            )}

            {/* Formulario desplegable para "+ Nuevo estudiante" o "Editar" */}
            {mostrarModal && (
                <form
                    onSubmit={handleSubmit}
                    className="bg-white p-6 rounded-xl shadow-md mb-6 border border-blue-100"
                >
                    <h2 className="text-lg font-bold mb-4 text-gray-700">
                        {estudianteEditando === null
                            ? 'Registrar nuevo estudiante'
                            : 'Editar estudiante'}
                    </h2>
                    <div className="grid grid-cols-1 gap-4 mb-4 md:grid-cols-4">
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                CÓDIGO UNIVERSITARIO
                            </label>
                            <input
                                type="text"
                                value={codigoUniversitario}
                                onChange={(e) => setCodigoUniversitario(e.target.value)}
                                placeholder="Ej. 201900123"
                                className="w-full p-2 border rounded text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                CARRERA
                            </label>
                            <input
                                type="text"
                                value={carrera}
                                onChange={(e) => setCarrera(e.target.value)}
                                placeholder="Ej. Ingeniería de Sistemas"
                                className="w-full p-2 border rounded text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                APELLIDO
                            </label>
                            <input
                                type="text"
                                value={apellido}
                                onChange={(e) => setApellido(e.target.value)}
                                placeholder="Ej. Pérez"
                                className="w-full p-2 border rounded text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                C.I.
                            </label>
                            <input
                                type="text"
                                value={ci}
                                onChange={(e) => setCi(e.target.value)}
                                placeholder="Ej. 7928194-CBB"
                                className="w-full p-2 border rounded text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                NOMBRE
                            </label>
                            <input
                                type="text"
                                value={nombre}
                                onChange={(e) => setNombre(e.target.value)}
                                placeholder="Ej. Juan"
                                className="w-full p-2 border rounded text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                CORREO (OPCIONAL)
                            </label>
                            <input
                                type="email"
                                value={correo}
                                onChange={(e) => setCorreo(e.target.value)}
                                placeholder="Ej. juan@umss.edu"
                                className="w-full p-2 border rounded text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                ESTADO
                            </label>
                            <select
                                value={activo ? 'ACTIVO' : 'INACTIVO'}
                                onChange={(e) => setActivo(e.target.value === 'ACTIVO')}
                                className="w-full p-2 border rounded text-sm bg-white"
                            >
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={limpiarFormulario}
                            className="px-4 py-2 border rounded text-sm text-gray-600 hover:bg-gray-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 font-medium"
                        >
                            {estudianteEditando === null ? 'Guardar Estudiante' : 'Guardar Cambios'}
                        </button>
                    </div>
                </form>
            )}

            {/* Tabla Estilo Mockup */}
            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-225 border-collapse text-left">
                        <thead className="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider border-b border-gray-100">
                            <tr>
                                <th className="w-32 whitespace-nowrap p-4 font-semibold">Código</th>
                                <th className="w-36 whitespace-nowrap p-4 font-semibold">
                                    Apellido
                                </th>
                                <th className="min-w-48 p-4 font-semibold">Nombre</th>
                                <th className="w-36 whitespace-nowrap p-4 font-semibold">C.I.</th>
                                <th className="min-w-48 p-4 font-semibold">Carrera</th>
                                <th className="min-w-32 p-4 font-semibold">Estado</th>
                                <th className="min-w-40 p-4 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 text-sm text-gray-600">
                            {cargando ? (
                                <tr>
                                    <td colSpan="7" className="p-6 text-center text-gray-400">
                                        Cargando estudiantes...
                                    </td>
                                </tr>
                            ) : (
                                estudiantes.map((est, index) => (
                                    <tr key={est.id} className="hover:bg-gray-50 transition">
                                        <td className="p-4 font-mono text-xs whitespace-nowrap text-gray-500">
                                            {est.codigo_universitario || '—'}
                                        </td>
                                        <td className="whitespace-nowrap p-4 font-medium text-gray-700">
                                            {est.apellido}
                                        </td>
                                        <td className="min-w-48 p-4 font-medium text-gray-900">
                                            {est.nombre}
                                        </td>
                                        <td className="whitespace-nowrap p-4">{est.ci}</td>
                                        <td className="min-w-48 p-4">{est.carrera || '—'}</td>
                                        <td className="p-4">
                                            <span
                                                className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${
                                                    !est.activo
                                                        ? 'bg-gray-100 text-gray-600'
                                                        : 'bg-green-50 text-green-600'
                                                }`}
                                            >
                                                <span
                                                    className={`w-1.5 h-1.5 rounded-full ${
                                                        !est.activo ? 'bg-gray-400' : 'bg-green-500'
                                                    }`}
                                                ></span>{' '}
                                                {est.activo ? 'ACTIVO' : 'INACTIVO'}
                                            </span>
                                        </td>
                                        <td className="p-4 text-right">
                                            <button
                                                onClick={() => abrirEditarEstudiante(index)}
                                                className="text-blue-600 hover:text-blue-800 font-medium text-sm"
                                            >
                                                Editar
                                            </button>
                                            <button
                                                onClick={() => cambiarEstado(index)}
                                                className={`ml-3 text-sm font-medium ${
                                                    est.activo
                                                        ? 'text-gray-500 hover:text-gray-700'
                                                        : 'text-green-600 hover:text-green-800'
                                                }`}
                                            >
                                                {est.activo ? 'Dar de baja' : 'Reactivar'}
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="p-4 bg-gray-50 text-xs text-gray-400 border-t border-gray-100">
                    Mostrando {estudiantes.length}{' '}
                    {estudiantes.length === 1 ? 'estudiante' : 'estudiantes'}
                </div>
            </div>
        </div>
    );
}

import React, { useEffect, useState } from 'react';
import axios from 'axios';
import PropTypes from 'prop-types';

// --- 1. MODAL INTEGRADO ---
const FormularioExamenModal = ({ isOpen, onClose, examenEditar, alGuardar }) => {
    const [formData, setFormData] = useState({
        grupo_id: '',
        nombre: 'Primer parcial',
        fecha: '',
        hora_inicio: '',
        duracion_minutos: '',
    });
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        if (examenEditar) {
            setFormData({
                grupo_id: examenEditar.grupo.id,
                nombre: examenEditar.nombre,
                fecha: examenEditar.fecha,
                hora_inicio: examenEditar.hora_inicio.substring(0, 5),
                duracion_minutos: examenEditar.duracion_minutos,
            });
        } else {
            setFormData({
                grupo_id: '1',
                nombre: 'Primer parcial',
                fecha: '',
                hora_inicio: '',
                duracion_minutos: '',
            });
        }
    }, [examenEditar, isOpen]);

    if (!isOpen) return null;

    const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

    const handleSubmit = (e) => {
        e.preventDefault();
        setError(null);
        setCargando(true);
        const request = examenEditar
            ? axios.put(`/api/examenes/${examenEditar.id}`, formData)
            : axios.post('/api/examenes', formData);

        request
            .then(() => {
                alGuardar();
                onClose();
            })
            .catch((errorRespuesta) => {
                setError(errorRespuesta.response?.data?.message || 'Error al guardar');
            })
            .finally(() => setCargando(false));
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
                <h2 className="text-xl font-bold mb-4">
                    {examenEditar ? 'Editar Examen' : 'Nuevo Examen'}
                </h2>
                {error && (
                    <div className="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm">{error}</div>
                )}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium">ID Grupo</label>
                        <input
                            type="number"
                            name="grupo_id"
                            value={formData.grupo_id}
                            onChange={handleChange}
                            required
                            className="w-full border p-2"
                            disabled={!!examenEditar}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium">Tipo</label>
                        <select
                            name="nombre"
                            value={formData.nombre}
                            onChange={handleChange}
                            className="w-full border p-2"
                        >
                            <option value="Primer parcial">Primer parcial</option>
                            <option value="Segundo parcial">Segundo parcial</option>
                            <option value="Examen final">Examen final</option>
                            <option value="Instancia">Instancia</option>
                        </select>
                    </div>
                    <div className="flex gap-4">
                        <div className="flex-1">
                            <label className="block text-sm font-medium">Fecha</label>
                            <input
                                type="date"
                                name="fecha"
                                value={formData.fecha}
                                onChange={handleChange}
                                required
                                className="w-full border p-2"
                            />
                        </div>
                        <div className="flex-1">
                            <label className="block text-sm font-medium">Hora</label>
                            <input
                                type="time"
                                name="hora_inicio"
                                value={formData.hora_inicio}
                                onChange={handleChange}
                                required
                                className="w-full border p-2"
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium">Duración (minutos)</label>
                        <input
                            type="number"
                            name="duracion_minutos"
                            value={formData.duracion_minutos}
                            onChange={handleChange}
                            required
                            min="15"
                            max="480"
                            className="w-full border p-2"
                        />
                    </div>
                    <div className="flex justify-end gap-3 mt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-gray-600 border rounded"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={cargando}
                            className="px-4 py-2 bg-blue-600 text-white rounded"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

// Validación requerida por ESLint para los props
FormularioExamenModal.propTypes = {
    isOpen: PropTypes.bool.isRequired,
    onClose: PropTypes.func.isRequired,
    examenEditar: PropTypes.object,
    alGuardar: PropTypes.func.isRequired,
};

// --- 2. PANTALLA PRINCIPAL ---
export default function ExamenesNormasApi() {
    const [examenes, setExamenes] = useState([]);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [examenEditar, setExamenEditar] = useState(null);
    const [examenSeleccionadoNormas, setExamenSeleccionadoNormas] = useState(null);

    const cargarExamenes = () => {
        axios.get('/api/examenes').then((res) => setExamenes(res.data.data || []));
    };

    useEffect(() => {
        cargarExamenes();
    }, []);

    const handleEliminar = (id) => {
        if (confirm('¿Eliminar examen?')) {
            // Se quitó la variable "err" sin usar para satisfacer a ESLint
            axios
                .delete(`/api/examenes/${id}`)
                .then(() => cargarExamenes())
                .catch(() => alert('Error'));
        }
    };

    return (
        <div className="p-6 w-full">
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-2xl font-bold">Exámenes</h1>
                    <p className="text-gray-500">
                        Evaluaciones registradas por grupo de asignatura
                    </p>
                </div>
                <button
                    onClick={() => {
                        setExamenEditar(null);
                        setIsModalOpen(true);
                    }}
                    className="bg-blue-600 text-white px-4 py-2 rounded-md font-medium hover:bg-blue-700"
                >
                    + Nuevo examen
                </button>
            </div>

            <div className="bg-white rounded-lg shadow overflow-x-auto mb-6">
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="border-b text-gray-500 text-sm">
                            <th className="p-4">ASIGNATURA</th>
                            <th className="p-4">TIPO</th>
                            <th className="p-4">FECHA</th>
                            <th className="p-4">HORA</th>
                            <th className="p-4">DOCENTE</th>
                            <th className="p-4">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        {examenes.map((ex) => (
                            <tr key={ex.id} className="border-b hover:bg-gray-50">
                                <td className="p-4 font-semibold">{ex.grupo.asignatura.nombre}</td>
                                <td className="p-4">{ex.nombre}</td>
                                <td className="p-4">{ex.fecha}</td>
                                <td className="p-4">
                                    {ex.hora_inicio} ({ex.duracion_minutos} min)
                                </td>
                                <td className="p-4 text-sm">{ex.grupo.docente.apellidos}</td>
                                <td className="p-4 flex gap-3 items-center">
                                    <button
                                        onClick={() => {
                                            setExamenEditar(ex);
                                            setIsModalOpen(true);
                                        }}
                                        className="text-blue-600 font-medium"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        onClick={() => handleEliminar(ex.id)}
                                        className="text-red-600 font-medium"
                                    >
                                        Eliminar
                                    </button>
                                    <span className="text-gray-300">|</span>
                                    <button
                                        onClick={() => setExamenSeleccionadoNormas(ex)}
                                        className="text-blue-600 font-medium"
                                    >
                                        Normas
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <FormularioExamenModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                examenEditar={examenEditar}
                alGuardar={cargarExamenes}
            />

            {examenSeleccionadoNormas && (
                <div className="bg-white rounded-lg shadow p-6 border border-gray-200 mt-6">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-lg font-bold">Normas del examen</h2>
                        <button
                            onClick={() => setExamenSeleccionadoNormas(null)}
                            className="text-red-600"
                        >
                            Cerrar
                        </button>
                    </div>
                    <div className="p-4 bg-gray-50 border rounded text-sm text-gray-600">
                        Panel de normas activo.
                    </div>
                </div>
            )}
        </div>
    );
}

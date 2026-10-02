import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function FormularioExamenModal({ isOpen, onClose, examenEditar, alGuardar }) {
    const [formData, setFormData] = useState({
        grupo_id: '',
        nombre: 'Primer parcial',
        fecha: '',
        hora_inicio: '',
        duracion_minutos: ''
    });
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        if (examenEditar) {
            setFormData({
                grupo_id: examenEditar.grupo.id,
                nombre: examenEditar.nombre,
                fecha: examenEditar.fecha,
                hora_inicio: examenEditar.hora_inicio.substring(0, 5), // formato HH:mm
                duracion_minutos: examenEditar.duracion_minutos
            });
        } else {
            setFormData({
                grupo_id: '1', // Por defecto, idealmente aquí iría un select con los grupos cargados
                nombre: 'Primer parcial',
                fecha: '',
                hora_inicio: '',
                duracion_minutos: ''
            });
        }
    }, [examenEditar, isOpen]);

    if (!isOpen) return null;

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setError(null);
        setCargando(true);

        const request = examenEditar 
            ? axios.put(`/api/examenes/${examenEditar.id}`, formData)
            : axios.post('/api/examenes', formData);

        request
            .then(response => {
                alGuardar();
                onClose();
            })
            .catch(err => {
                setError(err.response?.data?.message || 'Error al guardar el examen');
            })
            .finally(() => setCargando(false));
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
                <h2 className="text-xl font-bold mb-4">{examenEditar ? 'Editar Examen' : 'Nuevo Examen'}</h2>
                
                {error && <div className="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm">{error}</div>}
                
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">ID del Grupo (Asignatura)</label>
                        <input type="number" name="grupo_id" value={formData.grupo_id} onChange={handleChange} required className="mt-1 w-full border rounded p-2" disabled={!!examenEditar} />
                    </div>
                    
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Tipo de Examen</label>
                        <select name="nombre" value={formData.nombre} onChange={handleChange} className="mt-1 w-full border rounded p-2">
                            <option value="Primer parcial">Primer parcial</option>
                            <option value="Segundo parcial">Segundo parcial</option>
                            <option value="Examen final">Examen final</option>
                            <option value="Instancia">Instancia</option>
                        </select>
                    </div>

                    <div className="flex gap-4">
                        <div className="flex-1">
                            <label className="block text-sm font-medium text-gray-700">Fecha</label>
                            <input type="date" name="fecha" value={formData.fecha} onChange={handleChange} required className="mt-1 w-full border rounded p-2" />
                        </div>
                        <div className="flex-1">
                            <label className="block text-sm font-medium text-gray-700">Hora</label>
                            <input type="time" name="hora_inicio" value={formData.hora_inicio} onChange={handleChange} required className="mt-1 w-full border rounded p-2" />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Duración (minutos)</label>
                        <input type="number" name="duracion_minutos" value={formData.duracion_minutos} onChange={handleChange} required min="15" max="480" className="mt-1 w-full border rounded p-2" />
                    </div>

                    <div className="flex justify-end gap-3 mt-6">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancelar</button>
                        <button type="submit" disabled={cargando} className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            {cargando ? 'Guardando...' : 'Guardar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import axios from 'axios';
import CargaMasiva from './CargaMasiva';

vi.mock('axios', () => ({
    default: {
        post: vi.fn(),
    },
}));

function crearArchivoCsv(contenido, nombre = 'padron.csv') {
    return new File([contenido], nombre, { type: 'text/csv' });
}

function seleccionarArchivo(contenido, nombre) {
    const input = document.querySelector('input[type="file"]');
    fireEvent.change(input, { target: { files: [crearArchivoCsv(contenido, nombre)] } });
}

describe('CargaMasiva', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('muestra el formulario de carga', () => {
        render(<CargaMasiva onNavigate={vi.fn()} />);

        expect(screen.getByText('Selecciona un archivo para cargar')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Cargar archivo' })).toBeInTheDocument();
    });

    it('pide seleccionar un archivo antes de cargar', () => {
        render(<CargaMasiva onNavigate={vi.fn()} />);

        fireEvent.click(screen.getByRole('button', { name: 'Cargar archivo' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Selecciona un archivo antes de cargarlo.'
        );
        expect(axios.post).not.toHaveBeenCalled();
    });

    it('envia el CSV al endpoint y muestra el resultado del backend', async () => {
        axios.post.mockResolvedValue({
            data: {
                creados: 1,
                actualizados: 1,
                rechazados: 2,
                detalles: [
                    {
                        fila: 3,
                        motivo: 'Estudiante actualizado por coincidencia de CI.',
                        tipo: 'actualizado',
                    },
                    {
                        fila: 4,
                        motivo: 'CI o correo repetido en el archivo.',
                        tipo: 'rechazado',
                    },
                    {
                        fila: 5,
                        motivo: 'El campo ci es obligatorio.',
                        tipo: 'rechazado',
                    },
                ],
            },
        });

        render(<CargaMasiva onNavigate={vi.fn()} />);
        seleccionarArchivo(
            [
                'nombre,apellido,ci,correo,activo',
                'Ana,Perez,1234567,ana@example.edu,true',
                'Luis,Gomez,7654321,luis@example.edu,true',
            ].join('\\n')
        );
        fireEvent.click(screen.getByRole('button', { name: 'Cargar archivo' }));

        await waitFor(() => {
            expect(screen.getByText('1 nuevos')).toBeInTheDocument();
        });

        expect(screen.getByText('1 actualizados')).toBeInTheDocument();
        expect(screen.getByText('2 rechazados')).toBeInTheDocument();
        expect(screen.getByText('El campo ci es obligatorio.')).toBeInTheDocument();
        expect(axios.post).toHaveBeenCalledWith('/api/students/import', expect.any(FormData));
        const formData = axios.post.mock.calls[0][1];
        expect(formData.get('archivo').name).toBe('padron.csv');
    });

    it('muestra un error cuando el backend rechaza el archivo', async () => {
        axios.post.mockRejectedValue({
            response: {
                data: {
                    message: 'Las columnas del archivo no son válidas.',
                },
            },
        });

        render(<CargaMasiva onNavigate={vi.fn()} />);
        seleccionarArchivo('cabecera incorrecta', 'padron.csv');
        fireEvent.click(screen.getByRole('button', { name: 'Cargar archivo' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Las columnas del archivo no son válidas.'
        );
    });

    it('rechaza un archivo con extension no admitida', () => {
        render(<CargaMasiva onNavigate={vi.fn()} />);
        seleccionarArchivo('x', 'padron.pdf');
        fireEvent.click(screen.getByRole('button', { name: 'Cargar archivo' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'El archivo debe tener formato CSV (.csv).'
        );
        expect(axios.post).not.toHaveBeenCalled();
    });
});

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CargaMasiva from './CargaMasiva';

function crearArchivoCsv(contenido, nombre = 'padron.csv') {
    return new File([contenido], nombre, { type: 'text/csv' });
}

describe('CargaMasiva', () => {
    it('muestra el formulario de carga', () => {
        render(<CargaMasiva onNavigate={vi.fn()} />);

        expect(screen.getByRole('heading', { name: 'Padrón' })).toBeInTheDocument();
        expect(screen.getByText('Selecciona un archivo para cargar')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Cargar archivo' })).toBeInTheDocument();
    });

    it('pide seleccionar un archivo antes de cargar', () => {
        render(<CargaMasiva onNavigate={vi.fn()} />);

        fireEvent.click(screen.getByRole('button', { name: 'Cargar archivo' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Selecciona un archivo antes de cargarlo.'
        );
    });

    it('procesa un CSV válido: acepta, actualiza y rechaza según corresponda', async () => {
        const csv = [
            'codigo_universitario,documento_identidad,nombres,apellidos,carrera',
            '202512345,1234567,Ana,Perez,Informática',
            '201901349,8452110,Luis,Gomez,Sistemas',
            '202512345,7654321,Otro,Duplicado,Informática',
            ',,,,',
        ].join('\n');

        render(<CargaMasiva onNavigate={vi.fn()} />);

        const input = document.querySelector('input[type="file"]');
        fireEvent.change(input, { target: { files: [crearArchivoCsv(csv)] } });
        fireEvent.click(screen.getByRole('button', { name: 'Cargar archivo' }));

        await waitFor(() => {
            expect(screen.getByText('1 nuevos')).toBeInTheDocument();
        });

        expect(screen.getByText('1 actualizados')).toBeInTheDocument();
        expect(screen.getByText('2 rechazados')).toBeInTheDocument();
        expect(
            screen.getByText('Código universitario "202512345" repetido en el archivo.')
        ).toBeInTheDocument();
        expect(
            screen.getByText('Faltan campos obligatorios (se esperan 5 columnas).')
        ).toBeInTheDocument();
    });

    it('rechaza un archivo con extensión no admitida', () => {
        render(<CargaMasiva onNavigate={vi.fn()} />);

        const input = document.querySelector('input[type="file"]');
        fireEvent.change(input, {
            target: { files: [new File(['x'], 'padron.pdf', { type: 'application/pdf' })] },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Cargar archivo' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'El archivo debe ser una hoja de cálculo'
        );
    });
});

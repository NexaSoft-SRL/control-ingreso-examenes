import { useState } from 'react';
import PropTypes from 'prop-types';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import CampoImagen from './CampoImagen';

beforeAll(() => {
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:foto');
    globalThis.URL.revokeObjectURL = vi.fn();
});

function Campo({ revisar, onCambiar = () => {}, ...extra }) {
    const [archivo, setArchivo] = useState(null);
    return (
        <CampoImagen
            etiqueta="Foto"
            archivo={archivo}
            onCambiar={(f) => {
                setArchivo(f);
                onCambiar(f);
            }}
            revisar={revisar}
            {...extra}
        />
    );
}

Campo.propTypes = { revisar: PropTypes.func, onCambiar: PropTypes.func };

const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });
const elegir = () => fireEvent.change(screen.getByLabelText('Foto'), { target: { files: [foto] } });

describe('CampoImagen', () => {
    it('sin revisar acepta la foto', async () => {
        render(<Campo />);
        elegir();
        expect(await screen.findByRole('img', { name: 'Foto' })).toBeInTheDocument();
    });

    it('un error de la revisión la rechaza y se muestra', async () => {
        const onCambiar = vi.fn();
        const revisar = vi.fn(async () => ({
            errores: ['No se detecta un rostro', 'Centre el rostro'],
            avisos: ['Foto oscura'],
        }));
        render(<Campo revisar={revisar} onCambiar={onCambiar} />);
        elegir();

        expect(await screen.findByRole('alert')).toHaveTextContent('No se detecta un rostro');
        expect(revisar).toHaveBeenCalledWith(foto);
        expect(onCambiar).toHaveBeenCalledWith(null);
        expect(screen.queryByRole('img', { name: 'Foto' })).not.toBeInTheDocument();
        expect(screen.queryByText('Foto oscura')).not.toBeInTheDocument();
    });

    it('un aviso no la bloquea y se muestra bajo la vista previa', async () => {
        const onCambiar = vi.fn();
        render(
            <Campo
                revisar={async () => ({ errores: [], avisos: ['Foto oscura', 'Foto borrosa'] })}
                onCambiar={onCambiar}
            />
        );
        elegir();

        expect(await screen.findByRole('img', { name: 'Foto' })).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent('Foto oscura · Foto borrosa');
        expect(onCambiar).toHaveBeenCalledWith(foto);

        fireEvent.click(screen.getByRole('button', { name: 'Quitar foto' }));
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('si la revisión falla (detector caído), la foto pasa', async () => {
        render(<Campo revisar={() => Promise.reject(new Error('sin wasm'))} />);
        elegir();
        expect(await screen.findByRole('img', { name: 'Foto' })).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('mientras revisa dice «Revisando foto»', async () => {
        let terminar;
        const revisar = () =>
            new Promise((resolver) => {
                terminar = resolver;
            });
        render(<Campo revisar={revisar} />);
        elegir();

        expect(await screen.findByText('Revisando foto')).toBeInTheDocument();
        terminar({ errores: [], avisos: [] });
        expect(await screen.findByRole('img', { name: 'Foto' })).toBeInTheDocument();
    });

    it('prepara la foto antes de revisarla y la muestra en cuadrado y en círculo', async () => {
        const cuadrada = new File(['cuadrada'], 'yo.jpg', { type: 'image/jpeg' });
        const preparar = vi.fn(async () => cuadrada);
        const revisar = vi.fn(async () => ({ errores: [], avisos: [] }));
        const onCambiar = vi.fn();
        render(<Campo preparar={preparar} revisar={revisar} onCambiar={onCambiar} cuadrada />);
        elegir();

        expect(await screen.findByRole('img', { name: 'Foto' })).toHaveClass('w-44');
        expect(preparar).toHaveBeenCalledWith(foto);
        expect(revisar).toHaveBeenCalledWith(cuadrada);
        expect(onCambiar).toHaveBeenLastCalledWith(cuadrada);
    });

    it('sin cámara en el navegador solo ofrece elegir la foto', () => {
        render(<Campo tomarFoto />);
        expect(screen.queryByRole('button', { name: 'Tomar foto' })).not.toBeInTheDocument();
        expect(screen.getByText('Elegir foto')).toBeInTheDocument();
    });

    describe('con cámara', () => {
        afterEach(() => {
            delete navigator.mediaDevices;
        });

        it('ofrece tomarla o elegir un archivo, y abre la cámara', async () => {
            const parar = vi.fn();
            Object.defineProperty(navigator, 'mediaDevices', {
                configurable: true,
                value: {
                    getUserMedia: vi.fn(async () => ({ getTracks: () => [{ stop: parar }] })),
                },
            });
            // La frontal, para la propia foto; la de atrás es la de las pruebas.
            const { unmount } = render(<Campo tomarFoto camara="user" />);
            expect(screen.getByText('Elegir archivo')).toBeInTheDocument();

            fireEvent.click(screen.getByRole('button', { name: 'Tomar foto' }));
            expect(screen.getByRole('dialog')).toHaveTextContent('Tomar foto');
            expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledWith(
                expect.objectContaining({ video: expect.objectContaining({ facingMode: 'user' }) })
            );
            expect(screen.getByRole('button', { name: /^Tomar$/ })).toBeDisabled();

            // Al cerrar se apaga la cámara.
            await vi.waitFor(() => expect(navigator.mediaDevices.getUserMedia).toHaveReturned());
            fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));
            await vi.waitFor(() => expect(parar).toHaveBeenCalled());
            unmount();
        });

        it('sin permiso lo dice', async () => {
            Object.defineProperty(navigator, 'mediaDevices', {
                configurable: true,
                value: {
                    getUserMedia: vi.fn(async () => {
                        throw Object.assign(new Error('no'), { name: 'NotAllowedError' });
                    }),
                },
            });
            render(<Campo tomarFoto />);
            fireEvent.click(screen.getByRole('button', { name: 'Tomar foto' }));
            expect(await screen.findByRole('alert')).toHaveTextContent(
                'Permiso de cámara denegado'
            );
        });
    });
});

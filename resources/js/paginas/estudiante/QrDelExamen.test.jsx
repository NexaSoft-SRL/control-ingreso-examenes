import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import QrDelExamen from './QrDelExamen';
import { api } from '../../api/cliente';
import { errorHttp } from '../../test/apoyo';

vi.mock('../../api/cliente');

const PNG = new Blob(['png'], { type: 'image/png' });

const montar = () => render(<QrDelExamen examenId={3} nombre="qr-202100417-2026-10-22.png" />);

beforeEach(() => {
    URL.createObjectURL = vi.fn(() => 'blob:qr');
    URL.revokeObjectURL = vi.fn();
    api.get.mockReset();
});

afterEach(() => {
    delete navigator.share;
    delete navigator.canShare;
    vi.restoreAllMocks();
});

describe('QrDelExamen', () => {
    it('muestra el QR y lo descarga con el nombre del servidor', async () => {
        const clic = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        api.get.mockResolvedValue({
            data: PNG,
            headers: {
                'content-disposition': 'attachment; filename="qr-202100417-2026-10-22.png"',
            },
        });
        montar();

        expect(await screen.findByRole('img', { name: 'Código QR' })).toHaveAttribute(
            'src',
            'blob:qr'
        );
        expect(api.get).toHaveBeenCalledWith('/estudiante/examenes/3/qr', {
            responseType: 'blob',
            silencioso: true,
        });
        expect(screen.queryByRole('button', { name: 'Compartir' })).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Descargar QR' }));
        expect(clic).toHaveBeenCalledTimes(1);
        expect(clic.mock.contexts[0].download).toBe('qr-202100417-2026-10-22.png');
    });

    it('en el teléfono lo comparte con la Web Share API', async () => {
        navigator.canShare = vi.fn(() => true);
        navigator.share = vi.fn(() => Promise.resolve());
        api.get.mockResolvedValue({ data: PNG, headers: {} });
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Compartir' }));

        await waitFor(() => expect(navigator.share).toHaveBeenCalledTimes(1));
        const [archivo] = navigator.share.mock.calls[0][0].files;
        expect(archivo.name).toBe('qr-202100417-2026-10-22.png');
        expect(archivo.type).toBe('image/png');
    });

    it('si compartir falla lo descarga', async () => {
        const clic = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        navigator.canShare = vi.fn(() => true);
        navigator.share = vi.fn(() => Promise.reject(new Error('NotAllowed')));
        api.get.mockResolvedValue({ data: PNG, headers: {} });
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Compartir' }));

        await waitFor(() => expect(clic).toHaveBeenCalledTimes(1));
    });

    it('sin QR lo dice', async () => {
        api.get.mockRejectedValue(errorHttp(404, { message: 'Sin código QR.' }));
        montar();

        expect(await screen.findByText('QR no disponible')).toBeInTheDocument();
    });
});

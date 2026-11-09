import { fireEvent, screen } from '@testing-library/react';
import { Route, Routes } from 'react-router-dom';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import PortalEstudiante from './PortalEstudiante';
import { api } from '../../api/cliente';
import { renderConSesion, simularApi } from '../../test/apoyo';
import { precargarDetector, revisarFoto } from '../../utiles/revisarFoto';

vi.mock('../../api/cliente');
vi.mock('../../utiles/revisarFoto', () => ({
    precargarDetector: vi.fn(),
    revisarFoto: vi.fn(),
}));

beforeAll(() => {
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:foto');
    globalThis.URL.revokeObjectURL = vi.fn();
});

// «Mi foto» del portal del estudiante con la revisión simulada.
function montar() {
    simularApi(api, {
        'GET /estudiante/sesion': {
            estudiante: { id: 7, codigo: '202100417', nombre: 'Diego Mamani', con_foto: false },
            plazo_foto: { desde: '2026-10-01', hasta: '2026-10-31', abierto: true },
        },
        'GET /estudiante/examenes': { data: [], hoy: '2026-10-22' },
        'POST /estudiante/foto': { message: 'Foto registrada.' },
    });
    renderConSesion(
        <Routes>
            <Route path="/estudiante/foto" element={<PortalEstudiante />} />
        </Routes>,
        { usuario: null, ruta: '/estudiante/foto' }
    );
}

const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });

describe('Mi foto', () => {
    it('precarga el detector y rechaza la foto con un error', async () => {
        revisarFoto.mockResolvedValue({ errores: ['Debe aparecer solo usted'], avisos: [] });
        montar();
        await screen.findByRole('button', { name: 'Guardar foto' });
        expect(precargarDetector).toHaveBeenCalled();

        fireEvent.change(screen.getByLabelText('Foto'), { target: { files: [foto] } });
        expect(await screen.findByText('Debe aparecer solo usted')).toBeInTheDocument();
        expect(revisarFoto).toHaveBeenCalledWith(foto);

        fireEvent.click(screen.getByRole('button', { name: 'Guardar foto' }));
        expect(await screen.findByText('Obligatoria')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('con un aviso deja guardarla', async () => {
        revisarFoto.mockResolvedValue({ errores: [], avisos: ['Use un fondo claro'] });
        montar();
        await screen.findByRole('button', { name: 'Guardar foto' });

        fireEvent.change(screen.getByLabelText('Foto'), { target: { files: [foto] } });
        expect(await screen.findByText('Use un fondo claro')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Guardar foto' }));
        fireEvent.click(await screen.findByRole('button', { name: 'Confirmar' }));

        expect(await screen.findByText('Foto registrada')).toBeInTheDocument();
        expect(api.post.mock.calls[0][1].get('foto')).toBe(foto);
    });
});

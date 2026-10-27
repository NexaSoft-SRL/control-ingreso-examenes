import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import FichaEstudiante from './FichaEstudiante';
import PlazoFotos from './PlazoFotos';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

beforeAll(() => {
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:foto');
    globalThis.URL.revokeObjectURL = vi.fn();
});

const FICHA = {
    id: 5,
    codigo: '202104821',
    nombre: 'Aguilar Cossío Mariana',
    documento: '7928194',
    correo: '202104821@est.umss.edu',
    facultad: 'FCyT',
    carrera: 'Ing. Informática',
    origen: 'Administración',
    con_foto: false,
    materias: [],
};

describe('FichaEstudiante', () => {
    it('sin foto la sube apenas se elige, sin pasos de más', async () => {
        simularApi(api, {
            'GET /estudiantes/5': { data: FICHA },
            'POST /estudiantes/5/foto': { message: 'Foto guardada.' },
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        expect(await screen.findByText('Sin foto')).toBeInTheDocument();
        const foto = new File(['jpeg'], 'm.jpg', { type: 'image/jpeg' });
        fireEvent.change(screen.getByLabelText('Poner foto'), { target: { files: [foto] } });

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/estudiantes/5/foto', expect.any(FormData))
        );
        expect(await screen.findByText('Foto guardada')).toBeInTheDocument();
        expect(screen.queryByRole('dialog', { name: 'Poner foto' })).not.toBeInTheDocument();
    });

    it('con foto la muestra y la cámara la cambia', async () => {
        simularApi(api, {
            'GET /estudiantes/5': { data: { ...FICHA, con_foto: true } },
            'GET /estudiantes/5/foto': new Blob(['jpeg']),
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        expect(await screen.findByRole('img', { name: 'Foto del estudiante' })).toHaveAttribute(
            'src',
            'blob:foto'
        );
        expect(screen.getByLabelText('Cambiar foto')).toHaveAttribute('type', 'file');
    });

    it('rechaza un archivo que no es imagen y no lo envía', async () => {
        simularApi(api, { 'GET /estudiantes/5': { data: FICHA } });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        await screen.findByText('Sin foto');
        const pdf = new File(['%PDF'], 'doc.pdf', { type: 'application/pdf' });
        fireEvent.change(screen.getByLabelText('Poner foto'), { target: { files: [pdf] } });

        expect(await screen.findByText('Solo JPG, PNG o WebP')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });
});

describe('PlazoFotos', () => {
    it('guarda las dos fechas y muestra el error del servidor bajo su campo', async () => {
        simularApi(api, {
            'PUT /estudiantes/plazo-fotos': errorHttp(422, {
                errors: { hasta: ['No puede ser antes del inicio'] },
            }),
        });
        const avisar = vi.fn();
        renderConSesion(<PlazoFotos onCerrar={vi.fn()} onGuardado={vi.fn()} avisar={avisar} />);

        fireEvent.change(screen.getByLabelText(/Desde/), { target: { value: '2026-10-20' } });
        fireEvent.change(screen.getByLabelText(/Hasta/), { target: { value: '2026-10-10' } });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        expect(await screen.findByText('No puede ser antes del inicio')).toBeInTheDocument();
        expect(api.put).toHaveBeenCalledWith('/estudiantes/plazo-fotos', {
            desde: '2026-10-20',
            hasta: '2026-10-10',
        });
    });

    it('sin fechas no envía', () => {
        simularApi(api, {});
        renderConSesion(<PlazoFotos onCerrar={vi.fn()} onGuardado={vi.fn()} avisar={vi.fn()} />);
        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        expect(screen.getAllByText('Obligatorio')).toHaveLength(2);
        expect(api.put).not.toHaveBeenCalled();
    });
});

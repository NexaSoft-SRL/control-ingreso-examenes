import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import FichaEstudiante from './FichaEstudiante';
import PlazoFotos from './PlazoFotos';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';
import { revisarFoto } from '../../utiles/revisarFoto';

vi.mock('../../api/cliente');
vi.mock('../../utiles/revisarFoto', () => ({ revisarFoto: vi.fn() }));

beforeEach(() => {
    revisarFoto.mockResolvedValue({ errores: [], avisos: [] });
});

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
    verificado: true,
    registrado_en: '2026-10-01 10:00',
    actualizado_en: '2026-10-02 11:00',
    con_foto: false,
    conflictos: [],
    materias: [],
};

describe('FichaEstudiante', () => {
    it('muestra la procedencia y el historial de conflictos del padrón', async () => {
        simularApi(api, {
            'GET /estudiantes/5': {
                data: {
                    ...FICHA,
                    conflictos: [
                        {
                            id: 8,
                            tipo: 'DOCUMENTO_DISTINTO',
                            estado: 'resuelto',
                            resolucion: 'MANTENER_PADRON',
                            nombre_nuevo: 'Aguilar Cossío Mariana',
                            documento_nuevo: '7928149',
                            reportado_por: 'Blanco Coca Leticia',
                            reportado_en: '2026-10-10 12:00',
                            resuelto_por: 'Rojas Vargas Ana',
                            resuelto_en: '2026-10-11 13:30',
                        },
                    ],
                },
            },
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        expect(await screen.findByText(/Administración · verificado/)).toBeInTheDocument();
        expect(screen.getByText('Historial de conflictos')).toBeInTheDocument();
        expect(screen.getByText('Documento distinto')).toBeInTheDocument();
        expect(
            screen.getByText(/Propuesto: Aguilar Cossío Mariana · C\.I\. 7928149/)
        ).toBeInTheDocument();
        expect(screen.getByText(/Reportado por Blanco Coca Leticia/)).toBeInTheDocument();
        expect(screen.getByText(/Se dejó el actual · Rojas Vargas Ana/)).toBeInTheDocument();
    });

    it('rotula cada dato y junta el origen en una sola insignia', async () => {
        simularApi(api, { 'GET /estudiantes/5': { data: FICHA } });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        const datos = (await screen.findByText('Carrera')).closest('dl');
        for (const etiqueta of ['Carrera', 'Facultad', 'C.I.', 'Correo']) {
            expect(within(datos).getByText(etiqueta)).toBeVisible();
        }
        expect(within(datos).getByText('Ing. Informática')).toBeInTheDocument();
        expect(within(datos).getByText('FCyT')).toBeInTheDocument();
        expect(within(datos).getByText('7928194')).toBeInTheDocument();
        expect(within(datos).getByText('202104821@est.umss.edu')).toBeInTheDocument();
        // Un solo origen, sin bloque aparte ni repetición.
        expect(screen.getAllByText(/Administración/)).toHaveLength(1);
        expect(screen.getByText('Administración · verificado')).toBeInTheDocument();
        expect(screen.queryByText('Origen del dato')).not.toBeInTheDocument();
        expect(
            screen.getByText(/Registrado 1 oct 2026 · actualizado 2 oct 2026/)
        ).toBeInTheDocument();
    });

    it('un dato que vino del estudiante sin revisar dice «sin verificar»', async () => {
        simularApi(api, {
            'GET /estudiantes/5': { data: { ...FICHA, origen: 'Estudiante', verificado: false } },
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        expect(await screen.findByText('Estudiante · sin verificar')).toBeInTheDocument();
    });

    it('sin carnet lo dice en advertencia en lugar de un guion', async () => {
        simularApi(api, { 'GET /estudiantes/5': { data: { ...FICHA, documento: null } } });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        const datos = (await screen.findByText('C.I.')).closest('dl');
        expect(within(datos).getByText('Sin carnet')).toBeInTheDocument();
    });

    it('sin conflictos no muestra el bloque del historial; con conflictos, sí', async () => {
        simularApi(api, { 'GET /estudiantes/5': { data: FICHA } });
        const { unmount } = renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        await screen.findByText('Carrera');
        expect(screen.queryByText('Historial de conflictos')).not.toBeInTheDocument();
        expect(screen.queryByText(/Sin conflictos registrados/)).not.toBeInTheDocument();
        unmount();

        simularApi(api, {
            'GET /estudiantes/5': {
                data: {
                    ...FICHA,
                    conflictos: [
                        {
                            id: 9,
                            tipo: 'NOMBRE_DISTINTO',
                            estado: 'pendiente',
                            nombre_nuevo: 'Aguilar Mariana',
                            reportado_por: 'Blanco Coca Leticia',
                            reportado_en: '2026-10-10 12:00',
                        },
                    ],
                },
            },
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        expect(await screen.findByText('Historial de conflictos')).toBeInTheDocument();
        expect(screen.getByText('Nombre distinto')).toBeInTheDocument();
        expect(screen.getByText('Pendiente')).toBeInTheDocument();
    });

    it('cada materia dice su código, grupo, período y quién es el docente', async () => {
        simularApi(api, {
            'GET /estudiantes/5': {
                data: {
                    ...FICHA,
                    materias: [
                        {
                            grupo_id: 3,
                            asignatura: {
                                nombre: 'Introduccion a la Programacion',
                                codigo: '2010010',
                            },
                            grupo: '6',
                            periodo: '2/2026',
                            docente: 'Salazar Serrudo Carla',
                            via: 'Administración',
                        },
                        {
                            grupo_id: 4,
                            asignatura: { nombre: 'Algebra I', codigo: '2008053' },
                            grupo: '2',
                            periodo: '2/2026',
                            docente: null,
                        },
                    ],
                },
            },
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        const materias = within(await screen.findByRole('region', { name: 'Materias' }));
        const primera = materias.getByText('Introduccion a la Programacion').closest('li');
        expect(primera).toHaveTextContent('2010010 · Grupo 6 · 2/2026');
        expect(primera).toHaveTextContent('Docente: Salazar Serrudo Carla · vía Administración');
        expect(materias.getByText('Algebra I').closest('li')).toHaveTextContent(
            'Docente: Por designar'
        );
    });

    it('con foto dice desde cuándo la tiene', async () => {
        simularApi(api, {
            'GET /estudiantes/5': {
                data: { ...FICHA, con_foto: true, foto_subida_en: '2026-11-03 10:15:00' },
            },
            'GET /estudiantes/5/foto': new Blob(['jpeg']),
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        expect(await screen.findByText('Con foto')).toBeInTheDocument();
        expect(screen.getByText('del 3 nov 2026')).toBeInTheDocument();
    });

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
        expect((await screen.findAllByText('Foto guardada')).length).toBeGreaterThan(0);
        expect(screen.queryByRole('dialog', { name: 'Poner foto' })).not.toBeInTheDocument();
    });

    it('la administración edita los datos desde la ficha', async () => {
        let ficha = { ...FICHA, nombres: 'Mariana', apellidos: 'Aguilar Cossío' };
        simularApi(api, {
            'GET /estudiantes/5': () => ({ data: ficha }),
            'PUT /estudiantes/5': ({ data }) => {
                ficha = { ...ficha, documento: data.documento };
                return { message: 'Estudiante actualizado.' };
            },
        });
        const onFotoCambiada = vi.fn();
        renderConSesion(
            <FichaEstudiante
                estudiante={{ id: 5, nombre: FICHA.nombre }}
                onCerrar={vi.fn()}
                onFotoCambiada={onFotoCambiada}
            />
        );

        fireEvent.click(await screen.findByRole('button', { name: /Editar datos/ }));
        const edicion = screen.getByRole('dialog', { name: 'Editar estudiante' });
        expect(within(edicion).getByLabelText('Nombres', { exact: false })).toHaveValue('Mariana');
        fireEvent.change(within(edicion).getByLabelText('C.I.', { exact: false }), {
            target: { value: '7928194' },
        });
        fireEvent.click(within(edicion).getByRole('button', { name: /Guardar/ }));

        expect(await screen.findByText('Estudiante actualizado')).toBeInTheDocument();
        expect(await screen.findByText('7928194')).toBeInTheDocument();
        expect(onFotoCambiada).toHaveBeenCalled();
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
        // El botón se lee: no es solo un ícono sobre la foto.
        expect(screen.getByText('Cambiar foto')).toBeVisible();
    });

    it('rechaza un archivo que no es imagen y no lo envía', async () => {
        simularApi(api, { 'GET /estudiantes/5': { data: FICHA } });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );

        await screen.findByText('Sin foto');
        const pdf = new File(['%PDF'], 'doc.pdf', { type: 'application/pdf' });
        fireEvent.change(screen.getByLabelText('Poner foto'), { target: { files: [pdf] } });

        expect(await screen.findByRole('alert')).toHaveTextContent('Solo JPG, PNG o WebP');
        expect(api.post).not.toHaveBeenCalled();
    });

    it('la revisión rechaza una foto sin rostro y avisa de un fondo oscuro', async () => {
        simularApi(api, {
            'GET /estudiantes/5': { data: FICHA },
            'POST /estudiantes/5/foto': { message: 'Foto guardada.' },
        });
        renderConSesion(
            <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
        );
        await screen.findByText('Sin foto');
        const foto = new File(['jpeg'], 'm.jpg', { type: 'image/jpeg' });

        revisarFoto.mockResolvedValueOnce({ errores: ['No se detecta un rostro'], avisos: [] });
        fireEvent.change(screen.getByLabelText('Poner foto'), { target: { files: [foto] } });
        expect(await screen.findByRole('alert')).toHaveTextContent('No se detecta un rostro');
        expect(api.post).not.toHaveBeenCalled();

        revisarFoto.mockResolvedValueOnce({ errores: [], avisos: ['Use un fondo claro'] });
        fireEvent.change(screen.getByLabelText('Poner foto'), { target: { files: [foto] } });
        // El aviso del rechazo anterior sigue unos segundos con el mismo rol
        // `status`: se espera al texto nuevo, no al primer aviso que aparezca.
        expect(
            (await screen.findAllByText('Foto guardada · Use un fondo claro')).length
        ).toBeGreaterThan(0);
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/estudiantes/5/foto', expect.any(FormData));
    });

    it('deja a la vista el motivo del rechazo hasta el siguiente intento', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        try {
            simularApi(api, { 'GET /estudiantes/5': { data: FICHA } });
            renderConSesion(
                <FichaEstudiante estudiante={{ id: 5, nombre: FICHA.nombre }} onCerrar={vi.fn()} />
            );
            await screen.findByText('Sin foto');
            revisarFoto.mockResolvedValueOnce({ errores: ['No se detecta un rostro'], avisos: [] });
            const foto = new File(['jpeg'], 'm.jpg', { type: 'image/jpeg' });
            fireEvent.change(screen.getByLabelText('Poner foto'), { target: { files: [foto] } });
            expect(await screen.findByRole('alert')).toHaveTextContent('No se detecta un rostro');

            // El aviso de 3 s se va; el mensaje bajo la foto no.
            await vi.advanceTimersByTimeAsync(5000);
            expect(screen.getByRole('alert')).toHaveTextContent('No se detecta un rostro');
        } finally {
            vi.useRealTimers();
        }
    });
});

describe('PlazoFotos', () => {
    it('guarda las dos fechas y muestra el error del servidor bajo su campo', async () => {
        simularApi(api, {
            'PUT /estudiantes/plazo-fotos': errorHttp(422, {
                errors: { hasta: ['Máximo 180 días'] },
            }),
        });
        const avisar = vi.fn();
        renderConSesion(<PlazoFotos onCerrar={vi.fn()} onGuardado={vi.fn()} avisar={avisar} />);

        fireEvent.change(screen.getByLabelText(/Desde/), { target: { value: '2099-01-10' } });
        fireEvent.change(screen.getByLabelText(/Hasta/), { target: { value: '2099-12-10' } });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar plazo' }));

        expect(await screen.findByText('Máximo 180 días')).toBeInTheDocument();
        expect(api.put).toHaveBeenCalledWith('/estudiantes/plazo-fotos', {
            desde: '2099-01-10',
            hasta: '2099-12-10',
        });
    });

    it('dice cuál regla falla en «Hasta» sin llamar al servidor', () => {
        simularApi(api, {});
        renderConSesion(<PlazoFotos onCerrar={vi.fn()} onGuardado={vi.fn()} avisar={vi.fn()} />);

        fireEvent.change(screen.getByLabelText(/^Desde/), { target: { value: '2099-10-20' } });
        fireEvent.change(screen.getByLabelText(/^Hasta/), { target: { value: '2099-10-10' } });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar plazo' }));
        expect(screen.getByText('Debe ser después de Desde')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/^Desde/), { target: { value: '2000-01-01' } });
        fireEvent.change(screen.getByLabelText(/^Hasta/), { target: { value: '2000-01-10' } });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar plazo' }));
        expect(screen.getByText('No puede ser antes de hoy')).toBeInTheDocument();
        expect(api.put).not.toHaveBeenCalled();
    });

    it('Enter en una fecha guarda el plazo', async () => {
        simularApi(api, { 'PUT /estudiantes/plazo-fotos': { message: 'ok' } });
        renderConSesion(<PlazoFotos onCerrar={vi.fn()} onGuardado={vi.fn()} avisar={vi.fn()} />);

        fireEvent.change(screen.getByLabelText(/Desde/), { target: { value: '2099-10-10' } });
        fireEvent.change(screen.getByLabelText(/Hasta/), { target: { value: '2099-10-20' } });
        fireEvent.submit(screen.getByRole('dialog').querySelector('form'));

        await waitFor(() => expect(api.put).toHaveBeenCalled());
    });

    it('sin fechas no envía', () => {
        simularApi(api, {});
        renderConSesion(<PlazoFotos onCerrar={vi.fn()} onGuardado={vi.fn()} avisar={vi.fn()} />);
        fireEvent.click(screen.getByRole('button', { name: 'Guardar plazo' }));

        expect(screen.getAllByText('Obligatorio')).toHaveLength(2);
        expect(api.put).not.toHaveBeenCalled();
    });
});

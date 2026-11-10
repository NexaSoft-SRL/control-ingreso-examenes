import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import {
    BotonIncidencia,
    BotonIncidenciasComunes,
    FiltroGravedad,
    ListaIncidencias,
    TIPOS_INCIDENCIA,
} from './Incidencias';
import { api } from '../api/cliente';
import { errorHttp, simularApi } from '../test/apoyo';

vi.mock('../api/cliente');

const FOTO = new File(['jpeg'], 'hoja.jpg', { type: 'image/jpeg' });

beforeAll(() => {
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:foto');
    globalThis.URL.revokeObjectURL = vi.fn();
});

const AULAS = [
    { id: 30, nombre: '691A' },
    { id: 31, nombre: '624' },
];

const abrir = () => {
    fireEvent.click(screen.getByRole('button', { name: 'Incidencia' }));
    return within(screen.getByRole('dialog', { name: 'Incidencia' }));
};

const elegirTipo = (dialogo, tipo = 'CAMBIO_AULA') =>
    fireEvent.change(dialogo.getByRole('combobox', { name: /Tipo/ }), {
        target: { value: tipo },
    });

describe('BotonIncidencia', () => {
    it('con el aula fija la muestra y no ofrece la expulsión', () => {
        render(
            <BotonIncidencia
                aula={AULAS[1]}
                por="Mamani Torrez Diego"
                hora="08:21"
                registrar={vi.fn()}
            />
        );
        const dialogo = abrir();

        expect(dialogo.getByText('Aula 624 · 08:21 · Mamani Torrez Diego')).toBeInTheDocument();
        expect(dialogo.queryByRole('combobox', { name: /Aula/ })).not.toBeInTheDocument();
        const tipos = within(dialogo.getByRole('combobox', { name: /Tipo/ })).getAllByRole(
            'option'
        );
        expect(tipos.map((o) => o.textContent)).toEqual([
            'Elegir',
            'Intento rechazado',
            'Suplantación de identidad',
            'Material no permitido',
            'Dispositivo no permitido',
            'Cambio de aula',
            'Otra no prevista…',
        ]);
        expect(TIPOS_INCIDENCIA.EXPULSION).toBe('Expulsión');
    });

    it('exige el tipo y cinco caracteres de detalle antes de enviar', () => {
        const registrar = vi.fn();
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();
        const boton = dialogo.getByRole('button', { name: 'Registrar' });

        expect(dialogo.getByRole('combobox', { name: /Tipo/ })).toHaveValue('');
        expect(boton).toBeDisabled();
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Detalle largo' },
        });
        expect(boton).toBeDisabled();
        elegirTipo(dialogo);
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'abc' },
        });

        expect(dialogo.getByText('Mínimo 5 caracteres')).toBeInTheDocument();
        expect(boton).toBeDisabled();
        expect(registrar).not.toHaveBeenCalled();
    });

    it('al cerrar vuelve a pedir el tipo', () => {
        render(<BotonIncidencia aula={AULAS[0]} registrar={vi.fn()} />);
        let dialogo = abrir();
        elegirTipo(dialogo);
        fireEvent.click(dialogo.getByRole('button', { name: 'Cancelar' }));

        dialogo = abrir();
        expect(dialogo.getByRole('combobox', { name: /Tipo/ })).toHaveValue('');
    });

    it('envía el aula elegida, el tipo, el estudiante y el detalle', async () => {
        const registrar = vi.fn(() => Promise.resolve());
        const alRegistrar = vi.fn();
        render(<BotonIncidencia aulas={AULAS} registrar={registrar} alRegistrar={alRegistrar} />);
        const dialogo = abrir();

        fireEvent.change(dialogo.getByRole('combobox', { name: /Aula/ }), {
            target: { value: '31' },
        });
        fireEvent.change(dialogo.getByRole('combobox', { name: /Tipo/ }), {
            target: { value: 'MATERIAL_NO_PERMITIDO' },
        });
        fireEvent.change(dialogo.getByLabelText('Estudiante'), {
            target: { value: ' 222011223 ' },
        });
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Calculadora programable' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(registrar).toHaveBeenCalledWith({
            aula_id: 31,
            tipo: 'MATERIAL_NO_PERMITIDO',
            tipo_texto: null,
            gravedad: 'GRAVE',
            guardar_comun: null,
            estudiante: '222011223',
            detalle: 'Calculadora programable',
        });
        expect(screen.getByRole('status')).toHaveTextContent('Incidencia registrada');
        expect(alRegistrar).toHaveBeenCalledTimes(1);
    });

    it('con fotos envía un formulario con la evidencia; hasta tres', async () => {
        const registrar = vi.fn(() => Promise.resolve());
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();
        elegirTipo(dialogo);

        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Hoja con fórmulas' },
        });
        const otra = new File(['jpeg2'], 'otra.jpg', { type: 'image/jpeg' });
        fireEvent.change(dialogo.getByLabelText('Evidencia'), { target: { files: [FOTO] } });
        expect(await dialogo.findByRole('img', { name: 'Foto 1' })).toBeInTheDocument();
        fireEvent.change(dialogo.getByLabelText('Evidencia'), { target: { files: [otra] } });
        expect(await dialogo.findByRole('img', { name: 'Foto 2' })).toBeInTheDocument();
        expect(dialogo.getByText('2/3')).toBeInTheDocument();

        // Se quita una con su equis.
        fireEvent.click(dialogo.getByRole('button', { name: 'Quitar la foto 2' }));
        expect(dialogo.queryByRole('img', { name: 'Foto 2' })).not.toBeInTheDocument();
        fireEvent.change(dialogo.getByLabelText('Evidencia'), { target: { files: [otra] } });
        await dialogo.findByRole('img', { name: 'Foto 2' });
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        await waitFor(() => expect(registrar).toHaveBeenCalledTimes(1));
        const cuerpo = registrar.mock.calls[0][0];
        expect(cuerpo).toBeInstanceOf(FormData);
        expect(cuerpo.get('aula_id')).toBe('30');
        expect(cuerpo.get('detalle')).toBe('Hoja con fórmulas');
        expect(cuerpo.getAll('evidencias[]')).toEqual([FOTO, otra]);
        expect(cuerpo.has('estudiante')).toBe(false);
    });

    it('con video envía un formulario con el video', async () => {
        const registrar = vi.fn(() => Promise.resolve());
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();
        elegirTipo(dialogo);
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Salió del aula sin permiso' },
        });
        // jsdom no lee videos: la duración se da por leída.
        const crear = document.createElement.bind(document);
        vi.spyOn(document, 'createElement').mockImplementation((etiqueta, ...resto) => {
            const elemento = crear(etiqueta, ...resto);
            if (etiqueta === 'video') {
                Object.defineProperty(elemento, 'duration', { value: 20 });
                Object.defineProperty(elemento, 'src', {
                    set() {
                        setTimeout(() => elemento.onloadedmetadata?.(), 0);
                    },
                });
            }
            return elemento;
        });
        const grabado = new File(['webm'], 'pasillo.webm', { type: 'video/webm' });
        fireEvent.change(dialogo.getByLabelText('Archivo de video'), {
            target: { files: [grabado] },
        });
        expect(await dialogo.findByLabelText('Video elegido')).toBeInTheDocument();
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        await waitFor(() => expect(registrar).toHaveBeenCalledTimes(1));
        const cuerpo = registrar.mock.calls[0][0];
        expect(cuerpo).toBeInstanceOf(FormData);
        expect(cuerpo.get('video')).toBe(grabado);
        expect(cuerpo.getAll('evidencias[]')).toEqual([]);
        expect(cuerpo.get('gravedad')).toBe('LEVE');
        vi.restoreAllMocks();
    });

    it('la gravedad se marca con el tipo y se puede cambiar', async () => {
        const registrar = vi.fn(() => Promise.resolve());
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();

        expect(dialogo.queryByRole('radiogroup', { name: 'Gravedad' })).not.toBeInTheDocument();
        elegirTipo(dialogo, 'DISPOSITIVO_NO_PERMITIDO');
        const gravedad = within(dialogo.getByRole('radiogroup', { name: 'Gravedad' }));
        expect(gravedad.getByRole('radio', { name: 'Grave' })).toHaveAttribute(
            'aria-checked',
            'true'
        );
        fireEvent.click(gravedad.getByRole('radio', { name: 'Leve' }));
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Celular apagado en la mochila' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        await waitFor(() => expect(registrar).toHaveBeenCalledTimes(1));
        expect(registrar.mock.calls[0][0]).toMatchObject({
            tipo: 'DISPOSITIVO_NO_PERMITIDO',
            gravedad: 'LEVE',
        });
    });

    it('ofrece la lista del docente con su gravedad', async () => {
        simularApi(api, {
            'GET /examenes/8/incidencias/catalogo': {
                data: [
                    {
                        tipo: 'DISPOSITIVO_NO_PERMITIDO',
                        nombre: 'Dispositivo no permitido',
                        gravedad: 'MUY_GRAVE',
                        propia: false,
                    },
                    {
                        id: 3,
                        tipo: 'OTRA',
                        nombre: 'Uso de corrector',
                        gravedad: 'LEVE',
                        propia: true,
                    },
                ],
                puede_guardar: true,
            },
        });
        const registrar = vi.fn(() => Promise.resolve());
        render(
            <BotonIncidencia
                aula={AULAS[0]}
                registrar={registrar}
                catalogo="/examenes/8/incidencias/catalogo"
            />
        );
        const dialogo = abrir();

        const propias = await dialogo.findByRole('group', { name: 'Mis incidencias' });
        expect(
            within(propias).getByRole('option', { name: 'Uso de corrector' })
        ).toBeInTheDocument();
        elegirTipo(dialogo, 'DISPOSITIVO_NO_PERMITIDO');
        expect(dialogo.getByRole('radio', { name: 'Muy grave' })).toHaveAttribute(
            'aria-checked',
            'true'
        );
        elegirTipo(dialogo, 'p:Uso de corrector');
        expect(dialogo.getByRole('radio', { name: 'Leve' })).toHaveAttribute(
            'aria-checked',
            'true'
        );
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Corrector líquido en la mesa' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        await waitFor(() => expect(registrar).toHaveBeenCalledTimes(1));
        expect(registrar.mock.calls[0][0]).toMatchObject({
            tipo: 'OTRA',
            tipo_texto: 'Uso de corrector',
            gravedad: 'LEVE',
        });
    });

    it('una no prevista pide su nombre y su gravedad, y se puede guardar', async () => {
        simularApi(api, {
            'GET /examenes/8/incidencias/catalogo': { data: [], puede_guardar: true },
        });
        const registrar = vi.fn(() => Promise.resolve());
        render(
            <BotonIncidencia
                aula={AULAS[0]}
                registrar={registrar}
                catalogo="/examenes/8/incidencias/catalogo"
            />
        );
        const dialogo = abrir();
        await waitFor(() => expect(api.get).toHaveBeenCalled());

        elegirTipo(dialogo, '__nueva');
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Le pasaba respuestas al de al lado' },
        });
        const boton = dialogo.getByRole('button', { name: 'Registrar' });
        expect(boton).toBeDisabled();
        fireEvent.change(dialogo.getByRole('textbox', { name: /Qué pasó/ }), {
            target: { value: 'Se comunicó por señas' },
        });
        expect(boton).toBeDisabled();
        fireEvent.click(dialogo.getByRole('radio', { name: 'Grave' }));
        fireEvent.click(
            await dialogo.findByRole('checkbox', { name: 'Guardar en mis incidencias comunes' })
        );
        fireEvent.click(boton);

        await waitFor(() => expect(registrar).toHaveBeenCalledTimes(1));
        expect(registrar.mock.calls[0][0]).toMatchObject({
            tipo: 'OTRA',
            tipo_texto: 'Se comunicó por señas',
            gravedad: 'GRAVE',
            guardar_comun: 1,
        });
    });

    it('muestra el error del servidor y deja el formulario abierto', async () => {
        const registrar = vi.fn(() =>
            Promise.reject(errorHttp(403, { message: 'Este examen no está a su cargo.' }))
        );
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();
        elegirTipo(dialogo);

        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Detalle largo' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        expect(await screen.findByRole('status')).toHaveTextContent(
            'Este examen no está a su cargo.'
        );
        expect(screen.getByRole('dialog', { name: 'Incidencia' })).toBeInTheDocument();
    });

    it('pone el error de validación bajo su campo', async () => {
        const registrar = vi.fn(() =>
            Promise.reject(
                errorHttp(422, { message: '…', errors: { detalle: ['Detalle no válido'] } })
            )
        );
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();
        elegirTipo(dialogo);

        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Ver detalle' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));
        expect(await dialogo.findByText('Detalle no válido')).toBeInTheDocument();
    });

    it('el estudiante va por código o por nombre en letras', () => {
        const registrar = vi.fn();
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();
        elegirTipo(dialogo);
        fireEvent.change(dialogo.getByLabelText('Estudiante'), { target: { value: 'Juan 12' } });
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Detalle largo' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        expect(dialogo.getByText('Un código SIS o solo letras')).toBeInTheDocument();
        expect(registrar).not.toHaveBeenCalled();
    });
});

describe('ListaIncidencias', () => {
    const ITEMS = [
        {
            id: 40,
            tipo: 'MATERIAL_NO_PERMITIDO',
            tipo_texto: 'Material no permitido',
            aula: '617',
            estudiante: 'Rocha Peredo Fabiola · 222011223',
            detalle: 'Calculadora programable',
            hora: '08:19',
            por: 'Peredo Claros Lucía',
            rol: 'Auxiliar',
            atendida: false,
        },
        {
            id: 39,
            tipo: 'CAMBIO_AULA',
            aula: '691A',
            detalle: 'Movilidad reducida',
            hora: '08:05',
            por: 'Salazar Carla',
            rol: 'Docente',
            atendida: true,
        },
    ];

    it('sin incidencias lo dice', () => {
        render(<ListaIncidencias items={[]} />);
        expect(screen.getByText('Sin incidencias')).toBeInTheDocument();
    });

    it('muestra cada una con su estado y, si se pide, el aula', () => {
        render(<ListaIncidencias items={ITEMS} conAula />);

        expect(screen.getByText('Material no permitido')).toBeInTheDocument();
        expect(screen.getByText('Aula 617 · 08:19')).toBeInTheDocument();
        expect(screen.getByText('Peredo Claros Lucía · Auxiliar')).toBeInTheDocument();
        expect(screen.getByText('Sin atender')).toBeInTheDocument();
        // Sin `tipo_texto` usa el mapa de tipos.
        expect(screen.getByText('Cambio de aula')).toBeInTheDocument();
        expect(screen.getByText('Atendida')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Atender' })).not.toBeInTheDocument();
    });

    it('el docente atiende las que faltan', async () => {
        const atender = vi.fn(() => Promise.resolve());
        render(<ListaIncidencias items={ITEMS} atender={atender} />);

        fireEvent.click(screen.getByRole('button', { name: 'Atender' }));
        // Primero pide confirmar: no se deshace.
        expect(atender).not.toHaveBeenCalled();
        const confirmar = screen.getByRole('button', { name: 'Confirmar' });
        fireEvent.click(confirmar);
        expect(atender).toHaveBeenCalledWith(40);
        expect(confirmar).toBeDisabled();
        await waitFor(() => expect(screen.getByRole('button', { name: 'Atender' })).toBeEnabled());
    });

    it('la gravedad se lee en el color y en el texto', () => {
        render(
            <ListaIncidencias
                items={[
                    {
                        ...ITEMS[0],
                        id: 41,
                        tipo: 'SUPLANTACION',
                        tipo_texto: 'Suplantación de identidad',
                        gravedad: 'MUY_GRAVE',
                    },
                    ITEMS[0],
                    ITEMS[1],
                ]}
            />
        );

        expect(screen.getByText('Muy grave')).toHaveClass('text-danger-700');
        expect(screen.getByText('Grave')).toHaveClass('text-warning-700');
        // Sin `gravedad` la toma del tipo; atendida, en gris.
        expect(screen.getByText('Leve')).toHaveClass('text-slate-500');
    });

    it('el filtro por gravedad cuenta cada nivel y elige uno', () => {
        const onCambiar = vi.fn();
        render(
            <FiltroGravedad
                items={[
                    { ...ITEMS[0], id: 41, tipo: 'SUPLANTACION', gravedad: 'MUY_GRAVE' },
                    ITEMS[0],
                    ITEMS[1],
                ]}
                valor={null}
                onCambiar={onCambiar}
            />
        );

        const grupo = within(screen.getByRole('group', { name: 'Gravedad' }));
        expect(grupo.getByRole('button', { name: /Todas/ })).toHaveTextContent('Todas3');
        expect(grupo.getByRole('button', { name: /Todas/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        expect(grupo.getByRole('button', { name: /Muy grave/ })).toHaveTextContent('Muy grave1');
        expect(grupo.getByRole('button', { name: /^Grave/ })).toHaveTextContent('Grave1');
        fireEvent.click(grupo.getByRole('button', { name: /Leve/ }));
        expect(onCambiar).toHaveBeenCalledWith('LEVE');
    });

    it('el docente corrige el tipo y el detalle', async () => {
        const corregir = vi.fn(() => Promise.resolve());
        render(<ListaIncidencias items={ITEMS} corregir={corregir} />);

        fireEvent.click(screen.getAllByRole('button', { name: 'Corregir' })[0]);
        const dialogo = within(screen.getByRole('dialog', { name: 'Corregir incidencia' }));
        fireEvent.change(dialogo.getByRole('combobox', { name: /Tipo/ }), {
            target: { value: 'DISPOSITIVO_NO_PERMITIDO' },
        });
        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Reloj inteligente' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Guardar' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(corregir).toHaveBeenCalledWith(40, {
            tipo: 'DISPOSITIVO_NO_PERMITIDO',
            gravedad: 'GRAVE',
            detalle: 'Reloj inteligente',
        });
    });

    it('el docente cambia la gravedad y nombra una no prevista', async () => {
        const corregir = vi.fn(() => Promise.resolve());
        render(<ListaIncidencias items={ITEMS} corregir={corregir} />);

        fireEvent.click(screen.getAllByRole('button', { name: 'Corregir' })[0]);
        const dialogo = within(screen.getByRole('dialog', { name: 'Corregir incidencia' }));
        fireEvent.change(dialogo.getByRole('combobox', { name: /Tipo/ }), {
            target: { value: 'OTRA' },
        });
        fireEvent.change(dialogo.getByRole('textbox', { name: /Qué pasó/ }), {
            target: { value: 'Hojas de otro examen' },
        });
        fireEvent.click(dialogo.getByRole('radio', { name: 'Muy grave' }));
        fireEvent.click(dialogo.getByRole('button', { name: 'Guardar' }));

        await waitFor(() => expect(corregir).toHaveBeenCalledTimes(1));
        expect(corregir).toHaveBeenCalledWith(40, {
            tipo: 'OTRA',
            tipo_texto: 'Hojas de otro examen',
            gravedad: 'MUY_GRAVE',
            detalle: 'Calculadora programable',
        });
    });

    it('un ingreso repetido conserva su tipo al corregirlo', () => {
        render(
            <ListaIncidencias
                items={[{ ...ITEMS[0], tipo: 'INGRESO_REPETIDO', tipo_texto: 'Ingreso repetido' }]}
                corregir={vi.fn()}
            />
        );

        fireEvent.click(screen.getByRole('button', { name: 'Adjuntar evidencia' }));
        const dialogo = within(screen.getByRole('dialog', { name: 'Evidencia' }));
        expect(dialogo.getByRole('button', { name: 'Guardar' })).toBeDisabled();
        expect(dialogo.queryByRole('combobox', { name: /Tipo/ })).not.toBeInTheDocument();
        expect(dialogo.getByText('Ingreso repetido')).toBeInTheDocument();
    });

    it('abre la foto de la evidencia', async () => {
        simularApi(api, { 'GET /incidencias/40/evidencias/1': new Blob(['jpeg']) });
        render(<ListaIncidencias items={[{ ...ITEMS[0], evidencia: true, evidencias: 1 }]} />);

        fireEvent.click(screen.getByRole('button', { name: 'Evidencia' }));

        const dialogo = within(screen.getByRole('dialog', { name: 'Evidencia' }));
        expect(await dialogo.findByRole('img', { name: 'Evidencia 1' })).toHaveAttribute(
            'src',
            'blob:foto'
        );
        expect(api.get).toHaveBeenCalledWith('/incidencias/40/evidencias/1', {
            responseType: 'blob',
        });
    });

    it('abre el video de la evidencia junto a las fotos', async () => {
        simularApi(api, {
            'GET /incidencias/40/evidencias/1': new Blob(['jpeg']),
            'GET /incidencias/40/video': new Blob(['webm'], { type: 'video/webm' }),
        });
        render(
            <ListaIncidencias
                items={[{ ...ITEMS[0], evidencia: true, evidencias: 1, video: true }]}
            />
        );

        fireEvent.click(screen.getByRole('button', { name: 'Evidencia · video' }));

        const dialogo = within(screen.getByRole('dialog', { name: 'Evidencia' }));
        expect(await dialogo.findByLabelText('Video de evidencia')).toHaveAttribute(
            'src',
            'blob:foto'
        );
        expect(dialogo.getByRole('img', { name: 'Evidencia 1' })).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/incidencias/40/video', { responseType: 'blob' });
    });

    it('con solo video, la evidencia es el video', async () => {
        simularApi(api, { 'GET /incidencias/40/video': new Blob(['mp4']) });
        render(<ListaIncidencias items={[{ ...ITEMS[0], evidencias: 0, video: true }]} />);

        fireEvent.click(screen.getByRole('button', { name: 'Evidencia' }));

        const dialogo = within(screen.getByRole('dialog', { name: 'Evidencia' }));
        expect(await dialogo.findByLabelText('Video de evidencia')).toBeInTheDocument();
        expect(dialogo.queryByText('No se pudo abrir')).not.toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalledWith('/incidencias/40/evidencias/1', expect.anything());
    });

    it('con varias fotos las muestra todas y se pasa de una a otra', async () => {
        simularApi(api, {
            'GET /incidencias/40/evidencias/1': new Blob(['uno']),
            'GET /incidencias/40/evidencias/2': new Blob(['dos']),
            'GET /incidencias/40/evidencias/3': new Blob(['tres']),
        });
        render(<ListaIncidencias items={[{ ...ITEMS[0], evidencia: true, evidencias: 3 }]} />);

        fireEvent.click(screen.getByRole('button', { name: 'Evidencia · 3' }));

        const dialogo = within(screen.getByRole('dialog', { name: 'Evidencia' }));
        await dialogo.findByRole('img', { name: 'Evidencia 1' });
        expect(dialogo.getByText(/3 fotos/)).toBeInTheDocument();
        fireEvent.click(dialogo.getByRole('button', { name: 'Ver la foto 3' }));
        expect(dialogo.getByRole('img', { name: 'Evidencia 3' })).toBeInTheDocument();
        expect(dialogo.getByRole('button', { name: 'Ver la foto 3' })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
    });

    it('al corregir, el estudiante solo viaja si se cambió', async () => {
        const corregir = vi.fn(() => Promise.resolve());
        render(<ListaIncidencias items={ITEMS} corregir={corregir} />);

        fireEvent.click(screen.getAllByRole('button', { name: 'Corregir' })[0]);
        let dialogo = within(screen.getByRole('dialog', { name: 'Corregir incidencia' }));
        fireEvent.click(dialogo.getByRole('button', { name: 'Guardar' }));
        await waitFor(() => expect(corregir).toHaveBeenCalledTimes(1));
        expect(corregir.mock.calls[0][1]).not.toHaveProperty('estudiante');

        fireEvent.click(screen.getAllByRole('button', { name: 'Corregir' })[0]);
        dialogo = within(screen.getByRole('dialog', { name: 'Corregir incidencia' }));
        fireEvent.change(dialogo.getByLabelText('Estudiante'), { target: { value: '202011223' } });
        fireEvent.click(dialogo.getByRole('button', { name: 'Guardar' }));
        await waitFor(() => expect(corregir).toHaveBeenCalledTimes(2));
        expect(corregir.mock.calls[1][1]).toMatchObject({ estudiante: '202011223' });
    });

    it('el texto de un ingreso repetido no se reescribe', () => {
        render(
            <ListaIncidencias
                items={[{ ...ITEMS[0], tipo: 'INGRESO_REPETIDO', detalle: 'Código QR ya usado.' }]}
                corregir={vi.fn()}
            />
        );
        fireEvent.click(screen.getByRole('button', { name: 'Adjuntar evidencia' }));
        const dialogo = within(screen.getByRole('dialog', { name: 'Evidencia' }));
        expect(dialogo.queryByRole('textbox', { name: /Detalle/ })).not.toBeInTheDocument();
        expect(dialogo.getByText('Código QR ya usado.')).toBeInTheDocument();
    });
});

describe('BotonIncidenciasComunes', () => {
    it('cambia la gravedad de un tipo, agrega y quita una propia', async () => {
        const lista = [
            {
                id: null,
                tipo: 'DISPOSITIVO_NO_PERMITIDO',
                nombre: 'Dispositivo no permitido',
                gravedad: 'GRAVE',
                propia: false,
            },
        ];
        const propia = {
            id: 5,
            tipo: 'OTRA',
            nombre: 'Uso de corrector',
            gravedad: 'LEVE',
            propia: true,
        };
        simularApi(api, {
            'GET /incidencias-comunes': { data: lista },
            'PUT /incidencias-comunes': ({ data }) => ({
                data: data.tipo
                    ? [{ ...lista[0], gravedad: data.gravedad }]
                    : [...lista, { ...propia, nombre: data.nombre, gravedad: data.gravedad }],
            }),
            'DELETE /incidencias-comunes/5': { data: lista },
        });
        render(<BotonIncidenciasComunes />);
        fireEvent.click(screen.getByRole('button', { name: 'Mis incidencias' }));
        const dialogo = within(screen.getByRole('dialog', { name: 'Mis incidencias comunes' }));

        const fila = within(
            await dialogo.findByRole('radiogroup', { name: 'Gravedad de Dispositivo no permitido' })
        );
        fireEvent.click(fila.getByRole('radio', { name: 'Muy grave' }));
        await waitFor(() =>
            expect(api.put).toHaveBeenCalledWith('/incidencias-comunes', {
                tipo: 'DISPOSITIVO_NO_PERMITIDO',
                gravedad: 'MUY_GRAVE',
            })
        );

        fireEvent.change(dialogo.getByRole('textbox', { name: /Nueva incidencia/ }), {
            target: { value: 'Uso de corrector' },
        });
        fireEvent.click(dialogo.getByRole('button', { name: 'Agregar' }));
        expect(await dialogo.findByText('Elija leve, grave o muy grave')).toBeInTheDocument();
        fireEvent.click(
            within(dialogo.getByRole('radiogroup', { name: 'Gravedad' })).getByRole('radio', {
                name: 'Leve',
            })
        );
        fireEvent.click(dialogo.getByRole('button', { name: 'Agregar' }));

        fireEvent.click(await dialogo.findByRole('button', { name: 'Quitar Uso de corrector' }));
        await waitFor(() => expect(api.delete).toHaveBeenCalledWith('/incidencias-comunes/5'));
        await waitFor(() =>
            expect(dialogo.queryByText('Uso de corrector')).not.toBeInTheDocument()
        );
    });
});

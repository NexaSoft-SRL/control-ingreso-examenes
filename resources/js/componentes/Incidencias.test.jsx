import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { BotonIncidencia, ListaIncidencias, TIPOS_INCIDENCIA } from './Incidencias';
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

const elegirTipo = (dialogo, tipo = 'OTRA') =>
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
            'Intento rechazado · Media',
            'Suplantación de identidad · Grave',
            'Material no permitido · Media',
            'Dispositivo no permitido · Media',
            'Cambio de aula · Leve',
            'Otra · Leve',
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
            estudiante: '222011223',
            detalle: 'Calculadora programable',
        });
        expect(screen.getByRole('status')).toHaveTextContent('Incidencia registrada');
        expect(alRegistrar).toHaveBeenCalledTimes(1);
    });

    it('con foto envía un formulario con la evidencia', async () => {
        const registrar = vi.fn(() => Promise.resolve());
        render(<BotonIncidencia aula={AULAS[0]} registrar={registrar} />);
        const dialogo = abrir();
        elegirTipo(dialogo);

        fireEvent.change(dialogo.getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Hoja con fórmulas' },
        });
        fireEvent.change(dialogo.getByLabelText('Evidencia'), { target: { files: [FOTO] } });
        expect(await dialogo.findByRole('img', { name: 'Evidencia' })).toBeInTheDocument();
        fireEvent.click(dialogo.getByRole('button', { name: 'Registrar' }));

        await waitFor(() => expect(registrar).toHaveBeenCalledTimes(1));
        const cuerpo = registrar.mock.calls[0][0];
        expect(cuerpo).toBeInstanceOf(FormData);
        expect(cuerpo.get('aula_id')).toBe('30');
        expect(cuerpo.get('detalle')).toBe('Hoja con fórmulas');
        expect(cuerpo.get('evidencia')).toBe(FOTO);
        expect(cuerpo.has('estudiante')).toBe(false);
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
                        tipo: 'INGRESO_REPETIDO',
                        tipo_texto: 'Ingreso repetido',
                        gravedad: 'GRAVE',
                    },
                    ITEMS[0],
                    ITEMS[1],
                ]}
            />
        );

        expect(screen.getByText('Grave')).toHaveClass('text-danger-700');
        expect(screen.getByText('Media')).toHaveClass('text-warning-700');
        // Sin `gravedad` la toma del tipo; atendida, en gris.
        expect(screen.getByText('Leve')).toHaveClass('text-slate-500');
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
            detalle: 'Reloj inteligente',
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
        simularApi(api, { 'GET /incidencias/40/evidencia': new Blob(['jpeg']) });
        render(<ListaIncidencias items={[{ ...ITEMS[0], evidencia: true }]} />);

        fireEvent.click(screen.getByRole('button', { name: 'Evidencia' }));

        const dialogo = within(screen.getByRole('dialog', { name: 'Evidencia' }));
        expect(await dialogo.findByRole('img', { name: 'Evidencia' })).toHaveAttribute(
            'src',
            'blob:foto'
        );
        expect(api.get).toHaveBeenCalledWith('/incidencias/40/evidencia', { responseType: 'blob' });
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

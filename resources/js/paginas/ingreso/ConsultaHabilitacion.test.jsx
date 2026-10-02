import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConsultaHabilitacion from './ConsultaHabilitacion';
import { guardarSesion, limpiarSesion } from '../../componentes/sesion.js';

const examenes = [
    { id: 7, asignatura_codigo: 'INF-342', nombre: 'Primer parcial', fecha: '2026-10-15' },
    { id: 9, asignatura_codigo: 'INF-271', nombre: 'Examen final', fecha: '2026-10-22' },
];

const habilitado = {
    id: 15,
    codigo_universitario: '202104821',
    ci: '7928194',
    nombre: 'Kevin',
    apellido: 'Alvarado',
    carrera: 'Ingeniería de Sistemas',
    condicion: 'HABILITADO',
    habilitado: true,
    motivo: null,
    ambiente: 'Aula 691B',
    registrado_por: 'Docente UMSS',
    fecha_registro: '2026-10-02T14:30:00+00:00',
};

const noHabilitado = {
    id: 16,
    codigo_universitario: '202105533',
    ci: '7712045',
    nombre: 'Luis',
    apellido: 'Mamani',
    carrera: 'Ingeniería Industrial',
    condicion: 'NO_HABILITADO',
    habilitado: false,
    motivo: 'Adeuda la matrícula del semestre',
    ambiente: null,
    registrado_por: 'Docente UMSS',
    fecha_registro: '2026-10-02T14:30:00+00:00',
};

function simularApi(consulta) {
    window.axios = {
        get: vi.fn((url) =>
            url === '/api/consulta-habilitacion/examenes'
                ? Promise.resolve({ data: { data: examenes } })
                : consulta()
        ),
    };
}

async function consultar(valor) {
    fireEvent.change(await screen.findByLabelText(/Código universitario o documento/), {
        target: { value: valor },
    });
    fireEvent.click(screen.getByRole('button', { name: /Consultar/ }));
}

beforeEach(() => {
    guardarSesion({ id: 3, name: 'Personal de control', permisos: ['punto_control'] });
    simularApi(() => Promise.resolve({ data: { data: [habilitado] } }));
});

afterEach(() => {
    limpiarSesion();
});

describe('ConsultaHabilitacion', () => {
    it('carga los exámenes y deja el foco en el campo de consulta', async () => {
        render(<ConsultaHabilitacion />);

        const campo = await screen.findByLabelText(/Código universitario o documento/);

        expect(campo).toHaveFocus();
        expect(campo).toBeRequired();
        // El documento puede llevar complemento con letras: nada de teclado numérico.
        expect(campo).not.toHaveAttribute('inputmode');
        expect(screen.getByLabelText('Examen')).toHaveValue('7');
        expect(window.axios.get).toHaveBeenCalledWith('/api/consulta-habilitacion/examenes');
    });

    it('muestra la condición, el ambiente y el tiempo de respuesta del habilitado', async () => {
        render(<ConsultaHabilitacion />);
        await consultar('  202104821 ');

        const tarjeta = await screen.findByRole('article', {
            name: 'Resultado de Kevin Alvarado',
        });

        expect(tarjeta).toHaveTextContent('Habilitado');
        expect(tarjeta).toHaveTextContent('Alvarado, Kevin');
        expect(tarjeta).toHaveTextContent('Aula 691B');
        expect(tarjeta).not.toHaveTextContent('Motivo de la inhabilitación');
        expect(screen.getByText(/Respondió en \d+,\d{2} s/)).toBeInTheDocument();
        expect(window.axios.get).toHaveBeenCalledWith('/api/consulta-habilitacion/examenes/7', {
            params: { identificador: '202104821' },
        });
    });

    it('muestra el motivo, quién lo registró y cuándo si no está habilitado', async () => {
        simularApi(() => Promise.resolve({ data: { data: [noHabilitado] } }));
        render(<ConsultaHabilitacion />);
        await consultar('7712045');

        const tarjeta = await screen.findByRole('article', { name: 'Resultado de Luis Mamani' });

        expect(tarjeta).toHaveTextContent('No habilitado');
        expect(tarjeta).toHaveTextContent('Adeuda la matrícula del semestre');
        expect(tarjeta).toHaveTextContent('Sin ambiente asignado todavía');
        expect(tarjeta).toHaveTextContent(/Registrado por Docente UMSS el 02\/10\/2026,? 10:30/);
    });

    it('explica cuando el docente todavía no registró la condición', async () => {
        simularApi(() =>
            Promise.resolve({
                data: {
                    data: [
                        {
                            ...noHabilitado,
                            motivo: null,
                            registrado_por: null,
                            fecha_registro: null,
                        },
                    ],
                },
            })
        );
        render(<ConsultaHabilitacion />);
        await consultar('7712045');

        expect(
            await screen.findByText(
                'El docente todavía no registró su habilitación para este examen.'
            )
        ).toBeInTheDocument();
    });

    it('avisa cuando nadie del padrón coincide', async () => {
        simularApi(() =>
            Promise.reject({
                response: {
                    status: 404,
                    data: {
                        message:
                            'Ningún estudiante activo del padrón tiene ese código universitario o documento.',
                    },
                },
            })
        );
        render(<ConsultaHabilitacion />);
        await consultar('000000');

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Ningún estudiante activo del padrón tiene ese código universitario o documento.'
        );
        expect(screen.queryByRole('article')).not.toBeInTheDocument();
    });

    it('no llama a la API con el campo vacío', async () => {
        render(<ConsultaHabilitacion />);
        await consultar('   ');

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Escribe el código universitario o el documento de identidad.'
        );
        expect(window.axios.get).toHaveBeenCalledTimes(1);
    });

    it('al enviar el campo vacío no deja el tiempo de la consulta anterior', async () => {
        render(<ConsultaHabilitacion />);
        await consultar('202104821');
        await screen.findByRole('article', { name: 'Resultado de Kevin Alvarado' });
        expect(screen.getByText(/Respondió en/)).toBeInTheDocument();

        await consultar('');

        expect(await screen.findByRole('alert')).toBeInTheDocument();
        expect(screen.queryByRole('article')).not.toBeInTheDocument();
        expect(screen.queryByText(/Respondió en/)).not.toBeInTheDocument();
        expect(screen.getByLabelText(/Código universitario o documento/)).toHaveFocus();
    });

    it('consulta sobre el examen elegido y muestra todas las coincidencias', async () => {
        simularApi(() => Promise.resolve({ data: { data: [habilitado, noHabilitado] } }));
        render(<ConsultaHabilitacion />);

        fireEvent.change(await screen.findByLabelText('Examen'), { target: { value: '9' } });
        await consultar('8800880');

        expect(await screen.findAllByRole('article')).toHaveLength(2);
        expect(screen.getByText(/Confirma\s+con el nombre/)).toBeInTheDocument();
        await waitFor(() =>
            expect(window.axios.get).toHaveBeenCalledWith('/api/consulta-habilitacion/examenes/9', {
                params: { identificador: '8800880' },
            })
        );
    });
});

import { fireEvent, screen } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import AccesoEstudiante from './AccesoEstudiante';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar(rutas = {}) {
    simularApi(api, {
        'POST /auth/estudiante/codigo': { correo: '2021•••17@est.umss.edu', minutos: 10 },
        'POST /auth/estudiante': { estudiante: { id: 7 } },
        ...rutas,
    });
    return renderConSesion(
        <Routes>
            <Route path="/estudiante/ingreso" element={<AccesoEstudiante />} />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { usuario: null, ruta: '/estudiante/ingreso' }
    );
}

const escribir = (etiqueta, valor) =>
    fireEvent.change(screen.getByLabelText(etiqueta), { target: { value: valor } });

describe('AccesoEstudiante', () => {
    it('pide el código SIS antes de enviar', () => {
        montar();
        escribir(/Código SIS/, '123');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));

        expect(screen.getByText('Entre 5 y 10 dígitos')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('dice a qué correo se envía el código apenas el código SIS tiene formato', () => {
        montar();
        escribir(/Código SIS/, '2021');
        expect(screen.queryByText(/Se enviará a/)).not.toBeInTheDocument();

        escribir(/Código SIS/, '202100417');
        expect(screen.getByText('Se enviará a 202100417@est.umss.edu')).toBeInTheDocument();
    });

    it('envía el código al correo y entra con él', async () => {
        montar();
        escribir(/Código SIS/, '202100417');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));

        expect(await screen.findByText('2021•••17@est.umss.edu')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/estudiante/codigo', {
            codigo_universitario: '202100417',
        });

        escribir(/^Código/, '48a2913');
        fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));

        expect(await screen.findByText('Estoy en /estudiante')).toBeInTheDocument();
        expect(api.post).toHaveBeenLastCalledWith('/auth/estudiante', {
            codigo_universitario: '202100417',
            codigo: '482913',
        });
    });

    it('un SIS que no está en el padrón pide sus datos y se empadrona', async () => {
        montar({
            'POST /auth/estudiante': { registro: { codigo_universitario: '199900001' } },
            'GET /auth/estudiante/carreras': {
                data: [
                    {
                        id: 3,
                        nombre: 'Ingeniería de Sistemas',
                        facultad_id: 1,
                        facultad: 'Ciencias y Tecnología',
                    },
                    {
                        id: 8,
                        nombre: 'Arquitectura',
                        facultad_id: 2,
                        facultad: 'Arquitectura y Hábitat',
                    },
                ],
            },
            'POST /auth/estudiante/registro': { estudiante: { id: 9 } },
        });
        escribir(/Código SIS/, '199900001');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));
        await screen.findByText('2021•••17@est.umss.edu');
        escribir(/^Código/, '482913');
        fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));

        expect(await screen.findByRole('button', { name: 'Registrarme' })).toBeInTheDocument();

        // Sin datos no se envía nada.
        fireEvent.click(screen.getByRole('button', { name: 'Registrarme' }));
        expect(screen.getByText('Elija su facultad')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalledWith('/auth/estudiante/registro', expect.anything());

        escribir(/Nombres/, 'Rodrigo Andres');
        escribir(/Apellidos/, 'Velasquez Rojas');
        escribir(/Carnet/, '5003748');
        // La carrera espera a la facultad y solo ofrece las de esa facultad.
        expect(screen.getByLabelText(/Carrera/)).toBeDisabled();
        escribir(/Facultad/, '1');
        expect(screen.queryByRole('option', { name: 'Arquitectura' })).not.toBeInTheDocument();
        escribir(/Carrera/, '3');
        // Sin confirmar que reviso sus datos no se envía.
        fireEvent.click(screen.getByRole('button', { name: 'Registrarme' }));
        expect(screen.getByText('Confirme que revisó sus datos')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalledWith('/auth/estudiante/registro', expect.anything());
        fireEvent.click(screen.getByLabelText('Mis datos son correctos'));
        fireEvent.click(screen.getByRole('button', { name: 'Registrarme' }));

        expect(await screen.findByText('Estoy en /estudiante')).toBeInTheDocument();
        expect(api.post).toHaveBeenLastCalledWith('/auth/estudiante/registro', {
            nombres: 'Rodrigo Andres',
            apellidos: 'Velasquez Rojas',
            documento: '5003748',
            carrera_id: 3,
            confirmo: true,
        });
    });

    it('un carnet ya registrado se dice bajo el campo', async () => {
        montar({
            'POST /auth/estudiante': { registro: { codigo_universitario: '199900001' } },
            'GET /auth/estudiante/carreras': {
                data: [{ id: 3, nombre: 'Sistemas', facultad_id: 1, facultad: 'FCyT' }],
            },
            'POST /auth/estudiante/registro': errorHttp(422, {
                errors: { documento: ['Ya está registrado · pida ayuda a su docente'] },
            }),
        });
        escribir(/Código SIS/, '199900001');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));
        await screen.findByText('2021•••17@est.umss.edu');
        escribir(/^Código/, '482913');
        fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
        await screen.findByRole('button', { name: 'Registrarme' });

        escribir(/Nombres/, 'Rodrigo');
        escribir(/Apellidos/, 'Velasquez');
        escribir(/Carnet/, '5003748');
        escribir(/Facultad/, '1');
        escribir(/Carrera/, '3');
        fireEvent.click(screen.getByLabelText('Mis datos son correctos'));
        fireEvent.click(screen.getByRole('button', { name: 'Registrarme' }));

        expect(
            await screen.findByText('Ya está registrado · pida ayuda a su docente')
        ).toBeInTheDocument();
    });

    it('muestra el código de prueba cuando el servidor lo devuelve', async () => {
        montar({
            'POST /auth/estudiante/codigo': {
                correo: '2021•••17@est.umss.edu',
                codigo_prueba: '135790',
            },
        });
        escribir(/Código SIS/, '202100417');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));

        expect(await screen.findByText('Modo de prueba · 135790')).toBeInTheDocument();
    });

    it('un código vencido se dice bajo el campo', async () => {
        montar({
            'POST /auth/estudiante': errorHttp(422, {
                errors: { codigo: ['Código no válido o vencido'] },
            }),
        });
        escribir(/Código SIS/, '202100417');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));
        await screen.findByRole('button', { name: 'Iniciar sesión' });

        escribir(/^Código/, '111111');
        fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));

        expect(await screen.findByText('Código no válido o vencido')).toBeInTheDocument();
    });

    it('al pegar el código SIS con espacios o guiones conserva todos los dígitos', () => {
        montar();
        escribir(/Código SIS/, '2024 100-07');
        expect(screen.getByLabelText(/Código SIS/)).toHaveValue('202410007');
    });

    it('reenviar confirma el envío y, si hay demasiados pedidos, lo dice', async () => {
        let pedidos = 0;
        montar({
            'POST /auth/estudiante/codigo': () =>
                ++pedidos === 1
                    ? { correo: '2021•••17@est.umss.edu' }
                    : errorHttp(429, { message: 'Demasiados pedidos · espere 10 minutos' }),
        });
        escribir(/Código SIS/, '202100417');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));
        const reenviar = await screen.findByRole('button', { name: /Reenviar en/ });
        expect(reenviar).toBeDisabled();
    });

    it('con la sesión ya abierta lleva al portal', async () => {
        montar({ 'GET /estudiante/sesion': { estudiante: { id: 7 } } });
        expect(await screen.findByText('Estoy en /estudiante')).toBeInTheDocument();
    });

    it('el código SIS solo admite números y lo avisa', () => {
        montar();
        escribir(/Código SIS/, 'SIS-2021a');

        expect(screen.getByLabelText(/Código SIS/)).toHaveValue('2021');
        expect(screen.getByText('Solo números')).toBeInTheDocument();
    });

    describe('registro de un SIS nuevo', () => {
        const entrarAlRegistro = async (carreras) => {
            montar({
                'POST /auth/estudiante': { registro: { codigo_universitario: '199900001' } },
                'GET /auth/estudiante/carreras': { data: carreras },
            });
            escribir(/Código SIS/, '199900001');
            fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));
            await screen.findByText('2021•••17@est.umss.edu');
            escribir(/^Código/, '482913');
            fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
            await screen.findByRole('button', { name: 'Registrarme' });
        };

        it('nombres solo letras, carnet con su complemento y todo lo obligatorio marcado', async () => {
            await entrarAlRegistro([
                { id: 3, nombre: 'Sistemas', facultad_id: 1, facultad: 'FCyT' },
                { id: 8, nombre: 'Arquitectura', facultad_id: 2, facultad: 'Hábitat' },
            ]);

            // Recién abierto, ningún campo acusa «Obligatorio» ni arrastra lo del paso anterior.
            expect(screen.queryByText('Obligatorio')).toBeNull();
            // La pista del complemento está a la vista antes de escribir.
            expect(screen.getByText('Complemento con guion: 5003748-1A')).toBeInTheDocument();

            escribir(/Nombres/, 'Ana2 María#');
            escribir(/Apellidos/, "D'Alessandro 7");
            escribir(/Carnet/, '5003748-1A!?');

            expect(screen.getByLabelText(/Nombres/)).toHaveValue('Ana María');
            expect(screen.getByLabelText(/Apellidos/)).toHaveValue("D'Alessandro ");
            expect(screen.getByLabelText(/Carnet/)).toHaveValue('5003748-1A');
            expect(screen.getByLabelText(/Nombres/)).toHaveAttribute('maxlength', '100');
            expect(screen.getByLabelText(/Carnet/)).toHaveAttribute('maxlength', '30');
            expect(screen.getByLabelText(/Nombres/)).toHaveAttribute('aria-required', 'true');
            expect(screen.getByText('Solo número y complemento')).toBeInTheDocument();
        });

        it('la carrera dice que espera la facultad', async () => {
            await entrarAlRegistro([
                { id: 3, nombre: 'Sistemas', facultad_id: 1, facultad: 'FCyT' },
                { id: 8, nombre: 'Arquitectura', facultad_id: 2, facultad: 'Hábitat' },
            ]);

            const carrera = screen.getByLabelText(/Carrera/);
            expect(carrera).toBeDisabled();
            expect(carrera).toHaveTextContent('Primero la facultad');
        });

        it('con una sola facultad ya viene elegida', async () => {
            await entrarAlRegistro([
                { id: 3, nombre: 'Sistemas', facultad_id: 1, facultad: 'FCyT' },
                { id: 4, nombre: 'Electrónica', facultad_id: 1, facultad: 'FCyT' },
            ]);

            expect(screen.getByLabelText(/Facultad/)).toHaveValue('1');
            expect(screen.getByLabelText(/Carrera/)).toBeEnabled();
        });
    });
});

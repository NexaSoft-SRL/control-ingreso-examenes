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
        'POST /auth/estudiante/codigo': { correo: '2021•••17@est.umss.edu', minutos: 1440 },
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

    it('envía el código al correo y entra con él', async () => {
        montar();
        escribir(/Código SIS/, '202100417');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar código al correo' }));

        expect(await screen.findByText('2021•••17@est.umss.edu')).toBeInTheDocument();
        expect(screen.getByText('Vence en 24 h')).toBeInTheDocument();
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
});

import { fireEvent, screen } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PropTypes from 'prop-types';
import AccesoAula from './AccesoAula';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

// La puerta la prueba su propio archivo: aquí basta ver qué recibe.
vi.mock('../ingreso/PuntoControl', () => {
    function PuertaSimulada({ acceso }) {
        const { search } = useLocation();
        return (
            <p>
                Puerta del aula {acceso.aula_nombre} · {search}
            </p>
        );
    }
    PuertaSimulada.propTypes = { acceso: PropTypes.object.isRequired };
    return { default: PuertaSimulada };
});

const ACCESO = {
    token: 'abc',
    aula_id: 31,
    aula_nombre: '624',
    examen_id: 1,
    nombre_colaborador: 'Vargas Arce Sergio',
    expira_en: '2026-10-12 09:45:00',
};

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar(rutas = {}, usuario = null) {
    simularApi(api, rutas);
    return renderConSesion(
        <Routes>
            <Route path="/acceso-aula" element={<AccesoAula />} />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { usuario, ruta: '/acceso-aula' }
    );
}

const escribir = (campo, valor) => fireEvent.change(campo, { target: { value: valor } });
const codigo = () => screen.findByLabelText(/Código/);

async function entrar(valor = '482913', nombre = 'Vargas Arce Sergio') {
    escribir(await codigo(), valor);
    escribir(screen.getByLabelText(/Nombre/), nombre);
    fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
}

describe('AccesoAula (HU-18)', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('pide el código de seis dígitos y el nombre', async () => {
        montar();

        expect(await codigo()).toHaveAttribute('inputmode', 'numeric');
        expect(screen.getByRole('heading', { name: 'Punto de control' })).toBeInTheDocument();
        expect(screen.getByLabelText(/Nombre/)).toHaveAttribute(
            'placeholder',
            'Apellidos y nombres'
        );
        expect(screen.getByRole('link', { name: 'Cuenta institucional' })).toHaveAttribute(
            'href',
            '/login'
        );

        escribir(await codigo(), '48a29-13777');
        expect(await codigo()).toHaveValue('482913');
    });

    it('valida antes de llamar al servidor', async () => {
        montar();
        await entrar('4829', 'Va');

        expect(screen.getByText('Seis dígitos')).toBeInTheDocument();
        expect(screen.getByText('Mínimo 3 letras, sin signos seguidos')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('espera la autorización del docente y entra apenas la da', async () => {
        let estado = 'pendiente';
        montar({
            'POST /auth/codigo-aula': { data: { ...ACCESO, estado: 'pendiente' } },
            'POST /auth/codigo-aula/salir': { message: 'Sesión cerrada' },
        });
        await entrar('482913', '  Vargas Arce Sergio ');

        expect(await screen.findByText('Esperando autorización')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/codigo-aula', {
            codigo: '482913',
            nombre_colaborador: 'Vargas Arce Sergio',
        });
        expect(localStorage.getItem('codigo_aula_token')).toBe('abc');
        expect(screen.getByText('Vargas Arce Sergio')).toBeInTheDocument();
        expect(screen.queryByText(/Puerta del aula/)).not.toBeInTheDocument();

        // El docente lo autoriza en otra aula del examen.
        simularApi(api, {
            'GET /auth/codigo-aula/sesion': () => ({
                data: { ...ACCESO, aula_id: 32, aula_nombre: '617', estado },
            }),
            'POST /auth/codigo-aula/salir': { message: 'Sesión cerrada' },
        });
        estado = 'autorizado';
        expect(
            await screen.findByText('Puerta del aula 617 · ?aula=32', {}, { timeout: 5000 })
        ).toBeInTheDocument();
        expect(screen.getByText('Aula 617')).toBeInTheDocument();
        expect(screen.getByText('· Hasta 09:45')).toBeInTheDocument();
        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Salir' }));
        expect(await codigo()).toHaveValue('');
        expect(localStorage.getItem('codigo_aula_token')).toBeNull();
        expect(api.post).toHaveBeenCalledWith('/auth/codigo-aula/salir', {}, { silencioso: true });
    });

    it('si el docente no lo autoriza vuelve al formulario y lo dice', async () => {
        montar({ 'POST /auth/codigo-aula': { data: { ...ACCESO, estado: 'pendiente' } } });
        await entrar();
        await screen.findByText('Esperando autorización');

        simularApi(api, {
            'GET /auth/codigo-aula/sesion': errorHttp(401, {
                message: 'El docente no autorizó el acceso.',
                estado: 'rechazado',
            }),
        });
        expect(
            await screen.findByText('El docente no autorizó el acceso', {}, { timeout: 5000 })
        ).toBeInTheDocument();
        expect(await codigo()).toBeInTheDocument();
    });

    it('cancelar la espera vuelve al formulario', async () => {
        montar({
            'POST /auth/codigo-aula': { data: { ...ACCESO, estado: 'pendiente' } },
            'POST /auth/codigo-aula/salir': { message: 'Sesión cerrada' },
        });
        await entrar();
        fireEvent.click(await screen.findByRole('button', { name: 'Cancelar' }));
        expect(await codigo()).toBeInTheDocument();
    });

    it('al recargar recupera el acceso autorizado', async () => {
        localStorage.setItem('codigo_aula_token', 'abc');
        montar({ 'GET /auth/codigo-aula/sesion': { data: { ...ACCESO, estado: 'autorizado' } } });

        expect(await screen.findByText('Puerta del aula 624 · ?aula=31')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/auth/codigo-aula/sesion', { silencioso: true });
    });

    it('fuera de hora dice cuándo abre la puerta', async () => {
        montar({
            'POST /auth/codigo-aula': errorHttp(422, {
                message: 'La puerta abre el 23 oct a las 09:12.',
                errors: { codigo: ['La puerta abre el 23 oct a las 09:12.'] },
            }),
        });
        await entrar();

        expect(await screen.findByText('La puerta abre el 23 oct a las 09:12')).toBeInTheDocument();
    });

    it('tras demasiados intentos dice cuánto esperar', async () => {
        montar({
            'POST /auth/codigo-aula': errorHttp(429, {
                message: 'Demasiados intentos.',
                reintentar_en: 240,
            }),
        });
        await entrar();

        expect(await screen.findByText('Demasiados intentos · espere 4 min')).toBeInTheDocument();
    });

    it('con cuenta va a la pantalla del rol', () => {
        montar({}, usuarioDePrueba('Auxiliar'));
        expect(screen.getByText(/Estoy en \//)).toBeInTheDocument();
    });
});

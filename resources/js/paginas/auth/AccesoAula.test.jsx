import { fireEvent, screen, within, waitFor } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import AccesoAula from './AccesoAula';
import { escucharInstalacion } from '../../utiles/instalacion';
import { usarAccesoAula } from '../../sesion/AccesoAulaContexto';
import { api, suscribirSesion, SIN_SESION } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';
import { act } from 'react';

vi.mock('../../api/cliente');

// La puerta la escribe otro paquete: aquí basta ver qué recibe.
vi.mock('../ingreso/PuntoControl', () => ({
    default: function PuertaSimulada() {
        const acceso = usarAccesoAula();
        const { search } = useLocation();
        return (
            <p>
                Puerta del aula {acceso.aula} · {search}
            </p>
        );
    },
}));

const ACCESO = {
    aula_id: 31,
    aula: '624',
    examen_id: 1,
    nombre: 'Vargas Arce Sergio',
    hasta: '2026-10-12T09:45:00-04:00',
};

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar(rutas, usuario = null) {
    simularApi(api, { 'GET /auth/codigo-aula': errorHttp(401), ...rutas });
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
    fireEvent.click(screen.getByRole('button', { name: 'Entrar al aula' }));
}

describe('AccesoAula', () => {
    it('también invita a instalar la aplicación, como el inicio de sesión', async () => {
        escucharInstalacion();
        montar();
        await codigo();

        const aviso = new Event('beforeinstallprompt', { cancelable: true });
        aviso.prompt = vi.fn(async () => {});
        aviso.userChoice = Promise.resolve({ outcome: 'dismissed' });
        act(() => {
            window.dispatchEvent(aviso);
        });

        expect(await screen.findByText('Instalar la aplicación')).toBeVisible();
    });

    it('muestra el formulario de una vez, sin esperar a saber si ya había un acceso', () => {
        // La consulta del estado tarda: el formulario no puede esperarla.
        simularApi(api, { 'GET /auth/codigo-aula': new Promise(() => {}) });
        renderConSesion(
            <Routes>
                <Route path="/acceso-aula" element={<AccesoAula />} />
            </Routes>,
            { usuario: null, ruta: '/acceso-aula' }
        );

        expect(screen.getByRole('button', { name: 'Entrar al aula' })).toBeVisible();
        expect(screen.getByRole('navigation', { name: 'Tipo de acceso' })).toBeVisible();
    });

    it('pide el código de seis dígitos y el nombre', async () => {
        montar();

        expect(await codigo()).toHaveAttribute('inputmode', 'numeric');
        expect(screen.getByRole('heading', { name: 'Punto de control' })).toBeInTheDocument();
        // Para quien no tiene cuenta, y de dónde sale el código.
        expect(screen.getByText('Sin cuenta · código de aula')).toBeInTheDocument();
        // El mismo selector que el inicio de sesión, con este lado activo.
        const tipos = screen.getByRole('navigation', { name: 'Tipo de acceso' });
        expect(within(tipos).getByRole('link', { name: 'Código de aula' })).toHaveAttribute(
            'aria-current',
            'page'
        );
        expect(within(tipos).getByRole('link', { name: 'Con cuenta' })).toHaveAttribute(
            'href',
            '/login'
        );
        expect(screen.getByText('Lo entrega el docente del examen')).toBeInTheDocument();

        escribir(await codigo(), '48a29-13777');
        expect(await codigo()).toHaveValue('482913');
        expect(await codigo()).toHaveFocus();
        expect(screen.getByText('Solo números')).toBeInTheDocument();
    });

    it('el nombre no deja poner números ni signos', async () => {
        montar();
        const campoNombre = await screen.findByLabelText(/^Nombre/);

        escribir(campoNombre, 'Vargas2 Arce#');

        expect(campoNombre).toHaveValue('Vargas Arce');
        expect(campoNombre).toHaveAttribute('maxlength', '120');
        expect(screen.getByText('Solo letras')).toBeInTheDocument();
    });

    it('valida antes de llamar al servidor', async () => {
        montar();
        await entrar('4829', 'Va');

        expect(screen.getByText('Seis dígitos')).toBeInTheDocument();
        expect(screen.getByText('Mínimo 3 letras')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('con el código válido muestra la puerta de esa aula, sin menú y con «Salir»', async () => {
        montar({ 'POST /auth/codigo-aula': { acceso: ACCESO }, 'DELETE /auth/codigo-aula': '' });
        await entrar('482913', '  Vargas Arce Sergio ');

        expect(await screen.findByText('Puerta del aula 624 · ?aula=31')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/codigo-aula', {
            codigo: '482913',
            nombre: 'Vargas Arce Sergio',
        });
        expect(screen.getByText('Vargas Arce Sergio')).toBeInTheDocument();
        expect(screen.getByText('Aula 624')).toBeInTheDocument();
        expect(screen.getByRole('banner')).toHaveClass('sticky', 'top-0');
        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();

        expect(screen.getByText('· Hasta 09:45')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Salir' }));
        expect(await codigo()).toHaveValue('');
        expect(screen.queryByText(/Puerta del aula/)).not.toBeInTheDocument();
        // Cierra con la ruta del código de aula, no con la de las cuentas.
        expect(api.delete).toHaveBeenCalledWith('/auth/codigo-aula', { silencioso: true });
        expect(api.post).not.toHaveBeenCalledWith(
            '/auth/logout',
            expect.anything(),
            expect.anything()
        );
    });

    it('sale aunque el cierre falle', async () => {
        montar({
            'POST /auth/codigo-aula': { acceso: ACCESO },
            'DELETE /auth/codigo-aula': errorHttp(500, {}),
        });
        await entrar();
        await screen.findByText(/Puerta del aula/);

        fireEvent.click(screen.getByRole('button', { name: 'Salir' }));
        expect(await codigo()).toHaveValue('');
    });

    it('con el examen ya terminado dice cuándo cerró', async () => {
        montar({
            'POST /auth/codigo-aula': errorHttp(422, {
                message: 'El examen ya terminó: cerró el 23 oct a las 11:42.',
                errors: { codigo: ['El examen ya terminó: cerró el 23 oct a las 11:42.'] },
            }),
        });
        await entrar();

        expect(
            await screen.findByText('El examen ya terminó: cerró el 23 oct a las 11:42')
        ).toBeInTheDocument();
    });

    it('con el código rechazado lo dice bajo el campo', async () => {
        montar({ 'POST /auth/codigo-aula': errorHttp(422, { message: 'Código no válido.' }) });
        await entrar();

        expect(await screen.findByText('Código no válido')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Entrar al aula' })).toBeEnabled();
    });

    it('tras demasiados intentos lo indica', async () => {
        montar({ 'POST /auth/codigo-aula': errorHttp(429, { message: 'Too Many Attempts.' }) });
        await entrar();

        expect(await screen.findByText('Demasiados intentos · espere 5 min')).toBeInTheDocument();
    });

    it('al recargar recupera el acceso con su nombre y su vigencia', async () => {
        montar({ 'GET /auth/codigo-aula': { acceso: { ...ACCESO, estado: 'autorizado' } } });

        expect(await screen.findByText('Puerta del aula 624 · ?aula=31')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/auth/codigo-aula', { silencioso: true });
        expect(screen.getByText('Vargas Arce Sergio')).toBeInTheDocument();
        expect(screen.getByText('Aula 624')).toBeInTheDocument();
        expect(screen.getByText('· Hasta 09:45')).toBeInTheDocument();
    });

    it('espera la autorización del docente y entra apenas la da', async () => {
        let estado = 'pendiente';
        montar({
            'POST /auth/codigo-aula': { acceso: { ...ACCESO, estado: 'pendiente' } },
        });
        await entrar();

        expect(await screen.findByText('Esperando autorización')).toBeInTheDocument();
        expect(screen.getByText('Vargas Arce Sergio')).toBeInTheDocument();
        expect(screen.queryByText(/Puerta del aula/)).not.toBeInTheDocument();

        simularApi(api, {
            'GET /auth/codigo-aula': () => ({
                acceso: { ...ACCESO, aula_id: 32, aula: '617', estado },
            }),
        });
        estado = 'autorizado';
        expect(
            await screen.findByText('Puerta del aula 617 · ?aula=32', {}, { timeout: 5000 })
        ).toBeInTheDocument();
    });

    it('si el docente no lo autoriza vuelve al formulario y lo dice', async () => {
        montar({ 'POST /auth/codigo-aula': { acceso: { ...ACCESO, estado: 'pendiente' } } });
        await entrar();
        await screen.findByText('Esperando autorización');

        simularApi(api, {
            'GET /auth/codigo-aula': errorHttp(401, {
                message: 'El docente no autorizó el acceso.',
                estado: 'rechazado',
            }),
        });
        expect(
            await screen.findByText('El docente no autorizó el acceso', {}, { timeout: 5000 })
        ).toBeInTheDocument();
        expect(await codigo()).toBeInTheDocument();
    });

    it('cuando el acceso vence vuelve al formulario', async () => {
        montar({ 'POST /auth/codigo-aula': { acceso: ACCESO } });
        await entrar();
        await screen.findByText(/Puerta del aula/);

        const oyente = suscribirSesion.mock.calls.at(-1)[0];
        act(() => oyente(SIN_SESION));
        await waitFor(() => expect(screen.queryByText(/Puerta del aula/)).not.toBeInTheDocument());
        expect(screen.getByRole('button', { name: 'Entrar al aula' })).toBeInTheDocument();
    });

    it('con cuenta va a la pantalla del rol', () => {
        montar({}, usuarioDePrueba('Auxiliar'));
        expect(screen.getByText('Estoy en /ingreso')).toBeInTheDocument();
    });
});

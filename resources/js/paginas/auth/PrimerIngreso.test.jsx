import { fireEvent, screen, waitFor } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import PrimerIngreso from './PrimerIngreso';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

const temporal = () => usuarioDePrueba('Docente', { debe_cambiar_contrasena: true });

function montar({ usuario = temporal(), sesion = {} } = {}) {
    return renderConSesion(
        <Routes>
            <Route path="/primer-ingreso" element={<PrimerIngreso />} />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { usuario, ruta: '/primer-ingreso', sesion }
    );
}

const escribir = (campo, valor) => fireEvent.change(campo, { target: { value: valor } });
const clave = () => screen.getByLabelText(/^Contraseña/);
const repetida = () => screen.getByLabelText(/Repetir contraseña/);
const guardar = () => screen.getByRole('button', { name: 'Guardar y entrar' });
const regla = (texto) => screen.getByText(texto).closest('li');

describe('PrimerIngreso', () => {
    it('tiene las reglas a la vista y se van cumpliendo al escribir', () => {
        montar();

        expect(screen.getByRole('heading', { name: 'Contraseña nueva' })).toBeInTheDocument();
        expect(screen.getByText('Primer ingreso · cambio obligatorio')).toBeInTheDocument();
        ['10 caracteres o más', 'Mayúscula y minúscula', 'Número'].forEach((texto) =>
            expect(regla(texto)).toHaveAttribute('data-cumplida', 'false')
        );
        expect(guardar()).toBeDisabled();

        escribir(clave(), 'Abcdefghij');
        expect(regla('10 caracteres o más')).toHaveAttribute('data-cumplida', 'true');
        expect(regla('Mayúscula y minúscula')).toHaveClass('text-success-700');
        expect(regla('Número')).toHaveAttribute('data-cumplida', 'false');

        escribir(clave(), 'Abcdefghi1');
        expect(regla('Número')).toHaveAttribute('data-cumplida', 'true');
        expect(guardar()).toBeDisabled();
    });

    it('exige que la repetición coincida', () => {
        montar();
        escribir(clave(), 'Abcdefghi1');
        escribir(repetida(), 'Abcdefghi2');

        expect(screen.getByText('No coincide')).toBeInTheDocument();
        expect(guardar()).toBeDisabled();

        escribir(repetida(), 'Abcdefghi1');
        expect(screen.queryByText('No coincide')).not.toBeInTheDocument();
        expect(guardar()).toBeEnabled();
    });

    it('guarda sin pedir la contraseña actual y entra a la pantalla del rol', async () => {
        simularApi(api, { 'POST /auth/contrasena': { message: 'Contraseña actualizada.' } });
        const actualizar = vi.fn(() => Promise.resolve(usuarioDePrueba('Docente')));
        montar({ sesion: { actualizar } });

        escribir(clave(), 'Abcdefghi1');
        escribir(repetida(), 'Abcdefghi1');
        fireEvent.click(guardar());

        expect(await screen.findByText('Estoy en /examenes')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/contrasena', {
            contrasena_nueva: 'Abcdefghi1',
            contrasena_nueva_confirmation: 'Abcdefghi1',
        });
        expect(actualizar).toHaveBeenCalledTimes(1);
    });

    it('muestra bajo el campo el rechazo del servidor', async () => {
        simularApi(api, {
            'POST /auth/contrasena': errorHttp(422, {
                message: '…',
                errors: { contrasena_nueva: ['Debe ser distinta de la actual'] },
            }),
        });
        montar();

        escribir(clave(), 'Abcdefghi1');
        escribir(repetida(), 'Abcdefghi1');
        fireEvent.click(guardar());

        expect(await screen.findByText('Debe ser distinta de la actual')).toBeInTheDocument();
        await waitFor(() => expect(guardar()).toBeEnabled());
    });

    it('sin sesión va al acceso y con contraseña propia a la entrada', () => {
        const { unmount } = montar({ usuario: null });
        expect(screen.getByText('Estoy en /login')).toBeInTheDocument();
        unmount();

        montar({ usuario: usuarioDePrueba('Auxiliar') });
        expect(screen.getByText('Estoy en /ingreso')).toBeInTheDocument();
    });

    it('permite cerrar la sesión', async () => {
        const { sesion } = montar();

        fireEvent.click(screen.getByRole('button', { name: 'Cerrar sesión' }));
        await waitFor(() => expect(screen.getByText('Estoy en /login')).toBeInTheDocument());
        expect(sesion.salir).toHaveBeenCalledTimes(1);
    });
});

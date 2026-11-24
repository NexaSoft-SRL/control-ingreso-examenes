import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import EsquemaApp from './EsquemaApp';
import { api } from '../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../test/apoyo';

vi.mock('../api/cliente');

function montar(respuesta = { message: 'Contraseña actualizada.' }) {
    simularApi(api, { 'POST /auth/contrasena': respuesta });
    return renderConSesion(
        <Routes>
            <Route path="*" element={<EsquemaApp>contenido</EsquemaApp>} />
        </Routes>,
        { usuario: usuarioDePrueba('Docente'), ruta: '/examenes' }
    );
}

const escribir = (etiqueta, valor) =>
    fireEvent.change(screen.getByLabelText(etiqueta), { target: { value: valor } });
const guardar = () =>
    within(screen.getByRole('dialog', { name: 'Cambiar contraseña' })).getByRole('button', {
        name: 'Cambiar contraseña',
    });

function abrir(respuesta) {
    montar(respuesta);
    fireEvent.click(screen.getAllByRole('button', { name: 'Cambiar contraseña' })[0]);
}

function llenar(actual = 'Anterior123') {
    escribir(/Contraseña actual/, actual);
    escribir(/^Contraseña nueva/, 'Nuevaclave1');
    escribir(/Repetir contraseña nueva/, 'Nuevaclave1');
}

describe('CambiarContrasena', () => {
    it('está en la cabecera, en el teléfono y en escritorio', () => {
        montar();
        expect(screen.getAllByRole('button', { name: 'Cambiar contraseña' })).toHaveLength(2);
    });

    it('pide la actual, las reglas y que la repetición coincida', () => {
        abrir();
        expect(screen.getByRole('dialog', { name: 'Cambiar contraseña' })).toBeInTheDocument();
        expect(screen.getByText('10 caracteres o más')).toBeInTheDocument();
        expect(guardar()).toBeDisabled();

        escribir(/^Contraseña nueva/, 'Nuevaclave1');
        escribir(/Repetir contraseña nueva/, 'Nuevaclave1');
        expect(guardar()).toBeDisabled();

        escribir(/Contraseña actual/, 'Anterior123');
        expect(guardar()).toBeEnabled();

        escribir(/Repetir contraseña nueva/, 'Otraclave1');
        expect(screen.getByText('No coincide')).toBeInTheDocument();
        expect(guardar()).toBeDisabled();
    });

    it('guarda con la actual, cierra y avisa', async () => {
        abrir();
        llenar();
        fireEvent.click(guardar());

        expect(await screen.findByText('Contraseña actualizada')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/contrasena', {
            contrasena_actual: 'Anterior123',
            contrasena_nueva: 'Nuevaclave1',
            contrasena_nueva_confirmation: 'Nuevaclave1',
        });
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('muestra en su campo que la actual no es correcta', async () => {
        abrir(
            errorHttp(422, {
                message: 'La contraseña actual no es correcta.',
                errors: { contrasena_actual: ['La contraseña actual no es correcta.'] },
            })
        );
        llenar('Equivocada1');
        fireEvent.click(guardar());

        expect(await screen.findByText('La contraseña actual no es correcta.')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        await waitFor(() => expect(guardar()).toBeEnabled());
    });

    it('se cierra con cancelar sin enviar nada', () => {
        abrir();
        fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });
});

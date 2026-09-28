import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UsuariosRoles from './UsuariosRoles';

const usuarios = [
    {
        id: 1,
        nombre: 'Administrador',
        correo: 'admin@umss.edu.bo',
        rol: 'Administrador',
        is_active: true,
    },
];
const permisos = [
    { id: 4, name: 'bitacora', screen_name: 'Bitácora' },
    { id: 5, name: 'usuarios_roles', screen_name: 'Usuarios y roles' },
];
const roles = [
    { id: 1, name: 'Administrador', permissions: [permisos[0]] },
    { id: 2, name: 'Docente', permissions: [] },
];

function respuestaDe(url) {
    if (url === '/api/auth/admin/roles') {
        return Promise.resolve({ data: roles });
    }

    if (url === '/api/auth/admin/permissions') {
        return Promise.resolve({ data: permisos });
    }

    return Promise.resolve({ data: usuarios });
}

beforeEach(() => {
    window.axios = {
        get: vi.fn(respuestaDe),
        post: vi.fn().mockResolvedValue({ data: { user: { id: 9 } } }),
        put: vi.fn().mockResolvedValue({ data: {} }),
        patch: vi.fn().mockResolvedValue({ data: {} }),
    };
});

async function abrirFormulario() {
    render(<UsuariosRoles onNavigate={vi.fn()} />);
    await screen.findByText('admin@umss.edu.bo');
    fireEvent.click(screen.getByRole('button', { name: '+ Nuevo usuario' }));
}

describe('UsuariosRoles', () => {
    it('ofrece los roles que existen en la base', async () => {
        await abrirFormulario();

        await waitFor(() =>
            expect(screen.getByRole('option', { name: 'Docente' })).toBeInTheDocument()
        );
        expect(screen.getByRole('option', { name: 'Administrador' })).toBeInTheDocument();
        expect(screen.queryByRole('option', { name: 'Responsable académico' })).toBeNull();
    });

    it('pide los datos del docente y lo da de alta junto con la cuenta', async () => {
        await abrirFormulario();

        fireEvent.change(screen.getByLabelText('Nombres'), { target: { value: 'Marcela' } });
        fireEvent.change(screen.getByLabelText('Correo'), {
            target: { value: 'marcela@umss.edu.bo' },
        });
        fireEvent.change(screen.getByLabelText('Código de docente'), {
            target: { value: 'DOC-900' },
        });
        fireEvent.change(screen.getByLabelText('Apellidos'), { target: { value: 'Quiroga' } });
        fireEvent.click(screen.getByRole('button', { name: 'Crear usuario' }));

        await waitFor(() =>
            expect(window.axios.post).toHaveBeenCalledWith('/api/docentes', {
                codigo_docente: 'DOC-900',
                nombres: 'Marcela',
                apellidos: 'Quiroga',
                correo: 'marcela@umss.edu.bo',
                telefono: null,
                user_id: 9,
            })
        );

        expect(window.axios.post).toHaveBeenCalledWith('/api/auth/admin/users', {
            nombre: 'Marcela',
            correo: 'marcela@umss.edu.bo',
            rol: 'Docente',
        });
    });

    it('no envía el alta de docente si falta el código', async () => {
        await abrirFormulario();

        fireEvent.change(screen.getByLabelText('Nombres'), { target: { value: 'Marcela' } });
        fireEvent.change(screen.getByLabelText('Correo'), {
            target: { value: 'marcela@umss.edu.bo' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Crear usuario' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Para dar de alta a un docente hacen falta su código y sus apellidos.'
        );
        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('para otros roles solo crea la cuenta', async () => {
        await abrirFormulario();

        await waitFor(() =>
            expect(screen.getByRole('option', { name: 'Administrador' })).toBeInTheDocument()
        );
        fireEvent.change(screen.getByLabelText('Rol'), { target: { value: 'Administrador' } });
        fireEvent.change(screen.getByLabelText('Nombre completo'), {
            target: { value: 'Nuevo Admin' },
        });
        fireEvent.change(screen.getByLabelText('Correo'), {
            target: { value: 'nuevo@umss.edu.bo' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Crear usuario' }));

        await waitFor(() => expect(window.axios.post).toHaveBeenCalledTimes(1));
        expect(window.axios.post).toHaveBeenCalledWith('/api/auth/admin/users', {
            nombre: 'Nuevo Admin',
            correo: 'nuevo@umss.edu.bo',
            rol: 'Administrador',
        });
    });

    it('edita la cuenta en vez de volver a crearla', async () => {
        render(<UsuariosRoles onNavigate={vi.fn()} />);
        await screen.findByText('admin@umss.edu.bo');

        fireEvent.click(screen.getByRole('button', { name: 'Editar' }));
        fireEvent.change(screen.getByLabelText('Nombre completo'), {
            target: { value: 'Administrador General' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));

        await waitFor(() =>
            expect(window.axios.put).toHaveBeenCalledWith('/api/auth/admin/users/1', {
                nombre: 'Administrador General',
                correo: 'admin@umss.edu.bo',
                rol: 'Administrador',
            })
        );
        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('desactiva la cuenta sin borrarla', async () => {
        render(<UsuariosRoles onNavigate={vi.fn()} />);
        await screen.findByText('admin@umss.edu.bo');

        fireEvent.click(screen.getByRole('button', { name: 'Desactivar a Administrador' }));

        await waitFor(() =>
            expect(window.axios.patch).toHaveBeenCalledWith('/api/auth/admin/users/1/estado', {
                is_active: false,
            })
        );
    });

    it('guarda los permisos de un rol desde la pestaña Roles', async () => {
        render(<UsuariosRoles onNavigate={vi.fn()} />);
        await screen.findByText('admin@umss.edu.bo');

        fireEvent.click(screen.getByRole('button', { name: 'Roles' }));

        const casillas = await screen.findAllByRole('checkbox');

        // El rol Administrador llega con Bitácora marcada y Usuarios no.
        expect(casillas[0]).toBeChecked();
        expect(casillas[1]).not.toBeChecked();

        fireEvent.click(casillas[1]);
        fireEvent.click(screen.getAllByRole('button', { name: 'Guardar permisos' })[0]);

        await waitFor(() =>
            expect(window.axios.put).toHaveBeenCalledWith('/api/auth/admin/roles/1/permisos', {
                permisos: [4, 5],
            })
        );
    });

    it('avisa si el alta del docente falla después de crear la cuenta', async () => {
        window.axios.post = vi
            .fn()
            .mockResolvedValueOnce({ data: { user: { id: 9 } } })
            .mockRejectedValueOnce({
                response: {
                    data: { errors: { codigo_docente: ['Ya existe un docente con ese código.'] } },
                },
            });

        await abrirFormulario();

        fireEvent.change(screen.getByLabelText('Nombres'), { target: { value: 'Marcela' } });
        fireEvent.change(screen.getByLabelText('Correo'), {
            target: { value: 'marcela@umss.edu.bo' },
        });
        fireEvent.change(screen.getByLabelText('Código de docente'), {
            target: { value: 'DOC-900' },
        });
        fireEvent.change(screen.getByLabelText('Apellidos'), { target: { value: 'Quiroga' } });
        fireEvent.click(screen.getByRole('button', { name: 'Crear usuario' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Ya existe un docente con ese código. La cuenta sí quedó creada.'
        );
    });
});

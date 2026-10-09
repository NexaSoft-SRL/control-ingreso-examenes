import { describe, expect, it } from 'vitest';
import { VISTAS, destinoDe, entradaDe, vistaDeRuta, vistasDe } from './vistas';
import { CATALOGO_PERMISOS, usuarioDePrueba } from '../test/apoyo';

const rutas = (usuario) => vistasDe(usuario).map((v) => v.ruta);

describe('vistas por permiso', () => {
    it('declara las diez rutas del sprint, cada una con su permiso', () => {
        expect(VISTAS.map((v) => [v.ruta, v.permiso])).toEqual([
            ['/periodo', 'periodo_oferta'],
            ['/aulas', 'aulas_docentes'],
            ['/docentes', 'aulas_docentes'],
            ['/estudiantes', 'padron_estudiantes'],
            ['/examenes', 'examenes'],
            ['/examenes/nuevo', 'examenes'],
            ['/habilitacion', 'habilitacion'],
            ['/mis-grupos', 'mis_grupos'],
            ['/admin', 'usuarios_roles'],
            ['/bitacora', 'bitacora'],
        ]);
    });

    it('cada permiso de una vista existe en el catálogo', () => {
        VISTAS.forEach((v) => expect(CATALOGO_PERMISOS, v.ruta).toContain(v.permiso));
    });

    it('cada rol de inicio entra por su pantalla', () => {
        expect(entradaDe(usuarioDePrueba('Administrador'))).toBe('/periodo');
        expect(entradaDe(usuarioDePrueba('Docente'))).toBe('/examenes');
        expect(entradaDe(usuarioDePrueba('Auxiliar'))).toBeNull();
    });

    it('da las vistas de los permisos de la cuenta', () => {
        expect(rutas(usuarioDePrueba('Administrador'))).toEqual([
            '/periodo',
            '/aulas',
            '/docentes',
            '/estudiantes',
            '/admin',
            '/bitacora',
        ]);
        expect(rutas(usuarioDePrueba('Administrador', { permisos: CATALOGO_PERMISOS }))).toEqual(
            VISTAS.map((v) => v.ruta)
        );
        expect(rutas(usuarioDePrueba('Docente'))).toEqual([
            '/examenes',
            '/examenes/nuevo',
            '/habilitacion',
            '/mis-grupos',
        ]);
        expect(rutas(usuarioDePrueba('Auxiliar'))).toEqual([]);
        expect(rutas(null)).toEqual([]);
    });

    it('no depende del nombre del rol: un rol creado ve lo que sus permisos dicen', () => {
        const cuenta = usuarioDePrueba('Docente', {
            rol: 'Coordinación',
            permisos: ['examenes', 'periodo_oferta'],
        });
        expect(rutas(cuenta)).toEqual(['/periodo', '/examenes', '/examenes/nuevo']);
        expect(entradaDe(cuenta)).toBe('/periodo');
    });

    it('quita las vistas sin permiso', () => {
        const cuenta = usuarioDePrueba('Administrador', { permisos: ['bitacora'] });
        expect(rutas(cuenta)).toEqual(['/bitacora']);
        expect(entradaDe(cuenta)).toBe('/bitacora');
    });

    it('una vista oculta no es entrada', () => {
        const cuenta = usuarioDePrueba('Docente', { permisos: ['habilitacion'] });
        expect(rutas(cuenta)).toEqual(['/habilitacion']);
        expect(entradaDe(cuenta)).toBeNull();
    });

    it('decide el destino tras autenticarse', () => {
        expect(destinoDe(null)).toBe('/login');
        expect(destinoDe(usuarioDePrueba('Docente'))).toBe('/examenes');
        expect(destinoDe(usuarioDePrueba('Auxiliar'))).toBe('/sin-acceso');
        expect(destinoDe(usuarioDePrueba('Docente', { permisos: [] }))).toBe('/sin-acceso');
    });

    it('encuentra la vista de una dirección, la más específica primero', () => {
        const vistas = vistasDe(usuarioDePrueba('Docente'));
        expect(vistaDeRuta(vistas, '/examenes/nuevo').padre).toBe('/examenes');
        expect(vistaDeRuta(vistas, '/examenes').ruta).toBe('/examenes');
        expect(vistaDeRuta(vistas, '/periodo')).toBeNull();
    });
});

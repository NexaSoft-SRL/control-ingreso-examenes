import { describe, expect, it } from 'vitest';
import { VISTAS, destinoDe, entradaDe, gemelaDe, vistaDeRuta, vistasDe } from './vistas';
import { CATALOGO_PERMISOS, PERMISOS, usuarioDePrueba } from '../test/apoyo';

const rutas = (usuario) => vistasDe(usuario).map((v) => v.ruta);
const conTodo = () => usuarioDePrueba('Administrador', { permisos: CATALOGO_PERMISOS });

describe('vistas por permiso', () => {
    it('declara las veintiuna rutas, cada una con su permiso y su grupo', () => {
        expect(VISTAS.map((v) => [v.ruta, v.permiso, v.grupo])).toEqual([
            ['/periodo', 'periodo_oferta', 'Preparación'],
            ['/oferta', 'periodo_oferta', 'Preparación'],
            ['/aulas', 'aulas_docentes', 'Preparación'],
            ['/docentes', 'aulas_docentes', 'Preparación'],
            ['/estudiantes', 'padron_estudiantes', 'Preparación'],
            ['/examenes', 'examenes', 'Docencia'],
            ['/examenes/nuevo', 'examenes', 'Docencia'],
            ['/examenes/detalle', 'examenes', 'Docencia'],
            ['/habilitacion', 'habilitacion', 'Docencia'],
            ['/codigos-qr', 'codigos_qr', 'Docencia'],
            ['/en-curso', 'seguimiento_vivo', 'Docencia'],
            ['/en-curso/aula', 'seguimiento_vivo', 'Docencia'],
            ['/control', 'punto_control', 'Docencia'],
            ['/ingreso', 'punto_control', 'Docencia'],
            ['/mi-aula', 'seguimiento_vivo', 'Docencia'],
            ['/mis-grupos', 'mis_grupos', 'Docencia'],
            ['/mis-reportes', 'reportes_examenes', 'Docencia'],
            ['/reportes', 'reportes_universidad', 'Sistema'],
            ['/admin', 'usuarios_roles', 'Sistema'],
            ['/bitacora', 'bitacora', 'Sistema'],
            ['/respaldo', 'respaldo_restauracion', 'Sistema'],
        ]);
    });

    it('cada permiso de una vista existe en el catálogo y cada permiso tiene vista', () => {
        VISTAS.forEach((v) => {
            expect(CATALOGO_PERMISOS, v.ruta).toContain(v.permiso);
            if (v.con) expect(CATALOGO_PERMISOS, v.ruta).toContain(v.con);
            if (v.sin) expect(CATALOGO_PERMISOS, v.ruta).toContain(v.sin);
        });
        expect([...new Set(VISTAS.map((v) => v.permiso))].sort()).toEqual(
            [...CATALOGO_PERMISOS].sort()
        );
    });

    it('una vista oculta nombra a su padre, que está en el menú', () => {
        VISTAS.filter((v) => v.oculta).forEach((v) => {
            const padre = VISTAS.find((otra) => otra.ruta === v.padre);
            expect(padre, v.ruta).toBeTruthy();
            expect(padre.oculta, v.ruta).toBeFalsy();
        });
    });

    it('cada rol de inicio entra por su pantalla', () => {
        expect(entradaDe(usuarioDePrueba('Administrador'))).toBe('/periodo');
        expect(entradaDe(usuarioDePrueba('Docente'))).toBe('/examenes');
        expect(entradaDe(usuarioDePrueba('Auxiliar'))).toBe('/ingreso');
    });

    it('da las vistas de los permisos de la cuenta', () => {
        expect(rutas(usuarioDePrueba('Administrador'))).toEqual([
            '/periodo',
            '/oferta',
            '/aulas',
            '/docentes',
            '/estudiantes',
            '/reportes',
            '/admin',
            '/bitacora',
            '/respaldo',
        ]);
        expect(rutas(usuarioDePrueba('Docente'))).toEqual([
            '/examenes',
            '/examenes/nuevo',
            '/examenes/detalle',
            '/habilitacion',
            '/codigos-qr',
            '/en-curso',
            '/en-curso/aula',
            '/control',
            '/mis-grupos',
            '/mis-reportes',
        ]);
        expect(rutas(usuarioDePrueba('Auxiliar'))).toEqual(['/ingreso', '/mi-aula']);
        expect(rutas(null)).toEqual([]);
    });

    it('el administrador de inicio no tiene pantallas de docencia', () => {
        expect(PERMISOS.Administrador).toHaveLength(7);
        expect(
            vistasDe(usuarioDePrueba('Administrador')).filter((v) => v.grupo === 'Docencia')
        ).toEqual([]);
    });

    it('con todo el catálogo se ven todas menos las de quien solo atiende un aula', () => {
        expect(rutas(conTodo())).toEqual(VISTAS.filter((v) => !v.sin).map((v) => v.ruta));
    });

    it('no depende del nombre del rol: un rol creado ve lo que sus permisos dicen', () => {
        const cuenta = usuarioDePrueba('Docente', {
            rol: 'Coordinación',
            permisos: ['examenes', 'periodo_oferta'],
        });
        expect(rutas(cuenta)).toEqual([
            '/periodo',
            '/oferta',
            '/examenes',
            '/examenes/nuevo',
            '/examenes/detalle',
        ]);
        expect(entradaDe(cuenta)).toBe('/periodo');
    });

    it('el control y el seguimiento cambian de juego según se tenga `examenes`', () => {
        const permisos = ['punto_control', 'seguimiento_vivo'];
        const atiende = usuarioDePrueba('Auxiliar', { rol: 'Apoyo', permisos });
        const toma = usuarioDePrueba('Docente', {
            rol: 'Titular',
            permisos: [...permisos, 'examenes'],
        });
        expect(rutas(atiende)).toEqual(['/ingreso', '/mi-aula']);
        expect(rutas(toma)).toEqual([
            '/examenes',
            '/examenes/nuevo',
            '/examenes/detalle',
            '/en-curso',
            '/en-curso/aula',
            '/control',
        ]);
    });

    it('da la gemela de una vista del otro juego si la cuenta la tiene', () => {
        const docente = usuarioDePrueba('Docente');
        const auxiliar = usuarioDePrueba('Auxiliar');
        expect(gemelaDe(docente, '/ingreso')).toBe('/control');
        expect(gemelaDe(docente, '/mi-aula')).toBe('/en-curso');
        expect(gemelaDe(auxiliar, '/control')).toBe('/ingreso');
        expect(gemelaDe(auxiliar, '/en-curso')).toBe('/mi-aula');
        expect(gemelaDe(auxiliar, '/en-curso/aula')).toBe('/mi-aula');
        expect(gemelaDe(auxiliar, '/examenes')).toBeNull();
        expect(gemelaDe(usuarioDePrueba('Administrador'), '/control')).toBeNull();
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
        expect(destinoDe(usuarioDePrueba('Docente', { debe_cambiar_contrasena: true }))).toBe(
            '/primer-ingreso'
        );
        expect(destinoDe(usuarioDePrueba('Docente'))).toBe('/examenes');
        expect(destinoDe(usuarioDePrueba('Auxiliar'))).toBe('/ingreso');
        expect(destinoDe(usuarioDePrueba('Docente', { permisos: [] }))).toBe('/sin-acceso');
    });

    it('encuentra la vista de una dirección, la más específica primero', () => {
        const vistas = vistasDe(usuarioDePrueba('Docente'));
        expect(vistaDeRuta(vistas, '/en-curso/aula').padre).toBe('/en-curso');
        expect(vistaDeRuta(vistas, '/en-curso').ruta).toBe('/en-curso');
        expect(vistaDeRuta(vistas, '/examenes/nuevo').padre).toBe('/examenes');
        expect(vistaDeRuta(vistas, '/periodo')).toBeNull();
    });
});

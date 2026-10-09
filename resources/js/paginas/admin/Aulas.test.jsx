import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Aulas, { pisosDe } from './Aulas';
import { api } from '../../api/cliente';
import { olvidarEdificios } from '../../api/usarEdificios';
import RutaProtegida from '../../sesion/RutaProtegida';
import {
    errorHttp,
    renderConSesion,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
} from '../../test/apoyo';

vi.mock('../../api/cliente');

const CAJA = { lon: [-66.15, -66.14], lat: [-17.4, -17.39] };

function poligono(i) {
    const lon = -66.149 + i * 0.0006;
    return [
        [lon, -17.399],
        [lon + 0.0004, -17.399],
        [lon + 0.0004, -17.398],
        [lon, -17.398],
    ];
}

// La forma de GET /api/edificios: 14 edificios (dos páginas de 12).
const EDIFICIOS = [
    {
        id: 3,
        clave: 'fcyt_3',
        facultad: 'FCyT',
        nombre: 'Edificio Académico 2',
        poligono: poligono(0),
        centro: [-66.1488, -17.3985],
        aulas: ['617', '624', '690A', '691B'],
        // El servidor ordena los pisos por nombre: la planta baja llega al final.
        pisos: [
            { nombre: '1° Piso', aulas: ['617', '624'] },
            { nombre: 'Planta Baja', aulas: ['690A', '691B'] },
        ],
    },
    {
        id: 12,
        clave: 'fcyt_12',
        facultad: 'FCyT',
        nombre: 'Biblioteca',
        poligono: poligono(1),
        centro: [-66.1482, -17.3985],
        aulas: [],
        pisos: [],
    },
    {
        id: 9,
        clave: 'fce_9',
        facultad: 'FCE',
        nombre: 'Bloque Central',
        poligono: poligono(2),
        centro: [-66.1476, -17.3985],
        aulas: ['E101', 'E102'],
        pisos: [],
    },
    ...Array.from({ length: 11 }, (_, i) => ({
        id: 20 + i,
        clave: `fhce_${20 + i}`,
        facultad: 'FHCE',
        nombre: `Pabellón ${i + 1}`,
        poligono: poligono(3 + i),
        centro: [-66.147 + i * 0.0006, -17.3985],
        aulas: [`H${i + 1}`],
        pisos: [],
    })),
];

// La forma de GET /api/aulas: 19 aulas, dos de ellas sin edificio. Van en
// desorden para comprobar que la página las ordena.
const AULAS = [
    ...EDIFICIOS.flatMap((e) =>
        e.aulas.map((nombre) => ({
            nombre,
            edificio_id: e.id,
            edificio: e.nombre,
            piso: e.pisos.find((p) => p.aulas.includes(nombre))?.nombre ?? null,
            facultad: e.facultad,
        }))
    ),
    { nombre: 'A706', edificio_id: null, edificio: null, piso: null, facultad: 'FACH' },
    { nombre: '003', edificio_id: null, edificio: null, piso: null, facultad: 'FHCE' },
]
    .map((a, i) => ({ id: 100 + i, ...a }))
    .reverse();

const SIN_DATO = new RegExp(['cap', 'acidad'].join(''), 'i');

function simular(cambios = {}) {
    simularApi(api, {
        'GET /edificios': { data: EDIFICIOS, meta: { caja: CAJA } },
        'GET /aulas': { data: AULAS },
        ...cambios,
    });
}

function montar(opciones = {}) {
    return renderConSesion(<Aulas />, {
        usuario: usuarioDePrueba('Administrador'),
        ruta: '/aulas',
        ...opciones,
    });
}

async function montarCargada(opciones) {
    const resultado = montar(opciones);
    await screen.findByText('Campus completo · 14 edificios · 19 aulas');
    return resultado;
}

const filtro = () => screen.getByRole('group', { name: 'Filtrar por facultad' });
const listaEdificios = () => screen.getByRole('list', { name: 'Edificios' });
const listaAulas = () => screen.getByRole('list', { name: 'Aulas' });
const filaEdificio = (nombre) =>
    within(listaEdificios()).getByRole('button', { name: new RegExp(nombre) });
const pestana = (nombre) => screen.getByRole('tab', { name: new RegExp(`^${nombre}`) });
const buscar = (texto) =>
    fireEvent.change(screen.getByRole('searchbox', { name: 'Buscar edificio o aula' }), {
        target: { value: texto },
    });
const detalle = () => screen.queryByTestId('detalle-edificio');
const nombres = (lista) =>
    within(lista)
        .getAllByRole('listitem')
        .map((li) => li.textContent);
const poligonos = (container) => [...container.querySelectorAll('polygon')];
const resaltados = (container) =>
    poligonos(container).filter((p) => p.getAttribute('stroke') === '#0f172a');

function enMovil(coincide) {
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => ({ matches: coincide }))
    );
}

// El desplazamiento espera al siguiente cuadro, cuando el detalle ya está
// dibujado: la prueba lo adelanta con `pintar()`.
let cuadros = [];
const pintar = () => cuadros.splice(0).forEach((fn) => fn());

beforeEach(() => {
    olvidarEdificios();
    simular();
    cuadros = [];
    vi.stubGlobal('requestAnimationFrame', (fn) => cuadros.push(fn));
    Element.prototype.scrollIntoView = vi.fn();
});

afterEach(() => {
    vi.unstubAllGlobals();
    delete Element.prototype.scrollIntoView;
});

describe('Aulas y mapa', () => {
    it('muestra el campus completo: mapa con el color de cada facultad, leyenda y totales (criterio 1)', async () => {
        const diferida = respuestaDiferida();
        simular({ 'GET /aulas': diferida.promesa });
        const { container } = montar();

        expect(screen.getByRole('status', { name: 'Cargando' })).toBeInTheDocument();
        diferida.resolver({ data: AULAS });

        expect(
            await screen.findByText('Campus completo · 14 edificios · 19 aulas')
        ).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Aulas y mapa' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Campus' })).toBeInTheDocument();
        expect(
            screen.getByRole('img', { name: 'Mapa de edificios del campus' })
        ).toBeInTheDocument();

        const dibujados = poligonos(container);
        expect(dibujados).toHaveLength(14);
        expect(dibujados[0]).toHaveAttribute('fill', '#B90813');
        expect(dibujados[2]).toHaveAttribute('fill', '#107C41');
        expect(dibujados[3]).toHaveAttribute('fill', '#ea580c');

        const leyenda = nombres(screen.getByRole('list', { name: 'Facultades' }));
        expect(leyenda).toHaveLength(4);
        expect(leyenda[0]).toBe('FCyT · Ciencias y Tecnología');

        expect(api.get).toHaveBeenCalledWith('/edificios');
        expect(api.get).toHaveBeenCalledWith('/aulas', { params: {} });
    });

    it('al elegir una facultad limita el mapa, las listas y los totales, con las aulas de cada opción (criterio 2)', async () => {
        const { container } = await montarCargada();

        const conteos = within(filtro())
            .getAllByRole('button')
            .map((b) => b.textContent);
        expect(conteos).toEqual(['Todas19', 'FCyT4', 'FCE2', 'FHCE12', 'FACH1']);

        fireEvent.click(within(filtro()).getByRole('button', { name: /FCyT/ }));

        expect(
            screen.getByText('Ciencias y Tecnología · 2 edificios · 4 aulas')
        ).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Edificios de FCyT' })).toBeInTheDocument();
        expect(poligonos(container)).toHaveLength(2);
        expect(nombres(screen.getByRole('list', { name: 'Facultades' }))).toHaveLength(1);
        expect(pestana('Edificios')).toHaveTextContent('Edificios 2');
        expect(pestana('Aulas')).toHaveTextContent('Aulas 4');
        expect(nombres(listaEdificios())).toEqual([
            'Edificio Académico 2FCyT4 aulas',
            'BibliotecaFCyT0 aulas',
        ]);

        fireEvent.click(pestana('Aulas'));
        expect(within(listaAulas()).getAllByRole('listitem')).toHaveLength(4);

        fireEvent.click(within(filtro()).getByRole('button', { name: /Todas/ }));
        expect(poligonos(container)).toHaveLength(14);
    });

    it('lista los edificios con su facultad y su cantidad de aulas, de a 12 por página (criterio 3)', async () => {
        await montarCargada();

        expect(pestana('Edificios')).toHaveAttribute('aria-selected', 'true');
        expect(pestana('Edificios')).toHaveTextContent('Edificios 14');
        const filas = nombres(listaEdificios());
        expect(filas).toHaveLength(12);
        expect(filas[0]).toBe('Edificio Académico 2FCyT4 aulas');
        expect(filas[2]).toBe('Bloque CentralFCE2 aulas');
        expect(filas[3]).toBe('Pabellón 1FHCE1 aula');
        expect(screen.getByText('1–12 de 14 edificios')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        expect(nombres(listaEdificios())).toEqual([
            'Pabellón 10FHCE1 aula',
            'Pabellón 11FHCE1 aula',
        ]);
        expect(screen.getByText('13–14 de 14 edificios')).toBeInTheDocument();
    });

    it('lista las aulas con edificio, facultad y piso, en orden natural y de a 12 por página (criterios 4 y 10)', async () => {
        await montarCargada();
        fireEvent.click(pestana('Aulas'));

        expect(pestana('Aulas')).toHaveAttribute('aria-selected', 'true');
        expect(nombres(listaAulas())).toEqual([
            '003Sin edificioFHCE',
            '617Edificio Académico 2FCyT · 1° Piso',
            '624Edificio Académico 2FCyT · 1° Piso',
            '690AEdificio Académico 2FCyT · Planta Baja',
            '691BEdificio Académico 2FCyT · Planta Baja',
            'A706Sin edificioFACH',
            'E101Bloque CentralFCE',
            'E102Bloque CentralFCE',
            'H1Pabellón 1FHCE',
            'H2Pabellón 2FHCE',
            'H3Pabellón 3FHCE',
            'H4Pabellón 4FHCE',
        ]);
        expect(screen.getByText('1–12 de 19 aulas')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        expect(nombres(listaAulas()).map((t) => t.match(/^H\d+/)[0])).toEqual([
            'H5',
            'H6',
            'H7',
            'H8',
            'H9',
            'H10',
            'H11',
        ]);
        expect(document.body.textContent).not.toMatch(SIN_DATO);
    });

    it('un aula sin edificio figura en la lista, no se puede elegir y no se dibuja en el mapa', async () => {
        const { container } = await montarCargada();
        fireEvent.click(pestana('Aulas'));

        const fila = within(listaAulas()).getByText('003').closest('li');
        expect(fila).toHaveTextContent('Sin edificio');
        expect(within(fila).queryByRole('button')).toBeNull();
        expect(poligonos(container)).toHaveLength(EDIFICIOS.length);
    });

    it('busca por edificio o por aula en las dos listas e indica las aulas que coinciden (criterio 5)', async () => {
        await montarCargada();

        buscar('e10');
        expect(nombres(listaEdificios())).toEqual(['Bloque CentralE101 · E1022 aulas']);
        expect(pestana('Edificios')).toHaveTextContent('Edificios 1');
        expect(pestana('Aulas')).toHaveTextContent('Aulas 2');
        fireEvent.click(pestana('Aulas'));
        expect(nombres(listaAulas())).toEqual(['E101Bloque CentralFCE', 'E102Bloque CentralFCE']);

        // Por el nombre del edificio, sin distinguir tildes ni mayúsculas.
        buscar('  ACADEMICO ');
        expect(within(listaAulas()).getAllByRole('listitem')).toHaveLength(4);
        fireEvent.click(pestana('Edificios'));
        expect(nombres(listaEdificios())).toEqual(['Edificio Académico 2FCyT4 aulas']);

        buscar('zzz');
        expect(nombres(listaEdificios())).toEqual(['Sin resultados']);
        expect(screen.queryByRole('navigation', { name: 'Paginación' })).toBeNull();
        fireEvent.click(pestana('Aulas'));
        expect(nombres(listaAulas())).toEqual(['Sin resultados']);
    });

    it('la búsqueda vuelve a la primera página', async () => {
        await montarCargada();
        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        expect(screen.getByText('13–14 de 14 edificios')).toBeInTheDocument();

        buscar('pabellón');
        expect(nombres(listaEdificios())[0]).toBe('Pabellón 1FHCE1 aula');
        expect(screen.getByText('11 edificios')).toBeInTheDocument();
    });

    it('elegir un edificio en la lista o en el mapa lo resalta y abre su detalle (criterio 6)', async () => {
        const { container } = await montarCargada();
        expect(detalle()).toBeNull();
        expect(resaltados(container)).toHaveLength(0);

        fireEvent.click(filaEdificio('Bloque Central'));

        expect(filaEdificio('Bloque Central')).toHaveAttribute('aria-pressed', 'true');
        expect(resaltados(container)).toHaveLength(1);
        expect(resaltados(container)[0]).toBe(poligonos(container)[2]);
        expect(
            within(detalle()).getByRole('heading', { name: 'Bloque Central' })
        ).toBeInTheDocument();
        expect(detalle()).toHaveTextContent('FCE');
        expect(detalle()).toHaveTextContent('2 aulas');

        // En el mapa.
        fireEvent.click(poligonos(container)[0]);

        expect(resaltados(container)[0]).toBe(poligonos(container)[0]);
        expect(
            within(detalle()).getByRole('heading', { name: 'Edificio Académico 2' })
        ).toBeInTheDocument();
        expect(detalle()).toHaveTextContent('FCyT');
        expect(detalle()).toHaveTextContent('4 aulas');
        expect(filaEdificio('Edificio Académico 2')).toHaveAttribute('aria-pressed', 'true');
        expect(filaEdificio('Bloque Central')).toHaveAttribute('aria-pressed', 'false');
    });

    it('agrupa las aulas del edificio por piso, en un solo grupo si no se conocen, o «Sin aulas» (criterio 7)', async () => {
        await montarCargada();

        fireEvent.click(filaEdificio('Edificio Académico 2'));
        expect(nombres(within(detalle()).getByRole('list', { name: 'Planta Baja' }))).toEqual([
            '690A',
            '691B',
        ]);
        expect(nombres(within(detalle()).getByRole('list', { name: '1° Piso' }))).toEqual([
            '617',
            '624',
        ]);
        const grupos = within(detalle())
            .getAllByRole('list')
            .map((l) => l.getAttribute('aria-label'));
        expect(grupos).toEqual(['Planta Baja', '1° Piso']);

        fireEvent.click(filaEdificio('Bloque Central'));
        expect(within(detalle()).getAllByRole('list')).toHaveLength(1);
        expect(nombres(within(detalle()).getByRole('list', { name: 'Aulas' }))).toEqual([
            'E101',
            'E102',
        ]);
        expect(within(detalle()).queryByText(/piso|planta/i)).toBeNull();

        fireEvent.click(filaEdificio('Biblioteca'));
        expect(detalle()).toHaveTextContent('0 aulas');
        expect(within(detalle()).getByText('Sin aulas')).toBeInTheDocument();
        expect(within(detalle()).queryByRole('list')).toBeNull();
    });

    it('pisosDe deja en un grupo aparte las aulas sin piso de un edificio con pisos', () => {
        expect(
            pisosDe({
                aulas: ['1', '2', '3'],
                pisos: [
                    { nombre: '2° Piso', aulas: ['2'] },
                    { nombre: 'Planta Baja', aulas: ['1'] },
                    { nombre: '5° Piso', aulas: [] },
                ],
            })
        ).toEqual([
            { nombre: 'Planta Baja', aulas: ['1'] },
            { nombre: '2° Piso', aulas: ['2'] },
            { nombre: null, aulas: ['3'] },
        ]);
        expect(pisosDe({ aulas: [], pisos: [] })).toEqual([]);
    });

    it('elegir un aula en la lista selecciona su edificio y la deja marcada (criterio 8)', async () => {
        const { container } = await montarCargada();
        fireEvent.click(pestana('Aulas'));

        fireEvent.click(within(listaAulas()).getByRole('button', { name: /^624/ }));

        expect(
            within(detalle()).getByRole('heading', { name: 'Edificio Académico 2' })
        ).toBeInTheDocument();
        expect(resaltados(container)[0]).toBe(poligonos(container)[0]);
        expect(within(detalle()).getByText('624')).toHaveAttribute('aria-current', 'true');
        expect(within(detalle()).getByText('617')).not.toHaveAttribute('aria-current');
        expect(within(listaAulas()).getByRole('button', { name: /^624/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        expect(within(listaAulas()).getByRole('button', { name: /^617/ })).toHaveAttribute(
            'aria-pressed',
            'false'
        );
    });

    it('«Quitar selección», volver a pulsar el edificio o cambiar de facultad cierran el detalle (criterio 9)', async () => {
        const { container } = await montarCargada();

        fireEvent.click(filaEdificio('Bloque Central'));
        expect(detalle()).not.toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Quitar selección' }));
        expect(detalle()).toBeNull();
        expect(resaltados(container)).toHaveLength(0);
        expect(filaEdificio('Bloque Central')).toHaveAttribute('aria-pressed', 'false');

        fireEvent.click(filaEdificio('Bloque Central'));
        expect(detalle()).not.toBeNull();
        fireEvent.click(filaEdificio('Bloque Central'));
        expect(detalle()).toBeNull();
        expect(resaltados(container)).toHaveLength(0);

        fireEvent.click(filaEdificio('Bloque Central'));
        expect(detalle()).not.toBeNull();
        fireEvent.click(within(filtro()).getByRole('button', { name: /FCE/ }));
        expect(detalle()).toBeNull();
        expect(resaltados(container)).toHaveLength(0);
    });

    it('es de consulta: sin acciones para crear, editar ni eliminar, y sin escrituras (criterios 10 y 11)', async () => {
        await montarCargada();
        fireEvent.click(filaEdificio('Edificio Académico 2'));
        fireEvent.click(pestana('Aulas'));

        expect(
            screen.queryByRole('button', {
                name: /nuev|crear|agregar|registrar|editar|modificar|eliminar|borrar|guardar/i,
            })
        ).toBeNull();
        expect(screen.queryByRole('link')).toBeNull();
        expect(document.querySelector('form')).toBeNull();
        expect(document.body.textContent).not.toMatch(SIN_DATO);
        ['post', 'put', 'patch', 'delete'].forEach((metodo) =>
            expect(api[metodo]).not.toHaveBeenCalled()
        );
    });

    it('al volver a abrir la vista pide otra vez las aulas (criterio 11)', async () => {
        const primera = await montarCargada();
        primera.unmount();

        simular({ 'GET /aulas': { data: AULAS.filter((a) => a.nombre !== 'A706') } });
        montar();

        expect(
            await screen.findByText('Campus completo · 14 edificios · 18 aulas')
        ).toBeInTheDocument();
    });

    it('con el teclado, la fila del edificio hace lo mismo que el mapa (criterio 12)', async () => {
        const { container } = await montarCargada();

        // Botón nativo: recibe el foco con Tab y se activa con Enter y con espacio.
        const fila = filaEdificio('Edificio Académico 2');
        expect(fila.tagName).toBe('BUTTON');
        expect(fila).toHaveAttribute('type', 'button');
        expect(fila).not.toHaveAttribute('tabindex');
        fila.focus();
        expect(document.activeElement).toBe(fila);

        fireEvent.click(fila);
        const porLista = {
            titulo: within(detalle()).getByRole('heading').textContent,
            texto: detalle().textContent,
            resaltado: poligonos(container).indexOf(resaltados(container)[0]),
        };

        fireEvent.click(screen.getByRole('button', { name: 'Quitar selección' }));
        fireEvent.click(poligonos(container)[0]);

        expect({
            titulo: within(detalle()).getByRole('heading').textContent,
            texto: detalle().textContent,
            resaltado: poligonos(container).indexOf(resaltados(container)[0]),
        }).toEqual(porLista);

        // Las pestañas y las filas de aulas también son botones.
        fireEvent.click(pestana('Aulas'));
        expect(within(listaAulas()).getByRole('button', { name: /^617/ }).tagName).toBe('BUTTON');
    });

    it('sin el permiso de aulas y docentes recibe una negativa explícita y no consulta (criterio 13)', () => {
        renderConSesion(
            <RutaProtegida ruta="/aulas">
                <Aulas />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Docente'), ruta: '/aulas' }
        );

        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Aulas y mapa' })).toBeNull();
        expect(api.get).not.toHaveBeenCalled();
    });

    it('en móvil, elegir un edificio o un aula en la lista desplaza hasta el detalle (criterio 14)', async () => {
        enMovil(true);
        const { container } = await montarCargada();

        fireEvent.click(filaEdificio('Bloque Central'));
        pintar();
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledTimes(1);
        expect(Element.prototype.scrollIntoView.mock.instances[0]).toBe(detalle());
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({
            behavior: 'smooth',
            block: 'start',
        });

        // Al quitar la selección desde la fila no hay a dónde ir.
        fireEvent.click(filaEdificio('Bloque Central'));
        pintar();
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledTimes(1);

        // El mapa está encima del detalle: no desplaza.
        fireEvent.click(poligonos(container)[0]);
        pintar();
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledTimes(1);

        fireEvent.click(pestana('Aulas'));
        fireEvent.click(within(listaAulas()).getByRole('button', { name: /^E101/ }));
        pintar();
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledTimes(2);
    });

    it('en escritorio no desplaza: el detalle queda junto a la lista (criterio 14)', async () => {
        enMovil(false);
        await montarCargada();

        fireEvent.click(filaEdificio('Bloque Central'));
        pintar();

        expect(detalle()).not.toBeNull();
        expect(Element.prototype.scrollIntoView).not.toHaveBeenCalled();
    });

    it('si falla la carga muestra el error y «Reintentar» vuelve a pedir', async () => {
        simular({ 'GET /edificios': errorHttp(500) });
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar');
        expect(screen.queryByRole('img')).toBeNull();

        simular();
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

        expect(
            await screen.findByText('Campus completo · 14 edificios · 19 aulas')
        ).toBeInTheDocument();
        await waitFor(() => expect(screen.queryByRole('alert')).toBeNull());
    });

    it('sin edificios ni aulas importados muestra los totales en cero y «Sin resultados»', async () => {
        simular({
            'GET /edificios': { data: [], meta: { caja: null } },
            'GET /aulas': { data: [] },
        });
        const { container } = montar();

        expect(
            await screen.findByText('Campus completo · 0 edificios · 0 aulas')
        ).toBeInTheDocument();
        expect(poligonos(container)).toHaveLength(0);
        expect(nombres(listaEdificios())).toEqual(['Sin resultados']);
    });
});

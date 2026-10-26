// Los tipos de incidencia del aula con las etiquetas del formulario de la
// puerta (HU-19). Los códigos son los que guarda el servidor.
export const TIPOS_INCIDENCIA = {
    INCIDENTE_GENERAL: 'Incidente general',
    EXCEPCION_IDENTIFICACION: 'Excepción: problema de identificación',
    EXCEPCION_CAMBIO_AULA: 'Excepción: cambio de aula',
    EXCEPCION_NO_HABILITADO: 'Excepción: estudiante no habilitado',
    DOCUMENTO_OBSERVADO: 'Documento observado',
    CONDUCTA: 'Conducta',
    OTRO: 'Otro',
};

// Los que se reportan como incidencia; las excepciones se registran desde
// el veredicto de la puerta.
export const TIPOS_COMUNES = ['INCIDENTE_GENERAL', 'DOCUMENTO_OBSERVADO', 'CONDUCTA', 'OTRO'];

// Códigos que ya pueden estar guardados aunque no se elijan en el formulario.
const OTROS_TIPOS = {
    OTRA_NOVEDAD: 'Otra novedad',
    SUPLANTACION: 'Suplantación',
};

// Palabras que el respaldo escribe con tilde.
const TILDES = {
    excepcion: 'excepción',
    identificacion: 'identificación',
    suplantacion: 'suplantación',
    expulsion: 'expulsión',
    informacion: 'información',
    observacion: 'observación',
    habilitacion: 'habilitación',
    telefono: 'teléfono',
    electronico: 'electrónico',
    academico: 'académico',
};

// 'EXCEPCION_CAMBIO_AULA' → 'Excepción: cambio de aula'. Un código no
// previsto se muestra sin guiones bajos, con mayúscula y tildes.
export function tipoIncidenciaLegible(tipo) {
    const codigo = String(tipo ?? '').trim();
    const conocida = TIPOS_INCIDENCIA[codigo] ?? OTROS_TIPOS[codigo];
    if (conocida) return conocida;

    const texto = codigo
        .toLocaleLowerCase('es')
        .split(/[_\s]+/)
        .filter(Boolean)
        .map((palabra) => TILDES[palabra] ?? palabra)
        .join(' ');

    return texto ? texto[0].toLocaleUpperCase('es') + texto.slice(1) : 'Incidencia';
}

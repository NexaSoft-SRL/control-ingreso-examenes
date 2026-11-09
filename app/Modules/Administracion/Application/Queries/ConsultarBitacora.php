<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;
use App\Modules\Administracion\Application\DTOs\PaginaBitacoraData;

/**
 * La bitacora se consulta, no se edita. La clave de cada operacion
 * (`entidad.verbo`) se muestra con un texto legible.
 */
final readonly class ConsultarBitacora
{
    /**
     * Clave de operacion => como se lee en la pantalla. Una clave que no
     * figure aqui se muestra tal cual.
     *
     * @var array<string, string>
     */
    public const TEXTOS = [
        // Acceso y cuentas.
        'sesion.iniciar' => 'Inició sesión',
        'sesion.cerrar' => 'Cerró sesión',
        'sesion.intento_fallido' => 'Intento de ingreso fallido',
        'rol.crear' => 'Creó el rol',
        'rol.modificar' => 'Modificó el rol',
        'rol.eliminar' => 'Eliminó el rol',
        'usuario.crear' => 'Creó el usuario',
        'usuario.actualizar' => 'Modificó el usuario',
        'usuario.bloquear' => 'Bloqueó el usuario',
        'usuario.desbloquear' => 'Desbloqueó el usuario',
        'usuario.restablecer_temporal' => 'Restableció la contraseña',
        'usuario.cambiar_contrasena' => 'Cambió su contraseña',
        'usuario.solicitar_restablecimiento' => 'Pidió recuperar su contraseña',
        'usuario.restablecer_contrasena' => 'Recuperó su contraseña',
        'respaldo.generar' => 'Generó el respaldo',
        'respaldo.fallar' => 'Falló el respaldo',
        'respaldo.restaurar' => 'Restauró el respaldo',
        // Oferta, aulas y docentes.
        'periodo.ajustar' => 'Ajustó el período',
        'periodo.detectar' => 'Detectó los períodos',
        'oferta.importar' => 'Importó la oferta',
        'ubicaciones.importar' => 'Importó las aulas',
        'docente.activar_cuenta' => 'Activó la cuenta',
        // Padron e inscripciones.
        'inscritos.cargar' => 'Cargó la lista de inscritos',
        'inscritos.agregar' => 'Agregó al estudiante',
        'inscritos.quitar' => 'Quitó al estudiante del grupo',
        'inscripciones.cargar' => 'Cargó inscripciones',
        'conflicto.resolver' => 'Resolvió el conflicto',
        'padron.resolver_conflicto' => 'Resolvió el conflicto',
        'estudiante.editar' => 'Corrigió los datos del estudiante',
        'estudiante.foto' => 'El estudiante subió su foto',
        'estudiante.foto.reemplazar' => 'Cambió la foto del estudiante',
        'estudiante.foto_habilitar' => 'Habilitó el cambio de foto',
        // Ya no lo fija el docente; queda para leer los registros de antes.
        'grupo.plazo_foto' => 'Fijó el plazo de fotos del grupo',
        'rol.restablecer' => 'Restableció los roles base',
        'acceso_aula.solicitar' => 'Pidió controlar con código de aula',
        'acceso_aula.autorizar' => 'Autorizó un acceso con código',
        'acceso_aula.rechazar' => 'Rechazó un acceso con código',
        'acceso_aula.cortar' => 'Cortó un acceso con código',
        'acceso_aula.trasladar' => 'Movió un acceso con código de aula',
        'examen.mover_personal' => 'Movió a quien atiende un aula',
        'habilitacion.mover_aula' => 'Movió estudiantes de aula',
        'estudiante.plazo_fotos' => 'Fijó el plazo general de fotos',
        // Examenes.
        'examen.registrar' => 'Registró el examen',
        'examen.actualizar' => 'Modificó el examen',
        'examen.modificar' => 'Modificó el examen',
        'examen.finalizar' => 'Finalizó el examen',
        'examen.eliminar' => 'Eliminó el examen',
        'norma.plantilla_crear' => 'Creó la plantilla de norma',
        'norma.plantilla_editar' => 'Modificó la plantilla de norma',
        'norma.plantilla_quitar' => 'Quitó la plantilla de norma',
        'auxiliar.registrar' => 'Registró al auxiliar',
        'auxiliar.quitar' => 'Quitó al auxiliar',
        'auxiliar.asignar' => 'Asignó al auxiliar',
        'examen.asignar_auxiliar' => 'Asignó al auxiliar',
        'codigo_aula.generar' => 'Generó el código de aula',
        'codigo_aula.regenerar' => 'Regeneró el código de aula',
        // Habilitacion.
        'habilitacion.habilitar' => 'Habilitó al estudiante',
        'habilitacion.inhabilitar' => 'Inhabilitó al estudiante',
        'habilitacion.sin_foto' => 'Inhabilitados por no subir su foto',
        'habilitacion.repartir' => 'Repartió por aula',
        // Codigos QR e ingreso.
        'qr.emitir' => 'Emitió los códigos QR',
        'qr.enviar' => 'Envió los códigos QR',
        'aviso.enviar' => 'Envió avisos del examen',
        'acceso_aula.abrir' => 'Entró con código de aula',
        'ingreso.registrar' => 'Registró el ingreso',
        'ingreso.excepcion' => 'Registró una excepción',
        'ingreso.expulsar' => 'Expulsó al estudiante',
        'incidencia.reportar' => 'Reportó una incidencia',
        'incidencia.registrar' => 'Reportó una incidencia',
        'incidencia.expulsion' => 'Expulsó al estudiante',
        'incidencia.atender' => 'Atendió la incidencia',
        'incidencia.corregir' => 'Corrigió la incidencia',
        'qr.hoja' => 'Imprimió las credenciales',
    ];

    public function __construct(
        private ConsultaBitacoraGateway $gateway,
    ) {}

    public function execute(ConsultarBitacoraData $filtros): PaginaBitacoraData
    {
        return $this->gateway->consultar($filtros);
    }

    public static function texto(string $operacion): string
    {
        return self::TEXTOS[$operacion] ?? $operacion;
    }

    /**
     * Las operaciones presentes en la bitacora, con su texto y en el orden
     * en que se leen en el desplegable.
     *
     * @param  list<string>  $claves
     * @return list<array{clave: string, texto: string}>
     */
    public function opciones(array $claves): array
    {
        $opciones = array_map(
            static fn (string $clave): array => [
                'clave' => $clave,
                'texto' => self::texto($clave),
            ],
            $claves,
        );

        usort(
            $opciones,
            static fn (array $una, array $otra): int => strcmp($una['texto'], $otra['texto'])
                ?: strcmp($una['clave'], $otra['clave']),
        );

        return $opciones;
    }
}

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    estado BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE permisos (
    id BIGSERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT
);


CREATE TABLE usuarios (
    id BIGSERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(120) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol_id BIGINT NOT NULL,

    CONSTRAINT fk_usuario_rol
    FOREIGN KEY(rol_id)
    REFERENCES roles(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE TABLE rol_permiso (
    id BIGSERIAL PRIMARY KEY,

    rol_id BIGINT NOT NULL,
    permiso_id BIGINT NOT NULL,

    CONSTRAINT fk_rol_permiso_rol
    FOREIGN KEY (rol_id)
    REFERENCES roles(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

    CONSTRAINT fk_rol_permiso_permiso
    FOREIGN KEY (permiso_id)
    REFERENCES permisos(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

    CONSTRAINT uq_rol_permiso
    UNIQUE (rol_id, permiso_id)
);
CREATE TABLE facultades (
    id BIGSERIAL PRIMARY KEY,

    nombre VARCHAR(150) NOT NULL UNIQUE,
    codigo VARCHAR(20) UNIQUE,

    estado BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE carreras (
    id BIGSERIAL PRIMARY KEY,

    facultad_id BIGINT NOT NULL,

    nombre VARCHAR(150) NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,

    estado BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_carreras_facultad
    FOREIGN KEY (facultad_id)
    REFERENCES facultades(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE TABLE docentes (
    id BIGSERIAL PRIMARY KEY,

    usuario_id BIGINT UNIQUE,

    codigo_docente VARCHAR(50) NOT NULL UNIQUE,

    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,

    correo VARCHAR(150) UNIQUE,

    telefono VARCHAR(30),

    estado BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_docentes_usuario
    FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);
CREATE TABLE asignaturas (
    id BIGSERIAL PRIMARY KEY,

    carrera_id BIGINT NOT NULL,

    docente_id BIGINT,

    codigo VARCHAR(30) NOT NULL UNIQUE,

    nombre VARCHAR(150) NOT NULL,

    semestre VARCHAR(20),

    descripcion TEXT,

    estado BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_asignaturas_carrera
    FOREIGN KEY (carrera_id)
    REFERENCES carreras(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

    CONSTRAINT fk_asignaturas_docente
    FOREIGN KEY (docente_id)
    REFERENCES docentes(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);
CREATE TABLE estudiantes (
    id BIGSERIAL PRIMARY KEY,

    carrera_id BIGINT NOT NULL,

    codigo_sis VARCHAR(30) NOT NULL UNIQUE,

    ci VARCHAR(30) NOT NULL UNIQUE,

    nombres VARCHAR(100) NOT NULL,

    apellidos VARCHAR(100) NOT NULL,

    correo VARCHAR(150) UNIQUE,

    telefono VARCHAR(30),

    estado VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_estado_estudiante
    CHECK (estado IN ('ACTIVO','INACTIVO')),

    CONSTRAINT fk_estudiante_carrera
    FOREIGN KEY (carrera_id)
    REFERENCES carreras(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE TABLE cargas_estudiantes (
    id BIGSERIAL PRIMARY KEY,

    usuario_id BIGINT NOT NULL,

    nombre_archivo VARCHAR(255) NOT NULL,

    tipo_archivo VARCHAR(20) NOT NULL,

    cantidad_registros INTEGER NOT NULL DEFAULT 0,

    registros_correctos INTEGER NOT NULL DEFAULT 0,

    registros_rechazados INTEGER NOT NULL DEFAULT 0,

    estado VARCHAR(20) NOT NULL DEFAULT 'PROCESANDO',

    fecha_carga TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT chk_estado_carga
    CHECK (
        estado IN (
            'PROCESANDO',
            'COMPLETADO',
            'ERROR'
        )
    ),

    CONSTRAINT fk_carga_usuario
    FOREIGN KEY(usuario_id)
    REFERENCES usuarios(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE TABLE errores_carga_estudiantes (
    id BIGSERIAL PRIMARY KEY,

    carga_id BIGINT NOT NULL,

    numero_fila INTEGER NOT NULL,

    dato_error TEXT NOT NULL,

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_error_carga
    FOREIGN KEY(carga_id)
    REFERENCES cargas_estudiantes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);
CREATE TABLE intentos_login (
    id BIGSERIAL PRIMARY KEY,

    usuario_id BIGINT NOT NULL,

    fecha_intento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    exitoso BOOLEAN DEFAULT FALSE,

    ip_origen VARCHAR(50),

    CONSTRAINT fk_intento_usuario
    FOREIGN KEY(usuario_id)
    REFERENCES usuarios(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);
CREATE TABLE sesiones_usuario (
    id BIGSERIAL PRIMARY KEY,

    usuario_id BIGINT NOT NULL,

    token VARCHAR(255) NOT NULL UNIQUE,

    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    fecha_fin TIMESTAMP,

    activa BOOLEAN DEFAULT TRUE,

    CONSTRAINT fk_sesion_usuario
    FOREIGN KEY(usuario_id)
    REFERENCES usuarios(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);
CREATE TABLE matriculas_estudiantes (
    id BIGSERIAL PRIMARY KEY,

    estudiante_id BIGINT NOT NULL,

    asignatura_id BIGINT NOT NULL,

    gestion VARCHAR(50),

    estado VARCHAR(20) DEFAULT 'ACTIVO',


    CONSTRAINT fk_matricula_estudiante
    FOREIGN KEY(estudiante_id)
    REFERENCES estudiantes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,


    CONSTRAINT fk_matricula_asignatura
    FOREIGN KEY(asignatura_id)
    REFERENCES asignaturas(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,


    CONSTRAINT uq_matricula
    UNIQUE(estudiante_id, asignatura_id)
);
CREATE TABLE grupos_asignatura (
    id BIGSERIAL PRIMARY KEY,

    asignatura_id BIGINT NOT NULL,

    docente_id BIGINT NOT NULL,

    codigo_grupo VARCHAR(20) NOT NULL,

    cupo INTEGER DEFAULT 0,


    CONSTRAINT fk_grupo_asignatura
    FOREIGN KEY(asignatura_id)
    REFERENCES asignaturas(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,


    CONSTRAINT fk_grupo_docente
    FOREIGN KEY(docente_id)
    REFERENCES docentes(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE TABLE ambientes (
    id BIGSERIAL PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL UNIQUE,

    ubicacion VARCHAR(200),

    capacidad INTEGER NOT NULL,

    estado VARCHAR(20) DEFAULT 'DISPONIBLE',


    CONSTRAINT chk_capacidad_ambiente
    CHECK(capacidad > 0)
);
CREATE TABLE examenes (
    id BIGSERIAL PRIMARY KEY,

    grupo_id BIGINT NOT NULL,

    nombre VARCHAR(150) NOT NULL,

    fecha DATE NOT NULL,

    hora_inicio TIME NOT NULL,

    duracion_minutos INTEGER NOT NULL,


    CONSTRAINT fk_examen_grupo
    FOREIGN KEY(grupo_id)
    REFERENCES grupos_asignatura(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,


    CONSTRAINT chk_duracion_examen
    CHECK(duracion_minutos > 0)
);
CREATE TABLE examen_ambiente (
    id BIGSERIAL PRIMARY KEY,

    examen_id BIGINT NOT NULL,

    ambiente_id BIGINT NOT NULL,


    CONSTRAINT fk_examen_ambiente_examen
    FOREIGN KEY(examen_id)
    REFERENCES examenes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,


    CONSTRAINT fk_examen_ambiente_ambiente
    FOREIGN KEY(ambiente_id)
    REFERENCES ambientes(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,


    CONSTRAINT uq_examen_ambiente
    UNIQUE(examen_id, ambiente_id)
);
CREATE TABLE habilitaciones_examen (
    id BIGSERIAL PRIMARY KEY,

    examen_id BIGINT NOT NULL,

    estudiante_id BIGINT NOT NULL,

    estado VARCHAR(20) DEFAULT 'HABILITADO',

    fecha_habilitacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_habilitacion_examen
    FOREIGN KEY(examen_id)
    REFERENCES examenes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,


    CONSTRAINT fk_habilitacion_estudiante
    FOREIGN KEY(estudiante_id)
    REFERENCES estudiantes(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,


    CONSTRAINT uq_habilitacion
    UNIQUE(examen_id, estudiante_id)
);
CREATE TABLE codigos_qr (
    id BIGSERIAL PRIMARY KEY,

    habilitacion_id BIGINT NOT NULL,

    codigo VARCHAR(255) NOT NULL UNIQUE,

    estado VARCHAR(20) DEFAULT 'GENERADO',

    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_qr_habilitacion
    FOREIGN KEY(habilitacion_id)
    REFERENCES habilitaciones_examen(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);
CREATE TABLE registros_ingreso (
    id BIGSERIAL PRIMARY KEY,

    examen_id BIGINT NOT NULL,

    estudiante_id BIGINT NOT NULL,

    qr_id BIGINT,

    fecha_ingreso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    estado VARCHAR(20) DEFAULT 'VALIDO',


    CONSTRAINT fk_ingreso_examen
    FOREIGN KEY(examen_id)
    REFERENCES examenes(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,


    CONSTRAINT fk_ingreso_estudiante
    FOREIGN KEY(estudiante_id)
    REFERENCES estudiantes(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,


    CONSTRAINT fk_ingreso_qr
    FOREIGN KEY(qr_id)
    REFERENCES codigos_qr(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,


    CONSTRAINT uq_doble_ingreso
    UNIQUE(examen_id, estudiante_id)
);
CREATE TABLE anulaciones_ingreso (
    id BIGSERIAL PRIMARY KEY,

    ingreso_id BIGINT NOT NULL,

    usuario_id BIGINT NOT NULL,

    motivo TEXT NOT NULL,

    fecha_anulacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_anulacion_ingreso
    FOREIGN KEY(ingreso_id)
    REFERENCES registros_ingreso(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,


    CONSTRAINT fk_anulacion_usuario
    FOREIGN KEY(usuario_id)
    REFERENCES usuarios(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE TABLE bitacora_operaciones (
    id BIGSERIAL PRIMARY KEY,

    usuario_id BIGINT,

    operacion VARCHAR(100) NOT NULL,

    tabla_afectada VARCHAR(100),

    registro_id BIGINT,

    descripcion TEXT,

    fecha_operacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_bitacora_usuario
    FOREIGN KEY(usuario_id)
    REFERENCES usuarios(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

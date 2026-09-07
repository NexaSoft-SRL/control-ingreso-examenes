# Arquitectura del sistema

## Estilo arquitectónico

El sistema utiliza un monolito modular.

El servidor se implementa con Laravel y PHP, el cliente con React y la persistencia con PostgreSQL.

La arquitectura interna divide el producto en módulos funcionales con límites explícitos y verificables automáticamente.

## Módulos

El backend contiene siete módulos:

- Estudiantes
- Examenes
- Habilitacion
- Ingreso
- Monitoreo
- Reportes
- Administracion

## Capas

Cada módulo puede contener, cuando sean necesarias:

- Domain
- Application
- Infrastructure
- Http

No se crean carpetas vacías únicamente para representar la arquitectura.

## Dependencias

Direcciones principales:

Http -> Application -> Domain

Infrastructure -> Application -> Domain

Las reglas completas se encuentran en:

docs/architecture/dependency-rules.md

## Cambios arquitectónicos

Las decisiones arquitectónicas se registran mediante ADR en:

docs/architecture/adr/

Una modificación estructural no puede integrarse únicamente porque compile o funcione.

Debe respetar el contrato arquitectónico y superar los controles automáticos.

## Protección automática

La arquitectura será protegida mediante:

- prueba de estructura del repositorio,
- análisis de dependencias,
- análisis estático,
- pruebas unitarias,
- pruebas funcionales,
- pruebas de integración con PostgreSQL,
- pruebas de concurrencia,
- pruebas de seguridad,
- pruebas frontend,
- pruebas E2E,
- GitHub Actions,
- CODEOWNERS,
- protección de main.

Una violación arquitectónica o una regresión debe provocar el fallo del Pull Request antes del merge.

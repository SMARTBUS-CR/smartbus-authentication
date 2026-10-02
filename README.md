<p align="center">
  <img src="public/assets/smartbus-logo.webp" width="300" alt="SmartBus Global Logo">
</p>

# SmartBus Global - API Gateway & Authentication

[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-777BB4?style=flat-square&logo=php)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com/)
[![Testing](https://img.shields.io/badge/Tested_with-Pest-F16529?style=flat-square)](https://pestphp.com/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)

## Visión General y Arquitectura

**SmartBus Global** es una plataforma integral de transporte público (B2G enfocada en Costa Rica), diseñada para la visualización de autobuses en tiempo real, predicción de llegadas (ETA) y gestión eficiente de rutas.

Este repositorio corresponde al **Servicio de Autenticación** del proyecto, encargado de la gestión de usuarios, roles y permisos, así como de la seguridad y autenticación de las aplicaciones cliente.

*   **Clientes Frontend:** Aplicación móvil unificada en Flutter que adapta su interfaz, estado y funcionalidades de forma dinámica (Conductor vs. Pasajero), y un Panel Web Administrativo (Filament).
*   **Ecosistema de Microservicios:**
    *   **Servicio de Autenticación:** *Este repositorio (Laravel).*
    *   **API Gateway:** Servicio de enrutamiento y control de acceso.
    *   **Panel Administrativo:** Gestión integral con Laravel/Filament.
    *   **Rastreo GPS en tiempo real:** Comunicación bidireccional usando Laravel Reverb/WebSockets.
    *   **Motor Predictivo ETA:** Inteligencia Artificial implementada en Python/TensorFlow.

---

## Stack Tecnológico y Características Principales

### Bases de Datos y Control de Acceso
*   **Múltiples Bases de Datos:**
    *   `MySQL`: Optimizada para el manejo exclusivo de usuarios, escalabilidad y análisis demográfico.
    *   `PostgreSQL` + `PostGIS`: Base de datos transaccional con extensiones espaciales para operaciones logísticas complejas.
*   **Gestión de Roles (`spatie/laravel-permission`):**
    *   `super-admin`: Control gubernamental y acceso total.
    *   `admin`: Administración de usuarios, roles y permisos.
    *   `driver`: Conductores operativos.
    *   `passenger`: Pasajeros y usuarios finales.
*   **Arquitectura Multi-inquilino (Multitenancy):** Aislamiento lógico gestionado por Filament para vincular cada `admin` estrictamente a su flotilla.

### Seguridad, Autenticación y API
*   **Gestión de Sesiones Seguras (`laravel/sanctum`):** Tokens con expiración dinámica por rol (Conductores 14h, Administradores 2-8h y Pasajeros 30 días).
*   **Defensa y Recuperación de Cuentas:**
    *   *Rate Limiting* estricto para mitigar ataques de fuerza bruta.
    *   Flujo seguro de recuperación mediante **OTP** (códigos de 6 dígitos enviados por correo, con validez de 15 minutos).
    *   Cambio de contraseña para pasajeros con confirmación de la contraseña actual y revocación de sus otros tokens.
*   **Documentación Interactiva (`dedoc/scramble`):** Especificación OpenAPI generada dinámicamente y siempre actualizada.

### Calidad e Internacionalización
*   **Soporte Bilingüe (i18n):** Middleware personalizado que interpreta el header `Accept-Language` para adaptar los mensajes, validaciones y respuestas (Español / Inglés).
*   **Pruebas Exhaustivas (`pestphp/pest`):** Suite de testing que abarca verificación de rutas protegidas, mocks de envío de correos, aserciones avanzadas y manipulación temporal (`freezeTime`).
*   **Estandarización y Clean Code (`laravel/pint`):** Garantía de uniformidad y calidad en el código fuente del equipo de desarrollo.

## API Principal

Todas las rutas están bajo el prefijo `/api`. Las rutas protegidas requieren un token Sanctum en el encabezado `Authorization: Bearer <token>` y, actualmente, también pasan por el middleware `verified`.

### Rutas públicas

| Método | Ruta | Descripción |
| --- | --- | --- |
| `POST` | `/api/register/passenger` | Registra un pasajero. |
| `POST` | `/api/login` | Inicia sesión y genera un token. Límite: 5 solicitudes por minuto. |
| `POST` | `/api/password/forgot` | Solicita un código OTP de recuperación. |
| `POST` | `/api/password/reset` | Restablece la contraseña con un código OTP. |

### Cuenta del pasajero

Estas rutas siempre operan sobre el propietario del token; no reciben un identificador de usuario y no sustituyen el endpoint administrativo `PATCH /api/users/{user}`.

| Método | Ruta | Descripción |
| --- | --- | --- |
| `PATCH` | `/api/user` | Actualiza el nombre y, opcionalmente, el correo del pasajero. Cambiar el correo requiere `current_password`. Límite: 5 solicitudes por minuto. |
| `PUT` | `/api/user/password` | Cambia la contraseña con `current_password`, `password` y `password_confirmation`. Mantiene el token actual y revoca los demás. Límite: 5 solicitudes por minuto. |

Los campos `roles`, `permissions`, `password` e `id` enviados a `PATCH /api/user` se ignoran. Los errores de validación se devuelven en formato JSON:API con `source.pointer` y mensajes disponibles en inglés y español mediante `Accept-Language`.

### Rutas protegidas principales

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/api/user` | Obtiene el usuario autenticado; admite `?include=roles`. |
| `POST` | `/api/token/validate` | Valida el token actual. `expires_at` puede ser `null` para tokens sin expiración. |
| `POST` | `/api/logout` | Revoca el token actual. |

### Verificación de correo

El modelo `User` implementa `MustVerifyEmail` y las rutas protegidas usan `verified`. El registro y el cambio de correo actualmente dejan el usuario sin verificación (`email_verified_at = null`), pero el flujo OTP de verificación de correo todavía debe implementarse antes de habilitar esta experiencia en producción.

---

## CI/CD y Despliegue

El ciclo de vida del software está automatizado para asegurar entregas rápidas y seguras:

1.  **Integración Continua (CI):** Flujos de trabajo en **GitHub Actions** ejecutan el linter (Laravel Pint) y la suite de pruebas (Pest PHP) con cada Pull Request, garantizando integridad en un modelo *GitFlow*.
2.  **Despliegue y Contenedores (CD):** El entorno de producción está completamente paquetizado mediante un `Dockerfile` optimizado (Multistage build), listo para aprovisionamiento directo en la infraestructura Cloud de **Render**.

---

## Instalación Local

```bash
# 1. Clonar el repositorio
git clone <url-del-repo>
cd smartbus-authentication

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate
php artisan migrate

# 4. Construir los assets frontend
npm run build

# 5. Levantar entorno local (servidor, cola y Vite)
composer run dev
```

El entorno local usa SQLite por defecto y el mailer `log`. Para ejecutar la suite:

```bash
php artisan test --compact
```

Antes de enviar cambios PHP, aplica el formato del proyecto:

```bash
vendor/bin/pint --dirty
```

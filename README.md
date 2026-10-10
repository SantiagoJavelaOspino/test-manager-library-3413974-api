# test-manager-library-3413974-api
**Backend API · Manager Library**  
*Ficha ADSO 3413974 · SENA*  
*Metodología:* Spec-Driven Development (SDD) · Stack: PHP 8.2+ · PostgreSQL 16+

---

## 1. Descripción
Este repositorio contiene la implementación del backend para **Manager Library**, construido en PHP siguiendo una **Arquitectura Hexagonal / Cebolla (Onion Architecture)** con estricta separación de capas (Constitución Artículos I, II, III y IV).

- **Dominio (`app/Domain`)**: Cero dependencias externas. Invariantes puras (`Book`, `Loan`, `LoanItem`, `Member`, `User`, `Category`) y Value Objects (`Isbn`, `Quantity`, `DateRange`, `Role`, `Username`, `Uuid`).
- **Aplicación (`app/Application`)**: Puertos desacoplados y casos de uso individuales (SRP).
- **Infraestructura (`app/Infrastructure`)**: Adaptadores para PostgreSQL (`library` schema), JWT nativo con HMAC SHA-256, hash Bcrypt y almacenamiento en disco de portadas.
- **HTTP (`app/Http`)**: Enrutador REST bajo `/api/v1` con contrato de error unificado y CORS habilitado.

---

## 1.1 Decisión Arquitectónica: PHP Hexagonal vs Framework Pesado
Durante la fase de aprovisionamiento de dependencias, Composer bloqueó la instalación de frameworks pesados (`laravel/framework` y paquetes vinculados) debido a advertencias críticas de seguridad y CVEs activos en el registro de paquetes.

En concordancia con los principios del reto:
1. **El Spec es el producto, no el framework:** La especificación define contratos de API, reglas de negocio y modelos de datos agnósticos.
2. **Constitución SDD (Artículos I, II y III):** Se exige que el núcleo de dominio no tenga acoplamiento a frameworks externos. La arquitectura hexagonal en PHP 8.2 nativo garantiza el 100% de aislamiento del dominio, enrutamiento liviano, emisión nativa de JWT HMAC-SHA256 y persistencia en PostgreSQL mediante PDO con transacciones ACID y control de concurrencia optimista (ADR-002).

## 2. Requisitos Previos
- PHP 8.2 o superior (con extensiones `pdo_pgsql`, `mbstring`, `fileinfo`).
- PostgreSQL 16+ en ejecución.
- Composer 2.x.

---

## 3. Instalación y Configuración Local

1. Clonar el repositorio y acceder a la carpeta:
   ```bash
   cd api
   ```

2. Instalar dependencias y generar autoload:
   ```bash
   composer install
   ```

3. Configurar variables de entorno:
   ```bash
   cp .env.example .env
   ```

4. Ejecutar las migraciones y sembrar categorías y admin inicial:
   ```bash
   php database/migrations/migrate.php
   ```

---

## 4. Ejecución del Servidor

Iniciar el servidor de desarrollo en el puerto 8000:
```bash
php -S 0.0.0.0:8000 -t public
```

El endpoint de salud estará disponible en:
- `GET http://localhost:8000/api/v1/health` $\rightarrow$ `{"status":"ok"}`

---

## 5. Pruebas Automatizadas

Ejecutar la suite completa de pruebas unitarias, de integración y de reglas arquitectónicas:
```bash
php tests/run_tests.php
```

Resultado verificado:
- **14/14 pruebas aprobadas**, validando contratos, concurrencia, límites de préstamos, vencimientos y preservación histórica de reportes (ADR-004).

---

## 6. Endpoints Principales (`/api/v1`)

| Método | Ruta | Rol Requerido | Descripción |
|---|---|---|---|
| `GET` | `/health` | Público | Verificación de estado |
| `POST` | `/auth/login` | Público | Autenticación y emisión de JWT |
| `POST` | `/users` | `admin` | Alta de bibliotecarios |
| `GET` | `/categories` | Autenticado | Catálogo de las 5 categorías |
| `GET` | `/books` | Autenticado | Búsqueda y catálogo paginado |
| `POST` | `/books` | `admin` | Creación de libro |
| `PUT` | `/books/{id}` | `admin` | Modificación de libro |
| `DELETE` | `/books/{id}` | `admin` | Baja lógica de libro |
| `GET` | `/members` | `admin`, `librarian` | Listado y búsqueda de socios |
| `POST` | `/members` | `admin`, `librarian` | Registro de nuevo socio |
| `POST` | `/loans` | `admin`, `librarian` | Creación de préstamo (1 a 3 libros) |
| `GET` | `/loans` | `admin`, `librarian` | Listado de préstamos con estado calculado |
| `GET` | `/loans/overdue` | `admin`, `librarian` | Listado de libros en mora |
| `POST` | `/loans/{loan_id}/items/{item_id}/return` | `admin`, `librarian` | Devolución de ejemplar |
| `GET` | `/reports/most-borrowed` | `admin` | Reporte de más prestados (valores congelados) |

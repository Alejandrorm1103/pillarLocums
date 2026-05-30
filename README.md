# PillarLocums

![Estado](https://img.shields.io/badge/status-en%20desarrollo-yellow)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)
![MySQL](https://img.shields.io/badge/MySQL-8%2B-blue)
![Licencia](https://img.shields.io/badge/licencia-MIT%20sugerida-green)

**PillarLocums** es una plataforma web orientada a conectar médicos, clínicas y hospitales para la gestión de turnos, ofertas laborales, disponibilidad, credenciales y comunicación interna. El proyecto está construido como una aplicación PHP tradicional desplegable en `public_html`, con una landing principal, sistema de registro/login, paneles por rol y panel administrativo.

La aplicación incluye paneles diferenciados para **médicos**, **clínicas** y **hospitales**, además de un módulo de administración para gestionar usuarios, credenciales, calendario/disponibilidad y contenido multimedia del sitio. El proyecto utiliza una arquitectura directa basada en PHP, HTML, CSS, JavaScript Vanilla y conexión a base de datos mediante PDO.

---

## Autor

**Alejandrorm1103 / Nicolás**

---

## Tecnologías usadas

- PHP 8.1 o superior recomendado.
- MySQL 8.0 o MariaDB 10.5+.
- PDO MySQL para conexión a base de datos.
- HTML5.
- CSS3.
- JavaScript Vanilla.
- Apache o Nginx como servidor web.
- Hosting compatible con estructura `public_html`, por ejemplo Hostinger, cPanel, XAMPP, Laragon o entorno LAMP.

No se detectaron dependencias de Composer, Node.js, npm, Vite, React ni frameworks PHP como Laravel o Symfony.

---

## Requisitos para ejecutar localmente

Antes de ejecutar el proyecto necesitas:

- PHP 8.1+.
- Extensión `pdo_mysql` habilitada.
- MySQL o MariaDB.
- Servidor web local:
  - XAMPP,
  - Laragon,
  - MAMP,
  - Apache,
  - Nginx,
  - o servidor embebido de PHP para pruebas rápidas.
- Una base de datos MySQL creada.
- Un dump SQL del proyecto o creación manual de las tablas requeridas.

---

## Instalación paso a paso

### 1. Clonar el repositorio

```bash
git clone git@github.com:Alejandrorm1103/PillarLocums.git
cd PillarLocums
```

Con HTTPS:

```bash
git clone https://github.com/Alejandrorm1103/PillarLocums.git
cd PillarLocums
```

---

### 2. Ubicar el proyecto en el servidor local

Este proyecto está preparado para ejecutarse desde una carpeta tipo `public_html`.

Con XAMPP:

```txt
C:/xampp/htdocs/PillarLocums
```

Con Laragon:

```txt
C:/laragon/www/PillarLocums
```

En un hosting tradicional, el contenido debe ir dentro de:

```txt
public_html/
```

---

### 3. Crear la base de datos

Crea una base de datos MySQL, por ejemplo:

```sql
CREATE DATABASE pillar_locums CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Luego importa el archivo `.sql` correspondiente si lo tienes:

```bash
mysql -u root -p pillar_locums < database.sql
```

En el paquete revisado no se encontró un archivo `.sql`, por lo que debes exportar/importar la estructura real de la base de datos desde el entorno donde el proyecto ya funciona.

---

### 4. Configurar la conexión a base de datos

El archivo principal de conexión detectado es:

```txt
config/db.php
```

Este archivo define variables PHP directamente:

```php
$DB_HOST = 'localhost';
$DB_PORT = '3306';
$DB_NAME = 'NOMBRE_BASE_DATOS';
$DB_USER = 'USUARIO_BASE_DATOS';
$DB_PASS = 'CONTRASEÑA_BASE_DATOS';
```

Ejemplo local:

```php
$DB_HOST = 'localhost';
$DB_PORT = '3306';
$DB_NAME = 'pillar_locums';
$DB_USER = 'root';
$DB_PASS = '';
```

---

### 5. Revisar configuración del panel admin

El panel admin usa:

```txt
admin/config.php
```

Este archivo contiene constantes como:

```php
define('APP_NAME', 'PILLAR Locums Admin');
define('BASE_URL', '/admin');
define('ADMIN_PASSWORD_HASH', 'HASH_DE_CONTRASEÑA');
define('MEDIA_JSON_PATH', dirname(__DIR__) . '/assets/media.json');
define('UPLOAD_CAROUSEL_DIR', dirname(__DIR__) . '/assets/uploads/carousel/');
define('UPLOAD_VIDEO_DIR', dirname(__DIR__) . '/assets/uploads/video/');
```

Recomendaciones:

- No guardar contraseñas reales en el repositorio.
- Mantener únicamente hashes seguros.
- Regenerar cualquier contraseña expuesta antes de subir el proyecto a GitHub.
- En producción, mantener `ALLOW_HASH_GEN` en `false`.

---

### 6. Ejecutar el proyecto

#### Opción A: servidor embebido de PHP

Desde la raíz del proyecto:

```bash
php -S localhost:8000
```

Abrir en el navegador:

```txt
http://localhost:8000/index.html
```

#### Opción B: XAMPP / Apache

Coloca el proyecto en `htdocs` y abre:

```txt
http://localhost/PillarLocums/index.html
```

#### Opción C: hosting con `public_html`

Sube el contenido del proyecto directamente a:

```txt
public_html/
```

Luego accede al dominio configurado.

---

## Estructura de carpetas

```txt
public_html/
├── admin/
│   ├── assets/
│   │   └── admin.css
│   ├── auth.php
│   ├── calendar.php
│   ├── config.php
│   ├── credenciales.php
│   ├── db.php
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── media.php
│   ├── user_delete.php
│   ├── user_edit.php
│   └── users.php
│
├── assets/
│   ├── uploads/
│   │   ├── carousel/
│   │   └── video/
│   ├── media.json
│   ├── logo.png
│   ├── logo en blanco.png
│   ├── logo en negro.png
│   └── imágenes principales del sitio
│
├── config/
│   ├── auth.php
│   ├── db.php
│   └── I18n.php
│
├── css/
│   └── style.css
│
├── payments/
│
├── php/
│   └── ofertas_home.php
│
├── recursos/
│   ├── fotos/
│   ├── music/
│   └── videos/
│
├── uploads/
│   └── medicos/
│
├── usuarios/
│   ├── clinica/
│   │   ├── modules/
│   │   ├── aplicantes.php
│   │   ├── chat.php
│   │   ├── contactos.php
│   │   ├── espacio.php
│   │   ├── index.php
│   │   ├── ofertas.php
│   │   └── perfil.php
│   │
│   ├── hospital/
│   │   ├── modules/
│   │   ├── aplicantes.php
│   │   ├── chat.php
│   │   ├── contactos.php
│   │   ├── espacio.php
│   │   ├── index.php
│   │   ├── ofertas.php
│   │   └── perfil.php
│   │
│   ├── medico/
│   │   ├── chat.php
│   │   ├── contactos.php
│   │   ├── espacio.php
│   │   ├── index.php
│   │   ├── ofertas.php
│   │   ├── perfil.php
│   │   └── solicitudes.php
│   │
│   ├── dashboard.php
│   ├── db.php
│   ├── db_test.php
│   ├── login.php
│   ├── logout.php
│   ├── ping.php
│   └── register.php
│
├── bootstrap.php
├── conocenos.html
├── contactanos.html
├── default.php
├── descargaApp.html
├── I18n.php
└── index.html
```

---

## Explicación de carpetas principales

### `admin/`

Contiene el panel administrativo del proyecto:

- Login de administrador.
- Dashboard general.
- Gestión de usuarios.
- Gestión de credenciales médicas.
- Calendario/disponibilidad.
- Gestión de contenido multimedia.
- Configuración propia del panel.

Rutas principales:

```txt
/admin/login.php
/admin/index.php
/admin/users.php
/admin/calendar.php
/admin/media.php
/admin/credenciales.php
```

---

### `usuarios/`

Contiene el sistema de autenticación y los paneles por rol.

Rutas principales:

```txt
/usuarios/login.php
/usuarios/register.php
/usuarios/logout.php
/usuarios/dashboard.php
```

El sistema redirige según el rol del usuario:

```txt
medico   -> /usuarios/medico/index.php
clinica  -> /usuarios/clinica/index.php
hospital -> /usuarios/hospital/index.php
```

---

### `usuarios/medico/`

Panel del médico:

- Ofertas disponibles.
- Solicitudes enviadas.
- Perfil profesional.
- Gestión de documentos/credenciales.
- Contactos.
- Chat.
- Espacio personal.

---

### `usuarios/clinica/`

Panel de clínica:

- Publicación y gestión de ofertas.
- Aplicantes.
- Calendario.
- Perfil.
- Contactos.
- Chat.
- Espacio institucional.

---

### `usuarios/hospital/`

Panel de hospital:

- Publicación y gestión de ofertas.
- Aplicantes.
- Calendario.
- Perfil.
- Contactos.
- Chat.
- Espacio institucional.

---

### `config/`

Contiene configuración global:

```txt
config/db.php
config/auth.php
config/I18n.php
```

`config/db.php` crea la conexión PDO y exporta:

```php
$pdo = db();
```

`config/auth.php` maneja:

- Inicio de sesión.
- Cierre de sesión.
- Redirección por rol.
- Protección de rutas privadas.

---

### `assets/`

Contiene logos, imágenes principales, video promocional y configuración multimedia.

Archivo importante:

```txt
assets/media.json
```

Este archivo es usado por el panel admin para controlar:

- Video principal.
- Imágenes del carrusel.
- Captions.
- Escala y posición de imágenes.

---

### `php/`

Contiene endpoints auxiliares.

Actualmente se detectó:

```txt
php/ofertas_home.php
```

Este endpoint devuelve ofertas activas en formato JSON para la landing principal.

---

### `uploads/`

Carpeta destinada a archivos subidos por usuarios, especialmente médicos.

Ejemplo:

```txt
uploads/medicos/
```

---

### `recursos/`

Carpeta de recursos multimedia adicionales:

```txt
recursos/fotos/
recursos/music/
recursos/videos/
```

---

### `payments/`

Carpeta reservada para futuras integraciones de pagos.

En el estado actual del paquete, no contiene implementación visible.

---

## Variables sensibles y configuración recomendada

Actualmente el proyecto guarda configuración sensible directamente en archivos PHP.

Archivos relevantes:

```txt
config/db.php
admin/config.php
```

Recomendación mínima:

1. No subir credenciales reales a GitHub.
2. Cambiar las credenciales expuestas antes de desplegar.
3. Usar contraseñas distintas para local, staging y producción.
4. Usar hashes para contraseñas administrativas.
5. Mantener fuera del repositorio cualquier archivo local con datos reales.

Ejemplo recomendado de archivo local no versionado:

```txt
config/db.local.php
```

Ejemplo:

```php
<?php
$DB_HOST = 'localhost';
$DB_PORT = '3306';
$DB_NAME = 'pillar_locums';
$DB_USER = 'root';
$DB_PASS = '';
```

Luego, en `config/db.php`, podrías cargarlo así:

```php
$localConfig = __DIR__ . '/db.local.php';

if (file_exists($localConfig)) {
    require $localConfig;
}
```

Si usas `db.local.php`, añádelo al `.gitignore`.

---

## Permisos necesarios

El servidor web necesita permisos de escritura en las carpetas donde se suben archivos o se modifica contenido multimedia.

Carpetas/archivos recomendados con permisos de escritura:

```txt
assets/media.json
assets/uploads/carousel/
assets/uploads/video/
uploads/
uploads/medicos/
usuarios/uploads/
usuarios/uploads/clinic/
recursos/fotos/
recursos/music/
recursos/videos/
```

En Linux/hosting:

```bash
chmod -R 775 assets/uploads
chmod -R 775 uploads
chmod -R 775 usuarios/uploads
chmod -R 775 recursos
chmod 664 assets/media.json
```

Si el servidor usa un usuario específico como `www-data`:

```bash
sudo chown -R www-data:www-data assets/uploads uploads usuarios/uploads recursos
sudo chown www-data:www-data assets/media.json
```

---

## Rutas principales

### Landing pública

```txt
/index.html
```

Secciones principales:

- Inicio.
- Conócenos.
- Contacto.
- Ofertas.
- Registro.
- Login.

---

### Registro

```txt
/usuarios/register.php
```

Roles disponibles:

```txt
medico
clinica
hospital
```

---

### Login de usuarios

```txt
/usuarios/login.php
```

Redirección automática según rol:

```txt
medico   -> /usuarios/medico/index.php
clinica  -> /usuarios/clinica/index.php
hospital -> /usuarios/hospital/index.php
```

---

### Panel médico

```txt
/usuarios/medico/index.php
```

Tabs principales:

```txt
Ofertas
Mis solicitudes
Calendario
Mi espacio
Perfil
Contactos
```

---

### Panel clínica

```txt
/usuarios/clinica/index.php
```

Tabs principales:

```txt
Ofertas
Calendario
Mi espacio
Perfil
Aplicantes
Contactos
```

---

### Panel hospital

```txt
/usuarios/hospital/index.php
```

Tabs principales:

```txt
Ofertas
Calendario
Mi espacio
Perfil
Aplicantes
Contactos
```

---

### Panel administrador

```txt
/admin/login.php
/admin/index.php
```

Módulos principales:

```txt
Usuarios
Calendario
Media
Credenciales
```

---

### Endpoint de ofertas para home

```txt
/php/ofertas_home.php
```

Devuelve JSON con ofertas activas desde la tabla `jobs`.

---

## Tablas de base de datos detectadas en el código

El proyecto hace referencia a varias tablas. La estructura SQL exacta no está incluida en el paquete, pero se detectaron estas tablas por uso en consultas:

```txt
users
jobs
job_applications
availability
clinic_events
clinic_contacts
hospital_contacts
conversaciones
mensajes
medico_profile
medico_documents
medico_verifications
clinic_documents
hospital_documents
clinic_activity
hospital_activity
```

Antes de ejecutar el proyecto en un entorno nuevo, asegúrate de importar la base de datos completa o crear estas tablas con las columnas esperadas por los módulos.

---

## Comandos útiles

### Levantar servidor PHP local

```bash
php -S localhost:8000
```

### Probar que PHP ejecuta correctamente

```txt
http://localhost:8000/usuarios/ping.php
```

### Probar conexión a base de datos

```txt
http://localhost:8000/usuarios/db_test.php
```

Se recomienda eliminar o proteger `db_test.php` en producción.

---

### Revisar versión de PHP

```bash
php -v
```

---

### Revisar módulos PHP instalados

```bash
php -m
```

Debe aparecer:

```txt
PDO
pdo_mysql
```

---

## Composer y npm

En el estado actual del proyecto no se detectaron archivos:

```txt
composer.json
package.json
```

Por tanto, no es necesario ejecutar:

```bash
composer install
npm install
npm run dev
```

Si en el futuro se agregan dependencias, documentarlas en esta sección.

---

## Recomendaciones de despliegue

### Apache

Configura el `DocumentRoot` apuntando a la raíz del proyecto o a `public_html`.

Ejemplo de VirtualHost:

```apache
<VirtualHost *:80>
    ServerName pillarlocums.local
    DocumentRoot "/ruta/al/proyecto/public_html"

    <Directory "/ruta/al/proyecto/public_html">
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/pillarlocums_error.log
    CustomLog ${APACHE_LOG_DIR}/pillarlocums_access.log combined
</VirtualHost>
```

Reiniciar Apache:

```bash
sudo systemctl restart apache2
```

---

### Nginx

Ejemplo básico:

```nginx
server {
    listen 80;
    server_name pillarlocums.local;

    root /ruta/al/proyecto/public_html;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Reiniciar Nginx:

```bash
sudo systemctl restart nginx
```

---

## Seguridad básica recomendada

Antes de subir el proyecto a producción:

1. **Rotar credenciales expuestas**

   Si alguna contraseña real estuvo en el código, cámbiala en el servidor y en la base de datos.

2. **No subir archivos sensibles**

   No versionar:

   ```txt
   .env
   config/db.local.php
   backups SQL
   dumps de base de datos
   archivos temporales
   credenciales reales
   ```

3. **Proteger archivos de prueba**

   Eliminar o restringir:

   ```txt
   usuarios/db_test.php
   usuarios/ping.php
   ```

4. **Validar y sanitizar inputs**

   El proyecto usa `htmlspecialchars`, `password_hash`, `password_verify` y consultas preparadas PDO en varias zonas. Mantener ese patrón en todos los formularios nuevos.

5. **Proteger sesiones**

   Mantener:

   - `session_regenerate_id(true)` tras login.
   - Cookies `httponly`.
   - `samesite=Lax`.
   - HTTPS en producción.

6. **Subidas de archivos**

   Validar:

   - MIME real.
   - Extensión.
   - Tamaño máximo.
   - Nombre de archivo.
   - Carpeta de destino.

7. **CSRF**

   El panel admin incluye funciones CSRF. Mantener `csrf_token()` y `verify_csrf()` en formularios críticos.

8. **Permisos**

   Evitar permisos `777` en producción. Usar `775` o configuración equivalente según el hosting.

9. **Errores**

   En producción:

   ```php
   ini_set('display_errors', '0');
   error_reporting(E_ALL);
   ```

   Registrar errores en logs del servidor.

---

## Notas técnicas del estado actual

- `index.html` es la landing principal.
- `php/ofertas_home.php` sirve ofertas activas para la home.
- `config/db.php` es la conexión central PDO.
- `usuarios/db.php` y `admin/db.php` cargan la conexión central.
- `admin/config.php` contiene configuración del panel admin y rutas de media.
- `assets/media.json` controla carrusel/video de la landing.
- `payments/` existe como carpeta reservada, pero no contiene implementación visible en el paquete revisado.
- No se detectó `.htaccess`.
- No se detectó archivo SQL de estructura de base de datos.
- No se detectaron dependencias Composer o npm.

---

## Contribuir

Para contribuir al proyecto:

1. Crear una rama nueva:

```bash
git checkout -b feature/nombre-de-la-funcionalidad
```

2. Realizar los cambios.

3. Verificar que el proyecto sigue ejecutando correctamente:

```bash
php -S localhost:8000
```

4. Probar rutas principales:

```txt
/index.html
/usuarios/login.php
/usuarios/register.php
/admin/login.php
```

5. Revisar que no se suban credenciales reales:

```bash
git status
```

6. Hacer commit:

```bash
git add .
git commit -m "feat: descripción breve del cambio"
```

7. Subir la rama:

```bash
git push origin feature/nombre-de-la-funcionalidad
```

8. Crear un Pull Request hacia la rama principal.

---

## Licencia

Licencia sugerida: **MIT**.

Si decides usar MIT, agrega un archivo `LICENSE` en la raíz del repositorio con el texto oficial de la licencia MIT y el nombre del autor.

Ejemplo:

```txt
MIT License

Copyright (c) 2026 Nicolás / Alejandrorm1103
```

Si el proyecto es privado o comercial, puedes dejarlo sin licencia pública hasta definir los términos de uso.

# Magento Data Quality Tool

🔍 **Herramienta de análisis y reparación de integridad de datos para Magento**

Una utilidad robusta que actúa como un "detector de humo" para tu base de datos Magento, identificando y reparando problemas de integridad referencial que pueden afectar el rendimiento y estabilidad de tu tienda.

## 🎯 ¿Qué hace esta herramienta?

Imagina tu base de datos Magento como una biblioteca gigante donde cada libro (registro) debe estar correctamente catalogado y referenciado. Con el tiempo, algunos libros pueden perderse o las referencias pueden quedar obsoletas. Esta herramienta:

- **Detecta** referencias rotas entre tablas (como catálogos que apuntan a libros inexistentes)
- **Identifica** registros huérfanos que consumen espacio innecesariamente
- **Repara** estos problemas de forma segura e interactiva

## ✨ Características Principales

- 🔍 **Análisis Silencioso**: Ejecuta verificaciones completas en segundo plano
- 🛠️ **Asistente de Reparación Interactivo**: Control total sobre cada acción de reparación
- ⚡ **Optimizado para Magento**: Conoce las relaciones específicas de Magento
- 🛡️ **Modo Seguro**: Confirmación manual antes de cada operación destructiva
- 📊 **Reportes Detallados**: Información clara sobre problemas encontrados

## 🚀 Instalación

### Opción 1: Descarga directa (Recomendada)
```bash
# Desde la raíz de tu instalación Magento
cd /ruta/a/tu/magento

# Descarga el script directamente
wget https://raw.githubusercontent.com/way2ecommerce/magento-data-quality-tool/main/DataQualityTool.php

# O usando curl
curl -O https://raw.githubusercontent.com/way2ecommerce/magento-data-quality-tool/main/DataQualityTool.php
```

### Opción 2: Clonado y copia
```bash
# Clona el repositorio
git clone https://github.com/way2ecommerce/magento-data-quality-tool.git

# Copia el archivo a la raíz de tu Magento
cp magento-data-quality-tool/DataQualityTool.php /ruta/a/tu/magento/

# Navega a tu directorio Magento
cd /ruta/a/tu/magento

# Asegúrate de tener PHP 7.4+ instalado
php --version
```

> **📁 Importante**: El script debe ejecutarse desde la raíz de tu instalación Magento para poder acceder al archivo `app/etc/env.php`

## 📋 Requisitos

- PHP 7.4 o superior
- Acceso a la base de datos MySQL de Magento
- Permisos de lectura/escritura (para el modo `--fix`)

## 🔧 Uso

> **📍 Nota**: Todos los comandos deben ejecutarse desde la raíz de tu instalación Magento

### 1. Análisis por Defecto (Silencioso)

Ejecuta una verificación completa sin modificar datos:

```bash
# Desde la raíz de Magento
php DataQualityTool.php
```

**Salida de ejemplo si hay errores:**

```
Iniciando Herramienta de Calidad de Datos para Magento...

----------------- RESUMEN DEL ANÁLISIS -----------------
Se encontraron 2 problemas de integridad de datos:

 - [FALLO] en tabla `catalog_eav_attribute`: La columna `attribute_id`... apunta a registros inexistentes en `eav_attribute`.
 - [FALLO] en tabla `cms_page_store`: La columna `page_id`... apunta a registros inexistentes en `cms_page`.

Para solucionar estos problemas, ejecuta el script con la opción: php DataQualityTool.php --fix
----------------------------------------------------------
```

### 2. Análisis con Progreso Detallado (`--progress`)

Para ver el progreso tabla por tabla durante el análisis:

```bash
php DataQualityTool.php --progress
```

**Salida de ejemplo:**
```
Iniciando Herramienta de Calidad de Datos para Magento...
Modo de progreso detallado activado.
[1/245] `admin_user` -> [OK]
[2/245] `catalog_eav_attribute` -> [FALLO]
[3/245] `cms_page_store` -> [FALLO]
...
```

### 3. Asistente de Reparación (`--fix`)

🌟 **Nueva funcionalidad estrella**: Reparación interactiva y controlada

```bash
php DataQualityTool.php --fix
```

**Ejemplo de sesión interactiva:**

```
Iniciando Herramienta de Calidad de Datos para Magento...
(Aquí aparecerá el resumen con los fallos)

----------------- ASISTENTE DE REPARACIÓN INTERACTIVO -----------------
¡ADVERTENCIA! Este proceso modificará tu base de datos.
Asegúrate de tener una copia de seguridad reciente antes de continuar.

¿Estás seguro de que quieres continuar? [y/n]: y

---------------- Problema 1/2 ----------------
Tipo:    Registros Huérfanos
Tabla:   `catalog_eav_attribute`
Detalle: La columna `attribute_id` en `catalog_eav_attribute` apunta a registros inexistentes en `eav_attribute`.
Impacto: Se han encontrado 14 registros huérfanos para eliminar.
Acción:  DELETE FROM `catalog_eav_attribute` WHERE la referencia no exista.

¿Proceder con la eliminación? [y/n]: y
ÉXITO: Se han eliminado 14 registros huérfanos de `catalog_eav_attribute`.

---------------- Problema 2/2 ----------------
Tipo:    Registros Huérfanos
Tabla:   `cms_page_store`
Detalle: La columna `page_id` en `cms_page_store` apunta a registros inexistentes en `cms_page`.
Impacto: Se han encontrado 3 registros huérfanos para eliminar.
Acción:  DELETE FROM `cms_page_store` WHERE la referencia no exista.

¿Proceder con la eliminación? [y/n]: n
Acción omitida por el usuario.

Proceso de reparación finalizado.
Se recomienda limpiar la caché de Magento: `bin/magento cache:flush`
```

## ⚠️ Advertencias de Seguridad

> **🚨 IMPORTANTE**: Esta herramienta puede modificar tu base de datos. 

**Antes de usar el modo `--fix`:**

1. 📦 **Realiza un backup completo** de tu base de datos
2. 🧪 **Prueba en un entorno de desarrollo** primero
3. 🕒 **Ejecuta durante horarios de bajo tráfico**
4. 📋 **Documenta los cambios realizados**

```bash
# Ejemplo de backup rápido
mysqldump -u usuario -p nombre_bd > backup_$(date +%Y%m%d_%H%M%S).sql
```

## 🛠️ Configuración

Edita la configuración de base de datos en el archivo principal:

```php
// Configuración de conexión
$config = [
    'host' => 'localhost',
    'database' => 'tu_magento_db',
    'username' => 'tu_usuario',
    'password' => 'tu_password'
];
```

## 📈 Casos de Uso Comunes

- **Post-migración**: Verificar integridad después de migrar datos
- **Mantenimiento regular**: Limpieza mensual de registros huérfanos
- **Resolución de problemas**: Investigar errores de referencia en logs
- **Optimización**: Mejorar rendimiento eliminando datos innecesarios

## 🐛 Solución de Problemas

### Error de conexión a base de datos
```bash
# Verifica credenciales y conectividad
php -r "new PDO('mysql:host=localhost;dbname=tu_db', 'usuario', 'password');"
```

### Permisos insuficientes
```bash
# Asegúrate de que el usuario tenga permisos SELECT y DELETE
GRANT SELECT, DELETE ON tu_magento_db.* TO 'tu_usuario'@'localhost';
```

## 🤝 Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

## 📝 Roadmap

- [ ] Soporte para Magento 2.4.x específico
- [ ] Integración con CLI de Magento
- [ ] Reportes en formato JSON/XML
- [ ] Programación automática de verificaciones
- [ ] Dashboard web para visualización

## 📄 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE.md](LICENSE.md) para detalles.

## 🙏 Agradecimientos

- Comunidad de Magento por las mejores prácticas de integridad de datos
- Contribuidores que han reportado bugs y sugerencias

---

**⭐ Si esta herramienta te ha sido útil, no olvides darle una estrella al repositorio!**

## 🆘 Soporte

¿Tienes preguntas o problemas? 

- 🐛 [Reportar un bug](../../issues)
- 💡 [Solicitar una funcionalidad](../../issues)
- 📧 [Contacto directo](mailto:tu-email@dominio.com)

---

*Desarrollado con ❤️ para la comunidad Magento*
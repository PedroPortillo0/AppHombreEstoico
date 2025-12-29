# Solución al error de certificados SSL en Windows

## El Problema
cURL no puede verificar el certificado SSL de OpenAI porque falta el archivo de certificados CA.

## Solución

### 1. Descargar el archivo cacert.pem
1. Ve a: https://curl.se/ca/cacert.pem
2. Descarga el archivo `cacert.pem`
3. Guárdalo en una ubicación segura, por ejemplo:
   `C:\php\cacert.pem`

### 2. Configurar PHP para usar el archivo
Abre tu archivo php.ini y busca estas líneas:

```ini
;curl.cainfo =
;openssl.cafile=
```

Descoméntalas y establece la ruta al archivo cacert.pem:

```ini
curl.cainfo = "C:\php\cacert.pem"
openssl.cafile = "C:\php\cacert.pem"
```

### 3. Reiniciar el servidor
Reinicia tu servidor web (Apache, Nginx, o el servidor de desarrollo de Laravel).

### 4. Verificar
Ejecuta `php -i | findstr curl.cainfo` para verificar que la configuración se aplicó.

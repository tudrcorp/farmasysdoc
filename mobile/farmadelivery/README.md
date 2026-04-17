# Farmadoc Reparto (Expo)

App móvil de reparto con **Expo Router** y API Laravel (Bearer Sanctum): login `POST /api/v1/delivery/auth/login`, lista `GET /api/v1/delivery/orders/pending`, detalle con ítems `GET /api/v1/delivery/orders/{id}` (solo pedidos pendientes de aliados).

## Requisitos

- Node 20+
- Cuenta [Expo](https://expo.dev) para **EAS Build** (APK)
- **Simulador iOS**: [Xcode](https://developer.apple.com/xcode/) desde la Mac App Store (incluye *Simulator*). Abre Xcode una vez para aceptar licencia e instalar componentes.

## Configuración

1. Copia variables de entorno:

   ```bash
   cp .env.example .env
   ```

2. Ajusta `EXPO_PUBLIC_API_URL` a la URL base de tu Laravel **sin** `/api` al final (ver secciones según dispositivo).

3. En el backend, el usuario debe tener el rol **`DELIVERY`** en el JSON `roles` para poder entrar.

Navegación autenticada: **tabs** flotantes estilo iOS (vidrio + blur) con **Pedidos** y **Cuenta**; el detalle de un pedido se abre en **stack** encima con gesto de regreso.

### Simulador iOS (MacBook + Xcode)

El **Simulador** es la app que viene con Xcode; no hace falta abrir el proyecto `.xcodeproj` a menos que quieras compilar nativo a mano.

1. **Herramientas de línea de comandos** (si Xcode te lo pide):

   ```bash
   xcode-select -p
   ```

   Si falla: Xcode → *Settings* → *Locations* → *Command Line Tools* → elige tu versión de Xcode.

2. **Instala un runtime de iOS** (si no aparece simulador): Xcode → *Settings* → *Platforms* → descarga un *iOS Simulator*.

3. Desde **`mobile/farmadelivery`**:

   ```bash
   npm install
   npm run ios
   ```

   Eso arranca Metro y abre el Simulador con **Expo Go** (o el flujo que Expo elija). También puedes usar `npx expo start` y pulsar **`i`** en la terminal.

4. **`EXPO_PUBLIC_API_URL` en el Simulador** (el “teléfono virtual” usa la red de tu Mac):

   - Con **Laravel Herd** y tu sitio `*.test`, suele funcionar:  
     `EXPO_PUBLIC_API_URL=https://farmasysdoc.test`  
     (ajusta al dominio real de tu proyecto en Herd.)
   - O con servidor embebido: en la raíz del repo Laravel  
     `php artisan serve`  
     y en `.env` del móvil:  
     `EXPO_PUBLIC_API_URL=http://127.0.0.1:8000`

5. Tras cambiar `.env`: `npx expo start -c`.

**Build nativo con Xcode** (opcional, sin depender de Expo Go en el simulador):

```bash
cd mobile/farmadelivery
npx expo prebuild --platform ios
npx expo run:ios
```

La primera vez instalará *CocoaPods* en `ios/`. Útil si necesitas módulos nativos o el mismo binario que en TestFlight.

### iPhone físico + Expo Go (error “Network request failed”)

En el teléfono **no funcionan** `https://…test` (Herd) ni `localhost`: apuntan a la Mac solo desde la propia Mac.

1. Mac e iPhone en la **misma Wi‑Fi**.
2. En la raíz del monorepo Laravel:  
   `php artisan serve --host=0.0.0.0 --port=8000`
3. Anota la **IP local de la Mac** (Ajustes del Sistema → Red).
4. En `mobile/farmadelivery/.env`:  
   `EXPO_PUBLIC_API_URL=http://ESA_IP:8000`  
   (sin barra final; usa **http** en desarrollo para evitar certificados no confiables).
5. Reinicia Metro: `npx expo start -c`.
6. Si macOS pregunta por el **firewall**, permite conexiones entrantes para PHP o Terminal.

Alternativa: túnel (**ngrok**, **Cloudflare Tunnel**) con HTTPS público y esa URL en `EXPO_PUBLIC_API_URL`.

### Android físico + Expo Go

Es el **mismo problema de red** que en iPhone: el teléfono no ve `localhost` ni `*.test` de tu Mac.

1. Mac y Android en la **misma Wi‑Fi** (o USB con el truco de abajo).
2. Laravel: `php artisan serve --host=0.0.0.0 --port=8000`
3. `mobile/farmadelivery/.env`:  
   `EXPO_PUBLIC_API_URL=http://IP_DE_TU_MAC:8000`  
4. `npx expo start -c` y abre el proyecto en **Expo Go** en Android (`a` en la terminal o escanea el QR).

En el proyecto está `android.usesCleartextTraffic: true` en `app.json` para permitir **HTTP** en builds propios (`expo run:android` / APK de desarrollo). **Expo Go** ya permite probar contra `http://` en la red local en la mayoría de casos.

**USB (opcional):** con el dispositivo conectado por USB y depuración USB activada:

```bash
adb reverse tcp:8000 tcp:8000
```

Entonces puedes usar `EXPO_PUBLIC_API_URL=http://127.0.0.1:8000` solo en ese dispositivo (el puerto 8000 de la Mac se reenvía al móvil).

## Desarrollo

```bash
npm install
npx expo start
```

Atajos: **`i`** = Simulador iOS, **`a`** = abrir en **Android** (emulador o dispositivo con depuración USB / mismo QR que Expo Go).

## EAS (APK)

1. Inicia el proyecto en Expo (genera `projectId` en `app.json`):

   ```bash
   npx eas-cli@latest init
   ```

2. Compila APK perfil **preview** (distribución interna):

   ```bash
   npm run eas:build:android:preview
   ```

Perfiles definidos en `eas.json`: `preview` y `production` generan **APK** en Android.

## Nota sobre la ruta en el repo

El código vive en `mobile/farmadelivery` (no dentro de `app/` de Laravel) para no chocar con el namespace PSR-4 `App\`.

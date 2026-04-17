import * as Location from 'expo-location';
import { Platform } from 'react-native';

export type GpsGateResult =
  | { ok: true }
  | { ok: false; message: string };

const POSITION_TIMEOUT_MS = 14_000;

/**
 * Exige servicios de ubicación del sistema + permiso + una lectura de posición (GPS útil para reparto).
 */
export async function ensureGpsReadyForTakeOrder(): Promise<GpsGateResult> {
  if (Platform.OS === 'web') {
    return {
      ok: false,
      message:
        'Tomar pedidos con ubicación solo está disponible en la app para Android o iPhone.',
    };
  }

  const servicesEnabled = await Location.hasServicesEnabledAsync();
  if (!servicesEnabled) {
    return {
      ok: false,
      message:
        'Activa la ubicación (GPS) en los ajustes del teléfono para poder tomar pedidos.',
    };
  }

  const perm = await Location.requestForegroundPermissionsAsync();
  if (perm.status !== Location.PermissionStatus.GRANTED) {
    return {
      ok: false,
      message:
        'Necesitamos permiso de ubicación para comprobar que el GPS está disponible al tomar un pedido.',
    };
  }

  try {
    await Promise.race([
      Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      }),
      new Promise<never>((_, reject) => {
        setTimeout(() => {
          reject(new Error('timeout'));
        }, POSITION_TIMEOUT_MS);
      }),
    ]);
  } catch {
    return {
      ok: false,
      message:
        'No se pudo obtener tu ubicación. Activa el GPS, espera la señal e inténtalo de nuevo.',
    };
  }

  return { ok: true };
}

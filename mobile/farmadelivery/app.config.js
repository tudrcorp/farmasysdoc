/**
 * Amplía app.json con claves de Google Maps para builds nativos (PROVIDER_GOOGLE).
 * Carga EXPO_PUBLIC_GOOGLE_MAPS_KEY desde .env vía Expo (npx expo start).
 */
module.exports = ({ config }) => {
  const mapsKey = process.env.EXPO_PUBLIC_GOOGLE_MAPS_KEY || '';
  if (!mapsKey) {
    return config;
  }

  return {
    ...config,
    ios: {
      ...config.ios,
      config: {
        ...(config.ios?.config || {}),
        googleMapsApiKey: mapsKey,
      },
    },
    android: {
      ...config.android,
      config: {
        ...(config.android?.config || {}),
        googleMaps: {
          ...(config.android?.config?.googleMaps || {}),
          apiKey: mapsKey,
        },
      },
    },
  };
};

import Ionicons from '@expo/vector-icons/Ionicons';
import * as Location from 'expo-location';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Animated,
  Dimensions,
  Linking,
  PanResponder,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { BlurView } from 'expo-blur';
import * as ImagePicker from 'expo-image-picker';
import MapView, {
  Marker,
  Polyline,
  PROVIDER_GOOGLE,
  type LatLng,
} from 'react-native-maps';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAuth } from '../../../contexts/AuthContext';
import {
  completeDeliveryOrder,
  fetchDeliveryNavigation,
  fetchTransferNavigation,
  type DeliveryNavigationData,
} from '../../../lib/api';
import { colors } from '../../../lib/colors';
import {
  decodeEncodedPolyline,
  estimateDrivingDurationSeconds,
  fetchDrivingRouteWithMetadata,
  geocodeGoogleAddress,
  haversineDistanceMeters,
  sanitizeRouteCoords,
  straightLineRoute,
  type MapCoordinate as RouteCoord,
} from '../../../lib/fetchDrivingRoute';
import { liquidRadius, liquidType } from '../../../lib/liquidHigTheme';

const DEFAULT_REGION = {
  latitude: 10.4969,
  longitude: -66.8983,
  latitudeDelta: 0.12,
  longitudeDelta: 0.12,
};

/** Azul tipo Apple Maps / sistema. */
const ROUTE_STROKE = '#007AFF';

const SHEET_BG = '#1a221c';
const SHEET_BORDER = 'rgba(255,255,255,0.09)';
const SHEET_BORDER_IOS = 'rgba(0,0,0,0.08)';

/** Colores tipo panel claro de Apple Maps (iOS). */
const IOS_MAP_SHEET = {
  title: '#000000',
  subtitle: 'rgba(60,60,67,0.65)',
  muted: 'rgba(60,60,67,0.55)',
  metric: '#000000',
  metricLabel: 'rgba(60,60,67,0.45)',
  divider: 'rgba(60,60,67,0.12)',
  lane: 'rgba(60,60,67,0.55)',
  warn: '#C93400',
  topBtnBg: 'rgba(255,255,255,0.82)',
  topBtnBorder: 'rgba(0,0,0,0.06)',
  topIcon: '#1c1c1e',
  deliveredBg: '#34C759',
  deliveredText: '#ffffff',
};

async function pickDeliveryEvidenceImage(): Promise<{
  uri: string;
  mimeType: string;
} | null> {
  const camPerm = await ImagePicker.requestCameraPermissionsAsync();
  if (camPerm.granted) {
    const cam = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.85,
      allowsEditing: true,
      aspect: [4, 3],
    });
    if (!cam.canceled && cam.assets[0]) {
      const a = cam.assets[0];
      return {
        uri: a.uri,
        mimeType: a.mimeType ?? 'image/jpeg',
      };
    }
  }

  const libPerm = await ImagePicker.requestMediaLibraryPermissionsAsync();
  if (!libPerm.granted) {
    Alert.alert(
      'Permisos',
      'Activa la cámara o el acceso a fotos para adjuntar la evidencia de entrega.',
    );
    return null;
  }

  const lib = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ImagePicker.MediaTypeOptions.Images,
    quality: 0.85,
    allowsEditing: true,
    aspect: [4, 3],
  });
  if (lib.canceled || !lib.assets[0]) {
    return null;
  }
  const a = lib.assets[0];
  return {
    uri: a.uri,
    mimeType: a.mimeType ?? 'image/jpeg',
  };
}

function openGoogleMapsDirections(params: {
  origin?: { lat: number; lng: number };
  destinationLabel: string;
  destinationCoords?: { lat: number; lng: number };
}): void {
  const { origin, destinationLabel, destinationCoords } = params;
  const q = new URLSearchParams({ api: '1', travelmode: 'driving' });
  if (origin) {
    q.set('origin', `${origin.lat},${origin.lng}`);
  }
  if (destinationCoords) {
    q.set('destination', `${destinationCoords.lat},${destinationCoords.lng}`);
  } else {
    q.set('destination', destinationLabel);
  }
  const url = `https://www.google.com/maps/dir/?${q.toString()}`;
  void Linking.openURL(url);
}

function formatDistanceEs(meters: number): string {
  if (meters >= 1000) {
    return `${(meters / 1000).toFixed(meters >= 10_000 ? 0 : 1)} km`;
  }
  return `${Math.round(meters)} m`;
}

function formatDurationEs(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (h > 0) {
    return `${h} h ${m} min`;
  }
  if (m < 1) {
    return '< 1 min';
  }
  return `${m} min`;
}

function arrivalTimeLabel(secondsFromNow: number): string {
  const d = new Date(Date.now() + secondsFromNow * 1000);
  return d.toLocaleTimeString('es-VE', {
    hour: '2-digit',
    minute: '2-digit',
  });
}

/** Si `fitToCoordinates` falla en algún dispositivo, centramos con región derivada. */
function regionFromCoords(coords: RouteCoord[]): {
  latitude: number;
  longitude: number;
  latitudeDelta: number;
  longitudeDelta: number;
} {
  let minLat = Infinity;
  let maxLat = -Infinity;
  let minLng = Infinity;
  let maxLng = -Infinity;
  for (const c of coords) {
    minLat = Math.min(minLat, c.latitude);
    maxLat = Math.max(maxLat, c.latitude);
    minLng = Math.min(minLng, c.longitude);
    maxLng = Math.max(maxLng, c.longitude);
  }
  const midLat = (minLat + maxLat) / 2;
  const midLng = (minLng + maxLng) / 2;
  const latSpan = Math.max(maxLat - minLat, 0.006);
  const lngSpan = Math.max(maxLng - minLng, 0.006);

  return {
    latitude: midLat,
    longitude: midLng,
    latitudeDelta: Math.min(latSpan * 1.5, 2),
    longitudeDelta: Math.min(lngSpan * 1.5, 2),
  };
}

function subtitleFromNav(
  nav: DeliveryNavigationData | null,
  jobKind: 'order' | 'transfer',
): string {
  if (!nav) {
    return '';
  }
  const dest =
    nav.destination?.formatted_address?.split(',')[0]?.trim() ??
    nav.address_label?.split(',')[0]?.trim() ??
    (jobKind === 'transfer' ? 'Sucursal destino' : 'Punto de entrega');

  return `Tu ubicación → ${dest}`;
}

export default function DeliveryRouteScreen() {
  const params = useLocalSearchParams<{
    orderId: string;
    kind?: string | string[];
  }>();
  const orderIdParam = params.orderId;
  const kindParamRaw = params.kind;
  const kindStr = Array.isArray(kindParamRaw)
    ? kindParamRaw[0]
    : kindParamRaw;
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const windowH = Dimensions.get('window').height;
  const { token } = useAuth();
  const orderId = Number(orderIdParam);
  const jobKind = kindStr === 'transfer' ? 'transfer' : 'order';
  const mapRef = useRef<MapView>(null);
  const routeCoordsRef = useRef<RouteCoord[]>([]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [navData, setNavData] = useState<DeliveryNavigationData | null>(null);
  const [origin, setOrigin] = useState<{ lat: number; lng: number } | null>(null);
  const [routeCoords, setRouteCoords] = useState<RouteCoord[]>([]);
  const [usedDirectionsApi, setUsedDirectionsApi] = useState(false);
  const [distanceMeters, setDistanceMeters] = useState<number | null>(null);
  const [durationSeconds, setDurationSeconds] = useState<number | null>(null);
  const [completingDelivery, setCompletingDelivery] = useState(false);

  const isIosUi = Platform.OS === 'ios';

  const googleMapsKey = process.env.EXPO_PUBLIC_GOOGLE_MAPS_KEY?.trim() ?? '';
  /** En iOS (y Expo Go) Google Maps nativo suele fallar con claves restringidas; Apple Maps dibuja bien la polyline. */
  const useGoogleMapsTiles =
    Platform.OS === 'android' && googleMapsKey.length > 0;

  const expandedH = useMemo(
    () => Math.min(windowH * 0.78, windowH - insets.top - 16),
    [windowH, insets.top],
  );
  const collapsedH = useMemo(
    () => Math.min(windowH * 0.38, 340),
    [windowH],
  );
  const hideOffset = Math.max(0, expandedH - collapsedH);

  const sheetY = useRef(new Animated.Value(hideOffset)).current;
  const dragOriginY = useRef(hideOffset);

  const fitMapToCoords = useCallback(
    (coords: RouteCoord[]) => {
      const clean = sanitizeRouteCoords(coords);
      if (clean.length === 0) {
        return;
      }
      routeCoordsRef.current = clean;
      const bottomPad = collapsedH + insets.bottom + 24;
      const edgePadding = {
        top: insets.top + 100,
        right: 28,
        bottom: bottomPad,
        left: 28,
      };
      const apply = (): void => {
        const map = mapRef.current;
        if (!map) {
          return;
        }
        if (clean.length === 1) {
          map.animateToRegion(
            {
              latitude: clean[0].latitude,
              longitude: clean[0].longitude,
              latitudeDelta: 0.06,
              longitudeDelta: 0.06,
            },
            320,
          );
          return;
        }
        try {
          map.fitToCoordinates(clean, {
            edgePadding,
            animated: true,
          });
        } catch {
          map.animateToRegion(regionFromCoords(clean), 400);
        }
      };
      requestAnimationFrame(apply);
      setTimeout(apply, 450);
      setTimeout(apply, 950);
    },
    [collapsedH, insets.bottom, insets.top],
  );

  const snapSheet = useCallback(
    (to: number) => {
      dragOriginY.current = to;
      Animated.spring(sheetY, {
        toValue: to,
        useNativeDriver: false,
        friction: 9,
        tension: 65,
      }).start();
    },
    [sheetY],
  );

  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onMoveShouldSetPanResponder: (_, g) =>
          Math.abs(g.dy) > 6 && Math.abs(g.dy) > Math.abs(g.dx) * 0.7,
        onPanResponderGrant: () => {
          sheetY.stopAnimation((v: number) => {
            dragOriginY.current = v;
          });
        },
        onPanResponderMove: (_, { dy }) => {
          const next = Math.max(0, Math.min(hideOffset, dragOriginY.current + dy));
          sheetY.setValue(next);
        },
        onPanResponderRelease: (_, { dy, vy }) => {
          const raw = Math.max(0, Math.min(hideOffset, dragOriginY.current + dy));
          let snap = raw;
          if (vy > 0.6) {
            snap = hideOffset;
          } else if (vy < -0.6) {
            snap = 0;
          } else {
            snap = raw > hideOffset / 2 ? hideOffset : 0;
          }
          snapSheet(snap);
        },
      }),
    [hideOffset, sheetY, snapSheet],
  );

  const load = useCallback(async () => {
    if (!token || Number.isNaN(orderId) || orderId < 1) {
      setError('Ruta no válida.');
      setLoading(false);
      return;
    }

    setError(null);
    setLoading(true);
    setRouteCoords([]);
    setNavData(null);
    setOrigin(null);
    setUsedDirectionsApi(false);
    setDistanceMeters(null);
    setDurationSeconds(null);

    try {
      if (Platform.OS === 'web') {
        setError('El mapa de ruta solo está disponible en la app para Android o iPhone.');
        setLoading(false);
        return;
      }

      const servicesEnabled = await Location.hasServicesEnabledAsync();
      if (!servicesEnabled) {
        setError('Activa el GPS para ver la ruta hasta la entrega.');
        setLoading(false);
        return;
      }

      const perm = await Location.requestForegroundPermissionsAsync();
      if (perm.status !== Location.PermissionStatus.GRANTED) {
        setError('Necesitamos permiso de ubicación para trazar la ruta desde tu posición.');
        setLoading(false);
        return;
      }

      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });
      const o = {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      };
      setOrigin(o);

      const nav =
        jobKind === 'transfer'
          ? await fetchTransferNavigation(token, orderId, o)
          : await fetchDeliveryNavigation(token, orderId, o);
      setNavData(nav);

      let navForRoute = nav;
      const label = nav.address_label?.trim() ?? '';
      if (!nav.destination && label !== '' && googleMapsKey !== '') {
        const geo = await geocodeGoogleAddress(label, googleMapsKey);
        if (geo) {
          navForRoute = {
            ...nav,
            destination: {
              lat: geo.lat,
              lng: geo.lng,
              formatted_address: geo.formatted_address || label,
            },
          };
          setNavData(navForRoute);
        }
      }

      if (navForRoute.destination) {
        const dest = navForRoute.destination;
        const destCoord: RouteCoord = {
          latitude: dest.lat,
          longitude: dest.lng,
        };
        const originCoord: RouteCoord = { latitude: o.lat, longitude: o.lng };

        let poly: RouteCoord[] = straightLineRoute(originCoord, destCoord);
        let distM: number | null = null;
        let durS: number | null = null;
        let fromDirections = false;

        const serverRoute = navForRoute.route;
        if (
          serverRoute &&
          typeof serverRoute.encoded_polyline === 'string' &&
          serverRoute.encoded_polyline.length > 0
        ) {
          const decoded = decodeEncodedPolyline(serverRoute.encoded_polyline);
          if (decoded.length >= 2) {
            poly = decoded;
            fromDirections = true;
            distM =
              serverRoute.distance_meters > 0
                ? serverRoute.distance_meters
                : haversineDistanceMeters(o, {
                    lat: dest.lat,
                    lng: dest.lng,
                  });
            durS =
              serverRoute.duration_seconds > 0
                ? serverRoute.duration_seconds
                : estimateDrivingDurationSeconds(distM);
          }
        }

        if (!fromDirections) {
          const drivingMeta = await fetchDrivingRouteWithMetadata(
            o,
            { lat: dest.lat, lng: dest.lng },
            googleMapsKey,
          );

          if (drivingMeta !== null && drivingMeta.coordinates.length >= 2) {
            poly = drivingMeta.coordinates;
            fromDirections = true;
            distM =
              drivingMeta.distanceMeters > 0
                ? drivingMeta.distanceMeters
                : haversineDistanceMeters(o, { lat: dest.lat, lng: dest.lng });
            durS =
              drivingMeta.durationSeconds > 0
                ? drivingMeta.durationSeconds
                : estimateDrivingDurationSeconds(distM ?? 0);
          } else {
            poly = straightLineRoute(originCoord, destCoord);
            distM = haversineDistanceMeters(o, { lat: dest.lat, lng: dest.lng });
            durS = estimateDrivingDurationSeconds(distM);
          }
        }

        setUsedDirectionsApi(fromDirections);
        setDistanceMeters(distM);
        setDurationSeconds(durS);
        const finalPoly = sanitizeRouteCoords(poly);
        routeCoordsRef.current = finalPoly;
        setRouteCoords(finalPoly);
        fitMapToCoords(finalPoly);
      } else {
        setDistanceMeters(null);
        setDurationSeconds(null);
        const only = sanitizeRouteCoords([
          { latitude: o.lat, longitude: o.lng },
        ]);
        routeCoordsRef.current = only;
        setRouteCoords([]);
        fitMapToCoords(only);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al cargar la ruta.');
    } finally {
      setLoading(false);
    }
  }, [token, orderId, jobKind, googleMapsKey, fitMapToCoords]);

  /** Pedido (API de órdenes); los traslados incluyen `transfer_id` en la respuesta. */
  const canMarkOrderDelivered =
    navData != null && navData.transfer_id == null;

  const handleMarkDelivered = useCallback(async () => {
    if (!token || completingDelivery || navData?.transfer_id != null) {
      return;
    }
    const picked = await pickDeliveryEvidenceImage();
    if (picked === null) {
      return;
    }
    setCompletingDelivery(true);
    try {
      const res = await completeDeliveryOrder(
        token,
        orderId,
        picked.uri,
        picked.mimeType,
      );
      Alert.alert('Entrega registrada', res.message, [
        { text: 'OK', onPress: () => router.back() },
      ]);
    } catch (e) {
      Alert.alert(
        'No se pudo completar',
        e instanceof Error ? e.message : 'Intenta de nuevo.',
      );
    } finally {
      setCompletingDelivery(false);
    }
  }, [token, orderId, completingDelivery, router, navData?.transfer_id]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (Platform.OS === 'web' || loading || error) {
      return;
    }

    let subscription: { remove: () => void } | undefined;

    (async () => {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== Location.PermissionStatus.GRANTED) {
        return;
      }

      subscription = await Location.watchPositionAsync(
        {
          accuracy: Location.Accuracy.Balanced,
          distanceInterval: 25,
          timeInterval: 10_000,
        },
        (loc) => {
          setOrigin({
            lat: loc.coords.latitude,
            lng: loc.coords.longitude,
          });
        },
      );
    })();

    return () => {
      subscription?.remove();
    };
  }, [loading, error]);

  const destinationCoordForMap: LatLng | null =
    navData?.destination != null
      ? {
          latitude: navData.destination.lat,
          longitude: navData.destination.lng,
        }
      : null;

  const tripTitle =
    navData?.order_number ??
    (jobKind === 'transfer'
      ? `Traslado ${navData?.transfer_id != null ? `#${navData.transfer_id}` : `#${orderId}`}`
      : `Pedido ${navData?.order_id != null ? `#${navData.order_id}` : `#${orderId}`}`);

  const tripSubtitle = subtitleFromNav(navData, jobKind);

  if (Platform.OS === 'web') {
    return (
      <View style={styles.webRoot}>
        <StatusBar style="light" />
        <Text style={styles.webMsg}>
          El mapa de ruta solo está disponible en Android o iPhone.
        </Text>
        <Pressable onPress={() => router.back()} style={styles.webBack}>
          <Text style={styles.webBackText}>Volver</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <StatusBar style="light" />

      <MapView
        ref={mapRef}
        provider={useGoogleMapsTiles ? PROVIDER_GOOGLE : undefined}
        style={StyleSheet.absoluteFill}
        initialRegion={DEFAULT_REGION}
        mapType={Platform.OS === 'ios' ? 'mutedStandard' : 'standard'}
        onMapReady={() => {
          fitMapToCoords(routeCoordsRef.current);
        }}
        showsUserLocation
        showsMyLocationButton={false}
        showsCompass={false}
      >
        {destinationCoordForMap ? (
          <Marker
            coordinate={destinationCoordForMap}
            title={jobKind === 'transfer' ? 'Sucursal destino' : 'Entrega'}
            description={
              navData?.destination?.formatted_address ?? navData?.address_label
            }
            pinColor="#34C759"
          />
        ) : null}
        {routeCoords.length >= 2 ? (
          <Polyline
            coordinates={routeCoords}
            geodesic
            lineCap="round"
            lineJoin="round"
            strokeColor={ROUTE_STROKE}
            strokeWidth={Platform.OS === 'ios' ? 7 : 9}
            zIndex={99}
          />
        ) : null}
      </MapView>

      <View
        pointerEvents="box-none"
        style={[styles.floatingTop, { paddingTop: insets.top + 8 }]}
      >
        <Pressable
          accessibilityLabel="Cerrar"
          android_ripple={{ color: 'rgba(255,255,255,0.2)' }}
          hitSlop={10}
          onPress={() => router.back()}
          style={({ pressed }) => [
            styles.roundBtn,
            isIosUi && styles.roundBtnIos,
            pressed && styles.roundBtnPressed,
          ]}
        >
          <Ionicons
            color={isIosUi ? IOS_MAP_SHEET.topIcon : '#fff'}
            name="close"
            size={26}
          />
        </Pressable>
        <View style={styles.topRightCluster}>
          <Pressable
            accessibilityLabel="Centrar mapa en la ruta"
            android_ripple={{ color: 'rgba(255,255,255,0.2)' }}
            hitSlop={8}
            onPress={() =>
              routeCoords.length >= 2 && fitMapToCoords(routeCoords)
            }
            style={({ pressed }) => [
              styles.roundBtn,
              isIosUi && styles.roundBtnIos,
              pressed && styles.roundBtnPressed,
            ]}
          >
            <Ionicons
              color={isIosUi ? IOS_MAP_SHEET.topIcon : '#fff'}
              name="locate-outline"
              size={22}
            />
          </Pressable>
          {navData?.destination ? (
            <Pressable
              accessibilityLabel="Abrir en Google Maps"
              android_ripple={{ color: 'rgba(255,255,255,0.2)' }}
              hitSlop={8}
              onPress={() =>
                openGoogleMapsDirections({
                  origin: origin ?? undefined,
                  destinationLabel: navData.address_label,
                  destinationCoords: navData.destination
                    ? {
                        lat: navData.destination.lat,
                        lng: navData.destination.lng,
                      }
                    : undefined,
                })
              }
              style={({ pressed }) => [
                styles.roundBtn,
                isIosUi && styles.roundBtnIos,
                pressed && styles.roundBtnPressed,
              ]}
            >
              <Ionicons
                color={isIosUi ? IOS_MAP_SHEET.topIcon : '#fff'}
                name="navigate-outline"
                size={22}
              />
            </Pressable>
          ) : null}
        </View>
      </View>

      <Animated.View
        pointerEvents="box-none"
        style={[
          styles.sheetOuter,
          {
            height: expandedH,
            transform: [{ translateY: sheetY }],
          },
        ]}
      >
        <View
          style={[
            styles.sheetCard,
            {
              borderColor: isIosUi ? SHEET_BORDER_IOS : SHEET_BORDER,
              paddingBottom: insets.bottom,
              backgroundColor: isIosUi ? 'transparent' : SHEET_BG,
            },
          ]}
        >
          {isIosUi ? (
            <BlurView
              intensity={88}
              style={StyleSheet.absoluteFill}
              tint="systemChromeMaterialLight"
            />
          ) : null}

          <View style={styles.sheetContent}>
            <View {...panResponder.panHandlers} style={styles.handleHit}>
              <View style={[styles.handleBar, isIosUi && styles.handleBarIos]} />
            </View>

            {!isIosUi ? (
              <View style={styles.sheetIconRow}>
                <View style={styles.sheetIconLeft}>
                  <View style={styles.iconGhost}>
                    <Ionicons
                      color="rgba(255,255,255,0.5)"
                      name="car-sport-outline"
                      size={20}
                    />
                  </View>
                  <View style={styles.iconGhost}>
                    <Ionicons
                      color="rgba(255,255,255,0.5)"
                      name="flag-outline"
                      size={20}
                    />
                  </View>
                </View>
                <View style={styles.iconGhost}>
                  <Ionicons
                    color="rgba(255,255,255,0.5)"
                    name="create-outline"
                    size={20}
                  />
                </View>
              </View>
            ) : null}

            <ScrollView
              style={styles.sheetScroll}
              contentContainerStyle={styles.sheetScrollContent}
              keyboardShouldPersistTaps="handled"
              nestedScrollEnabled
              showsVerticalScrollIndicator={!isIosUi}
            >
            {loading ? (
              <View style={styles.sheetBody}>
                <ActivityIndicator color={colors.primary} size="small" />
                <Text
                  style={[
                    styles.sheetMuted,
                    isIosUi && { color: IOS_MAP_SHEET.muted },
                  ]}
                >
                  Calculando ruta…
                </Text>
              </View>
            ) : null}

            {error && !loading ? (
              <View style={styles.sheetBody}>
                <Text style={styles.sheetError}>{error}</Text>
                <Pressable
                  onPress={() => void load()}
                  style={({ pressed }) => [pressed && { opacity: 0.75 }]}
                >
                  <Text style={styles.retry}>Reintentar</Text>
                </Pressable>
              </View>
            ) : null}

            {!loading && !error && navData ? (
              <View style={styles.sheetBody}>
                <Text
                  style={[
                    styles.sheetTitle,
                    isIosUi && { color: IOS_MAP_SHEET.title },
                  ]}
                >
                  {tripTitle}
                </Text>
                <Text
                  style={[
                    styles.sheetSubtitle,
                    isIosUi && { color: IOS_MAP_SHEET.subtitle },
                  ]}
                  numberOfLines={2}
                >
                  {tripSubtitle}
                </Text>

                {distanceMeters !== null && durationSeconds !== null ? (
                  <View style={styles.metricsRow}>
                    <View style={styles.metricCell}>
                      <Text
                        style={[
                          styles.metricValue,
                          isIosUi && { color: IOS_MAP_SHEET.metric },
                        ]}
                      >
                        {formatDistanceEs(distanceMeters)}
                      </Text>
                      <Text
                        style={[
                          styles.metricLabel,
                          isIosUi && { color: IOS_MAP_SHEET.metricLabel },
                        ]}
                      >
                        Distancia
                      </Text>
                    </View>
                    <View style={styles.metricCell}>
                      <Text
                        style={[
                          styles.metricValue,
                          isIosUi && { color: IOS_MAP_SHEET.metric },
                        ]}
                      >
                        {formatDurationEs(durationSeconds)}
                      </Text>
                      <Text
                        style={[
                          styles.metricLabel,
                          isIosUi && { color: IOS_MAP_SHEET.metricLabel },
                        ]}
                      >
                        Tiempo
                      </Text>
                    </View>
                    <View style={styles.metricCell}>
                      <Text
                        style={[
                          styles.metricValue,
                          isIosUi && { color: IOS_MAP_SHEET.metric },
                        ]}
                      >
                        {arrivalTimeLabel(durationSeconds)}
                      </Text>
                      <Text
                        style={[
                          styles.metricLabel,
                          isIosUi && { color: IOS_MAP_SHEET.metricLabel },
                        ]}
                      >
                        Llegada ~
                      </Text>
                    </View>
                  </View>
                ) : null}

                <View
                  style={[
                    styles.sheetDivider,
                    isIosUi && { backgroundColor: IOS_MAP_SHEET.divider },
                  ]}
                />

                <View style={styles.arrivalRow}>
                  <Text
                    style={[
                      styles.arrivalLeft,
                      isIosUi && { color: IOS_MAP_SHEET.subtitle },
                    ]}
                  >
                    Rango estimado
                  </Text>
                  <View style={styles.arrivalRight}>
                    <Ionicons
                      color={
                        isIosUi
                          ? 'rgba(60,60,67,0.45)'
                          : 'rgba(255,255,255,0.45)'
                      }
                      name="time-outline"
                      size={18}
                    />
                    <Text
                      style={[
                        styles.arrivalRightText,
                        isIosUi && { color: IOS_MAP_SHEET.lane },
                      ]}
                    >
                      {durationSeconds !== null
                        ? `± ${formatDurationEs(Math.max(120, Math.round(durationSeconds * 0.12)))}`
                        : '—'}
                    </Text>
                  </View>
                </View>

                <View style={styles.laneRow}>
                  <Ionicons
                    color={
                      isIosUi ? 'rgba(60,60,67,0.4)' : 'rgba(255,255,255,0.4)'
                    }
                    name="map-outline"
                    size={18}
                  />
                  <Text
                    style={[
                      styles.laneText,
                      isIosUi && { color: IOS_MAP_SHEET.lane },
                    ]}
                  >
                    {usedDirectionsApi
                      ? 'Ruta por calles (Google)'
                      : 'Línea recta · estimación'}
                  </Text>
                </View>

                <View
                  style={[
                    styles.sheetDivider,
                    isIosUi && { backgroundColor: IOS_MAP_SHEET.divider },
                    { marginTop: 6, marginBottom: 10 },
                  ]}
                />

                {!navData.destination && navData.geocoding_configured ? (
                  <Text
                    style={[
                      styles.sheetWarn,
                      isIosUi && { color: IOS_MAP_SHEET.warn },
                    ]}
                  >
                    No se pudo ubicar la dirección en el mapa. Usa Abrir en
                    Google Maps.
                  </Text>
                ) : null}
                {navData.destination_from_coordinates ? (
                  <Text
                    style={[
                      styles.sheetMuted,
                      isIosUi && { color: IOS_MAP_SHEET.muted },
                    ]}
                  >
                    Destino por coordenadas exactas del pedido.
                  </Text>
                ) : null}
                {!navData.geocoding_configured &&
                !navData.destination_from_coordinates ? (
                  <Text
                    style={[
                      styles.sheetWarn,
                      isIosUi && { color: IOS_MAP_SHEET.warn },
                    ]}
                  >
                    Falta geocodificación en servidor. Navega con Google Maps.
                  </Text>
                ) : null}
                {navData.destination &&
                !usedDirectionsApi &&
                googleMapsKey === '' ? (
                  <Text
                    style={[
                      styles.sheetWarn,
                      isIosUi && { color: IOS_MAP_SHEET.warn },
                    ]}
                  >
                    Añade EXPO_PUBLIC_GOOGLE_MAPS_KEY para ruta por calles en el
                    mapa.
                  </Text>
                ) : null}
                {navData.destination &&
                !usedDirectionsApi &&
                googleMapsKey !== '' ? (
                  <Text
                    style={[
                      styles.sheetWarn,
                      isIosUi && { color: IOS_MAP_SHEET.warn },
                    ]}
                  >
                    No se obtuvo ruta por calles; se muestra línea recta y tiempo
                    estimado.
                  </Text>
                ) : null}
              </View>
            ) : null}
            </ScrollView>

            {!loading && !error && navData ? (
              <View
                style={[
                  styles.sheetFooter,
                  isIosUi && styles.sheetFooterIos,
                ]}
              >
                {canMarkOrderDelivered ? (
                  <>
                    <Text
                      style={[
                        styles.evidenceHint,
                        isIosUi && { color: IOS_MAP_SHEET.muted },
                      ]}
                    >
                      Al confirmar se abrirá la cámara (o la galería) para
                      fotografiar la entrega en destino.
                    </Text>
                    <Pressable
                      accessibilityLabel="Marcar pedido como entregado"
                      disabled={completingDelivery}
                      onPress={() => void handleMarkDelivered()}
                      style={({ pressed }) => [
                        styles.deliveredCta,
                        isIosUi && styles.deliveredCtaIos,
                        pressed && !completingDelivery && { opacity: 0.92 },
                        completingDelivery && { opacity: 0.75 },
                      ]}
                    >
                      {completingDelivery ? (
                        <ActivityIndicator
                          color={IOS_MAP_SHEET.deliveredText}
                          size="small"
                        />
                      ) : (
                        <>
                          <Ionicons
                            color={IOS_MAP_SHEET.deliveredText}
                            name="checkmark-circle"
                            size={22}
                          />
                          <Text style={styles.deliveredCtaText}>
                            Pedido entregado
                          </Text>
                        </>
                      )}
                    </Pressable>
                  </>
                ) : null}
                <Pressable
                  onPress={() =>
                    openGoogleMapsDirections({
                      origin: origin ?? undefined,
                      destinationLabel: navData.address_label,
                      destinationCoords: navData.destination
                        ? {
                            lat: navData.destination.lat,
                            lng: navData.destination.lng,
                          }
                        : undefined,
                    })
                  }
                  style={({ pressed }) => [
                    styles.mapsCta,
                    isIosUi && styles.mapsCtaIos,
                    pressed && { opacity: 0.9 },
                  ]}
                >
                  <Ionicons
                    color={isIosUi ? colors.primary : '#0a1628'}
                    name="open-outline"
                    size={20}
                  />
                  <Text
                    style={[
                      styles.mapsCtaText,
                      isIosUi && { color: colors.primary },
                    ]}
                  >
                    Navegar en Google Maps
                  </Text>
                </Pressable>
              </View>
            ) : null}
          </View>
        </View>
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#000',
  },
  webRoot: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
    backgroundColor: '#0a1628',
  },
  webMsg: {
    ...liquidType.body,
    color: 'rgba(255,255,255,0.85)',
    textAlign: 'center',
    marginBottom: 20,
  },
  webBack: {
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  webBackText: {
    ...liquidType.body,
    color: colors.primary,
    fontWeight: '600',
  },
  floatingTop: {
    position: 'absolute',
    left: 0,
    right: 0,
    top: 0,
    zIndex: 4,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingHorizontal: 14,
  },
  roundBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(28,28,30,0.52)',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(255,255,255,0.14)',
  },
  roundBtnIos: {
    backgroundColor: IOS_MAP_SHEET.topBtnBg,
    borderColor: IOS_MAP_SHEET.topBtnBorder,
  },
  roundBtnPressed: {
    opacity: 0.82,
    transform: [{ scale: 0.97 }],
  },
  topRightCluster: {
    flexDirection: 'row',
    gap: 10,
  },
  sheetOuter: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    zIndex: 3,
  },
  sheetContent: {
    flex: 1,
    zIndex: 1,
  },
  sheetScroll: {
    flex: 1,
  },
  sheetScrollContent: {
    paddingBottom: 12,
  },
  sheetFooter: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 4,
    gap: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255,255,255,0.14)',
    zIndex: 2,
  },
  sheetFooterIos: {
    borderTopColor: 'rgba(0,0,0,0.1)',
  },
  sheetCard: {
    flex: 1,
    borderTopLeftRadius: liquidRadius.cardProminent,
    borderTopRightRadius: liquidRadius.cardProminent,
    borderWidth: StyleSheet.hairlineWidth,
    borderBottomWidth: 0,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.35,
    shadowRadius: 12,
    elevation: 16,
  },
  handleHit: {
    paddingTop: 10,
    paddingBottom: 8,
    alignItems: 'center',
  },
  handleBar: {
    width: 40,
    height: 5,
    borderRadius: 3,
    backgroundColor: 'rgba(255,255,255,0.22)',
  },
  handleBarIos: {
    backgroundColor: 'rgba(0,0,0,0.18)',
  },
  sheetIconRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingBottom: 6,
  },
  sheetIconLeft: {
    flexDirection: 'row',
    gap: 10,
  },
  iconGhost: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.06)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sheetBody: {
    paddingHorizontal: 20,
    paddingBottom: 20,
    gap: 8,
  },
  sheetTitle: {
    fontSize: 26,
    fontWeight: '700',
    color: 'rgba(255,255,255,0.96)',
    letterSpacing: -0.4,
    marginBottom: 4,
  },
  sheetSubtitle: {
    ...liquidType.body,
    fontSize: 15,
    color: 'rgba(255,255,255,0.52)',
    lineHeight: 21,
    marginBottom: 18,
  },
  metricsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 16,
    paddingHorizontal: 2,
  },
  metricCell: {
    flex: 1,
    alignItems: 'center',
  },
  metricValue: {
    fontSize: 17,
    fontWeight: '600',
    color: 'rgba(255,255,255,0.94)',
    marginBottom: 4,
  },
  metricLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: 'rgba(255,255,255,0.38)',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  sheetDivider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: 'rgba(255,255,255,0.1)',
    marginBottom: 14,
  },
  arrivalRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  arrivalLeft: {
    fontSize: 15,
    color: 'rgba(255,255,255,0.72)',
    fontWeight: '500',
  },
  arrivalRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  arrivalRightText: {
    fontSize: 15,
    fontWeight: '600',
    color: 'rgba(255,255,255,0.55)',
  },
  laneRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 16,
  },
  laneText: {
    fontSize: 14,
    color: 'rgba(255,255,255,0.48)',
    fontWeight: '500',
  },
  sheetMuted: {
    ...liquidType.footnote,
    color: 'rgba(255,255,255,0.55)',
    marginTop: 4,
  },
  sheetWarn: {
    ...liquidType.footnote,
    color: 'rgba(255,196,77,0.95)',
    marginBottom: 4,
    lineHeight: 20,
  },
  sheetError: {
    ...liquidType.body,
    color: '#ffb4b4',
    marginBottom: 8,
  },
  retry: {
    ...liquidType.body,
    color: colors.primary,
    fontWeight: '600',
  },
  mapsCta: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: colors.primary,
    paddingVertical: 15,
    borderRadius: liquidRadius.card,
    marginTop: 4,
  },
  mapsCtaIos: {
    backgroundColor: 'rgba(14,148,154,0.12)',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(14,148,154,0.35)',
  },
  mapsCtaText: {
    ...liquidType.body,
    color: '#0a1628',
    fontWeight: '700',
    fontSize: 16,
  },
  evidenceHint: {
    ...liquidType.footnote,
    fontSize: 12,
    lineHeight: 17,
    color: 'rgba(255,255,255,0.5)',
    marginBottom: 4,
  },
  deliveredCta: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: '#30D158',
    paddingVertical: 16,
    borderRadius: 14,
    marginTop: 4,
    marginBottom: 4,
    minHeight: 52,
    elevation: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
  },
  deliveredCtaIos: {
    backgroundColor: IOS_MAP_SHEET.deliveredBg,
    borderRadius: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.18,
    shadowRadius: 6,
  },
  deliveredCtaText: {
    fontSize: 17,
    fontWeight: '600',
    color: IOS_MAP_SHEET.deliveredText,
    letterSpacing: -0.3,
  },
});

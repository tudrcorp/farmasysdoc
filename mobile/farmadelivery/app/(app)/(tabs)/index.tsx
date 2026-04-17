import { useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { GlassCard } from '../../../components/liquid/GlassCard';
import { LiquidGlassBackground } from '../../../components/liquid/LiquidGlassBackground';
import { PendingOrderRow } from '../../../components/PendingOrderRow';
import { useAuth } from '../../../contexts/AuthContext';
import {
  deliveryJobKey,
  fetchPendingOrders,
  takeDeliveryOrder,
  takeDeliveryTransfer,
  type PendingJob,
} from '../../../lib/api';
import { ensureGpsReadyForTakeOrder } from '../../../lib/ensureGpsForTakeOrder';
import { colors } from '../../../lib/colors';
import {
  liquidCanvas,
  liquidHairline,
  liquidRadius,
  liquidSpace,
  liquidType,
} from '../../../lib/liquidHigTheme';

/** Tab bar anclada abajo: alto de ítems + home indicator + aire sobre la tarjeta. */
function tabBarContentInset(bottomInset: number): number {
  const bar = 68;
  return bar + bottomInset + 24;
}

export default function PendingOrdersScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { token, user } = useAuth();
  const [jobs, setJobs] = useState<PendingJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [takingKey, setTakingKey] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) {
      return;
    }
    setError(null);
    const data = await fetchPendingOrders(token);
    setJobs(data);
  }, [token]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (!token) {
        setLoading(false);
        return;
      }
      setLoading(true);
      try {
        await load();
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'Error al cargar.');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [token, load]);

  async function onTakeJob(job: PendingJob) {
    if (!token) {
      return;
    }

    const gps = await ensureGpsReadyForTakeOrder();
    if (!gps.ok) {
      Alert.alert('Ubicación requerida', gps.message, [
        { text: 'Entendido', style: 'cancel' },
        { text: 'Ajustes', onPress: () => void Linking.openSettings() },
      ]);
      return;
    }

    const key = deliveryJobKey(job);
    setTakingKey(key);
    try {
      if (job.kind === 'transfer') {
        await takeDeliveryTransfer(token, job.id);
      } else {
        await takeDeliveryOrder(token, job.id);
      }
      await load();
      router.push(
        `/delivery-route/${job.id}?kind=${encodeURIComponent(job.kind)}`,
      );
    } catch (e) {
      Alert.alert(
        job.kind === 'transfer'
          ? 'No se pudo tomar el traslado'
          : 'No se pudo tomar el pedido',
        e instanceof Error ? e.message : 'Error inesperado.',
      );
    } finally {
      setTakingKey(null);
    }
  }

  async function onRefresh() {
    if (!token) {
      return;
    }
    setRefreshing(true);
    try {
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al actualizar.');
    } finally {
      setRefreshing(false);
    }
  }

  const bottomPad = tabBarContentInset(insets.bottom);

  return (
    <View style={styles.root}>
      <LiquidGlassBackground />
      <StatusBar style="light" />

      <View
        style={[
          styles.topBar,
          {
            paddingTop: insets.top + 8,
            paddingHorizontal: liquidSpace.screenH,
            paddingBottom: 14,
          },
        ]}
      >
        <Text numberOfLines={1} style={styles.navTitle}>
          Pendientes
        </Text>
        <Text numberOfLines={1} style={styles.navSubtitle}>
          {user?.name ? `Hola, ${user.name}` : 'Reparto Farmadoc'}
        </Text>
      </View>

      <ScrollView
        contentContainerStyle={[
          styles.scrollContent,
          { paddingBottom: bottomPad },
        ]}
        style={styles.scrollTransparent}
        refreshControl={
          <RefreshControl
            colors={[colors.primary]}
            onRefresh={onRefresh}
            progressBackgroundColor="rgba(0,0,0,0.4)"
            refreshing={refreshing}
            tintColor="#ffffff"
          />
        }
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.hero}>
          <Text style={styles.largeTitle}>Pendientes</Text>
          <Text style={styles.heroBody}>
            Pool de tareas disponibles: pedidos (cliente o aliado) y traslados.
            Las etiquetas indican tipo y si sigue pendiente. El botón amarillo
            toma la tarea (GPS activo). Luego la verás en Gestión.
          </Text>
          {!loading && !error ? (
            <View style={styles.countPill}>
              <Text style={styles.countPillText}>
                {jobs.length === 1
                  ? '1 pendiente'
                  : `${jobs.length} pendientes`}
              </Text>
            </View>
          ) : null}
        </View>

        {loading ? (
          <View style={styles.centerBox}>
            <ActivityIndicator color={colors.primary} size="large" />
            <Text style={styles.hint}>Cargando pedidos…</Text>
          </View>
        ) : null}

        {error && !loading ? (
          <GlassCard
            contentStyle={styles.errorInner}
            radius={liquidRadius.card}
            style={styles.errorCard}
          >
            <Text style={styles.errorText}>{error}</Text>
            <Pressable
              onPress={async () => {
                setLoading(true);
                setError(null);
                try {
                  await load();
                } catch (e) {
                  setError(e instanceof Error ? e.message : 'Error al cargar.');
                } finally {
                  setLoading(false);
                }
              }}
              style={({ pressed }) => [
                styles.retryBtn,
                pressed && { opacity: 0.7 },
              ]}
            >
              <Text style={styles.retryLabel}>Reintentar</Text>
            </Pressable>
          </GlassCard>
        ) : null}

        {!loading && !error && jobs.length === 0 ? (
          <GlassCard
            contentStyle={styles.emptyInner}
            radius={liquidRadius.card}
            style={styles.emptyCard}
          >
            <Text style={styles.emptyTitle}>No hay tareas pendientes</Text>
            <Text style={styles.emptyBody}>
              Aparecerán aquí los pedidos con entrega y los traslados de
              inventario en estado pendiente.
            </Text>
          </GlassCard>
        ) : null}

        {!loading && !error && jobs.length > 0 ? (
          <View style={styles.listSection}>
            <Text style={styles.sectionLabel}>DISPONIBLES PARA TOMAR</Text>
            {jobs.map((job) => (
              <GlassCard
                key={deliveryJobKey(job)}
                contentStyle={{ padding: 0 }}
                radius={liquidRadius.card}
                style={styles.orderCard}
              >
                <PendingOrderRow
                  job={job}
                  variant="pending"
                  onOpenDetail={() =>
                    job.kind === 'transfer'
                      ? router.push(`/(app)/transfer/${job.id}`)
                      : router.push(`/(app)/order/${job.id}`)
                  }
                  onPrimaryPress={() => void onTakeJob(job)}
                  primaryLoading={takingKey === deliveryJobKey(job)}
                />
              </GlassCard>
            ))}
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: liquidCanvas.background,
  },
  topBar: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: liquidHairline.separator,
  },
  navTitle: {
    ...liquidType.navTitle,
  },
  navSubtitle: {
    ...liquidType.navSubtitle,
  },
  scrollContent: {
    paddingTop: 4,
  },
  scrollTransparent: {
    backgroundColor: 'transparent',
  },
  hero: {
    paddingHorizontal: liquidSpace.screenHLoose,
    marginBottom: 22,
  },
  largeTitle: {
    ...liquidType.largeTitle,
    marginBottom: 10,
  },
  heroBody: {
    ...liquidType.bodySecondary,
    fontSize: 16,
    marginBottom: 14,
  },
  countPill: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: liquidRadius.pill,
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(255,255,255,0.22)',
  },
  countPillText: {
    ...liquidType.sectionLabel,
    fontSize: 12,
    letterSpacing: 0.6,
    color: 'rgba(252,228,34,0.95)',
  },
  centerBox: {
    paddingVertical: 40,
    alignItems: 'center',
    gap: 14,
  },
  hint: {
    ...liquidType.footnote,
    fontSize: 15,
  },
  errorCard: {
    marginHorizontal: liquidSpace.screenH,
    marginBottom: 16,
  },
  errorInner: {
    padding: 18,
  },
  errorText: {
    fontSize: 15,
    color: 'rgba(255,180,180,0.98)',
    marginBottom: 14,
    lineHeight: 22,
  },
  retryBtn: {
    alignSelf: 'flex-start',
    paddingVertical: 6,
  },
  retryLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.primary,
  },
  emptyCard: {
    marginHorizontal: liquidSpace.screenH,
  },
  emptyInner: {
    padding: liquidSpace.cardPadLg,
  },
  emptyTitle: {
    ...liquidType.title3,
    marginBottom: 10,
  },
  emptyBody: {
    ...liquidType.footnote,
    fontSize: 15,
    lineHeight: 22,
    color: 'rgba(255,255,255,0.58)',
  },
  listSection: {
    paddingHorizontal: liquidSpace.cardPad,
  },
  sectionLabel: {
    ...liquidType.sectionLabel,
    marginBottom: 12,
    marginLeft: 4,
  },
  orderCard: {
    marginBottom: 14,
  },
});

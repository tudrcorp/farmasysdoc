import { useLocalSearchParams, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { GlassCard } from '../../../components/liquid/GlassCard';
import { LiquidGlassBackground } from '../../../components/liquid/LiquidGlassBackground';
import { useAuth } from '../../../contexts/AuthContext';
import { fetchTransferDetail, type TransferDetail } from '../../../lib/api';
import { colors } from '../../../lib/colors';
import {
  liquidCanvas,
  liquidHairline,
  liquidRadius,
  liquidSpace,
  liquidType,
} from '../../../lib/liquidHigTheme';

export default function TransferDetailScreen() {
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { token } = useAuth();
  const transferId = Number(idParam);
  const [detail, setDetail] = useState<TransferDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token || Number.isNaN(transferId) || transferId < 1) {
      return;
    }
    setError(null);
    const data = await fetchTransferDetail(token, transferId);
    setDetail(data);
  }, [token, transferId]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (!token || Number.isNaN(transferId) || transferId < 1) {
        setLoading(false);
        setError('Traslado no válido.');
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
  }, [token, transferId, load]);

  function branchBlock(
    label: string,
    b: TransferDetail['from_branch'] | TransferDetail['to_branch'],
  ) {
    if (!b) {
      return null;
    }
    const line = [b.address, b.city, b.state].filter(Boolean).join(', ');
    return (
      <View style={styles.block}>
        <Text style={styles.blockLabel}>{label}</Text>
        <Text style={styles.blockTitle}>{b.name ?? '—'}</Text>
        {line ? <Text style={styles.blockBody}>{line}</Text> : null}
        {b.phone ? <Text style={styles.blockBody}>{b.phone}</Text> : null}
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <LiquidGlassBackground />
      <StatusBar style="light" />

      <View
        style={[
          styles.nav,
          {
            paddingTop: insets.top + 6,
            paddingBottom: 12,
            paddingHorizontal: 16,
          },
        ]}
      >
        <Pressable
          accessibilityRole="button"
          android_ripple={{ color: 'rgba(255,255,255,0.12)' }}
          hitSlop={12}
          onPress={() => router.back()}
          style={({ pressed }) => [styles.backBtn, pressed && { opacity: 0.6 }]}
        >
          <Text style={styles.backChevron}>‹</Text>
          <Text style={styles.backLabel}>Pendientes</Text>
        </Pressable>
      </View>

      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          { paddingBottom: insets.bottom + 28 },
        ]}
        showsVerticalScrollIndicator={false}
        style={styles.scrollTransparent}
      >
        {loading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} size="large" />
            <Text style={styles.hint}>Cargando traslado…</Text>
          </View>
        ) : null}

        {error && !loading ? (
          <GlassCard
            contentStyle={styles.errorPad}
            radius={liquidRadius.card}
            style={styles.cardMargin}
          >
            <Text style={styles.errorText}>{error}</Text>
            <Pressable
              onPress={async () => {
                setLoading(true);
                setError(null);
                try {
                  await load();
                } catch (e) {
                  setError(e instanceof Error ? e.message : 'Error.');
                } finally {
                  setLoading(false);
                }
              }}
              style={({ pressed }) => [pressed && { opacity: 0.7 }]}
            >
              <Text style={styles.retry}>Reintentar</Text>
            </Pressable>
          </GlassCard>
        ) : null}

        {!loading && !error && detail ? (
          <>
            <GlassCard
              contentStyle={styles.heroPad}
              radius={liquidRadius.cardProminent}
              style={styles.cardMargin}
            >
              <Text style={styles.heroNumber}>{detail.code}</Text>
              <View style={styles.badgeRow}>
                <View style={styles.statusBadge}>
                  <Text style={styles.statusBadgeText}>{detail.status_label}</Text>
                </View>
              </View>
              <Text style={styles.caption}>
                Origen → destino. Usa «Tomar» en la lista para iniciar el envío y
                ver la ruta en el mapa.
              </Text>
            </GlassCard>

            <GlassCard
              contentStyle={styles.cardInner}
              radius={liquidRadius.card}
              style={styles.cardMargin}
            >
              {branchBlock('Origen (envía)', detail.from_branch)}
              <View style={styles.divider} />
              {branchBlock('Destino (recibe)', detail.to_branch)}
            </GlassCard>

            <GlassCard
              contentStyle={styles.cardInner}
              radius={liquidRadius.card}
              style={styles.cardMargin}
            >
              <Text style={styles.sectionTitle}>Ítems</Text>
              {detail.items.map((item, idx) => (
                <View
                  key={item.id}
                  style={[
                    styles.itemRow,
                    idx < detail.items.length - 1 && styles.itemRowBorder,
                  ]}
                >
                  <Text style={styles.itemName}>{item.product_name}</Text>
                  <Text style={styles.itemQty}>{item.quantity}</Text>
                </View>
              ))}
            </GlassCard>
          </>
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
  nav: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: liquidHairline.separator,
  },
  backBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    paddingVertical: 8,
    paddingHorizontal: 4,
  },
  backChevron: {
    fontSize: 28,
    color: '#fff',
    marginRight: 2,
    marginTop: -2,
  },
  backLabel: {
    ...liquidType.body,
    color: '#fff',
    fontWeight: '600',
  },
  scroll: {
    paddingHorizontal: liquidSpace.screenHLoose,
    paddingTop: 8,
  },
  scrollTransparent: {
    backgroundColor: 'transparent',
  },
  center: {
    paddingVertical: 40,
    alignItems: 'center',
    gap: 12,
  },
  hint: {
    ...liquidType.bodySecondary,
  },
  cardMargin: {
    marginBottom: 16,
  },
  errorPad: { padding: 18 },
  errorText: { ...liquidType.body, color: '#ffb4b4', marginBottom: 12 },
  retry: { ...liquidType.body, color: colors.primary, fontWeight: '600' },
  heroPad: { padding: 20 },
  heroNumber: {
    ...liquidType.title2,
    marginBottom: 10,
  },
  badgeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  statusBadgeText: {
    ...liquidType.footnote,
    color: 'rgba(252,228,34,0.95)',
    fontWeight: '600',
  },
  caption: {
    ...liquidType.bodySecondary,
    fontSize: 14,
    lineHeight: 20,
  },
  cardInner: { padding: 18 },
  block: { gap: 4 },
  blockLabel: {
    ...liquidType.sectionLabel,
    fontSize: 10,
    marginBottom: 4,
  },
  blockTitle: { ...liquidType.headline },
  blockBody: { ...liquidType.bodySecondary, fontSize: 15 },
  divider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: liquidHairline.separator,
    marginVertical: 16,
  },
  sectionTitle: {
    ...liquidType.headline,
    marginBottom: 12,
  },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 10,
  },
  itemRowBorder: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: liquidHairline.separator,
  },
  itemName: { ...liquidType.body, flex: 1, minWidth: 0 },
  itemQty: { ...liquidType.subheadline },
});

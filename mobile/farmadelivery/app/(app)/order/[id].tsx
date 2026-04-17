import { useLocalSearchParams, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
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
import { fetchOrderDetail, type OrderDetail, type OrderDetailItem } from '../../../lib/api';
import { colors } from '../../../lib/colors';
import {
  liquidCanvas,
  liquidHairline,
  liquidRadius,
  liquidSpace,
  liquidType,
} from '../../../lib/liquidHigTheme';
import { formatQuantity } from '../../../lib/formatUsd';

function formatDateTime(iso: string | null | undefined): string | null {
  if (!iso) {
    return null;
  }
  try {
    const d = new Date(iso);
    return d.toLocaleString('es-VE', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return null;
  }
}

function KeyVal({
  label,
  value,
}: {
  label: string;
  value: string | null | undefined;
}) {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  return (
    <View style={styles.kv}>
      <Text style={styles.kvLabel}>{label}</Text>
      <Text style={styles.kvValue}>{value}</Text>
    </View>
  );
}

function SectionTitle({ children }: { children: ReactNode }) {
  return <Text style={styles.sectionTitle}>{children}</Text>;
}

function ItemLine({ item, showDivider }: { item: OrderDetailItem; showDivider: boolean }) {
  return (
    <View>
      <View style={styles.itemRow}>
        <View style={styles.itemMain}>
          <Text style={styles.itemName}>{item.product_name}</Text>
          {item.sku ? (
            <Text style={styles.itemSku}>SKU {item.sku}</Text>
          ) : null}
        </View>
        <Text style={styles.itemQty}>{formatQuantity(item.quantity)}</Text>
      </View>
      {showDivider ? <View style={styles.itemDivider} /> : null}
    </View>
  );
}

export default function OrderDetailScreen() {
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { token } = useAuth();
  const orderId = Number(idParam);
  const [detail, setDetail] = useState<OrderDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token || Number.isNaN(orderId) || orderId < 1) {
      return;
    }
    setError(null);
    const data = await fetchOrderDetail(token, orderId);
    setDetail(data);
  }, [token, orderId]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (!token || Number.isNaN(orderId) || orderId < 1) {
        setLoading(false);
        setError('Pedido no válido.');
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
  }, [token, orderId, load]);

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
          <Text style={styles.backLabel}>Pedidos</Text>
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
            <Text style={styles.hint}>Cargando detalle…</Text>
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
              <Text style={styles.heroNumber}>
                {detail.order_number ?? `Pedido #${detail.id}`}
              </Text>
              <View style={styles.badgeRow}>
                <View style={styles.statusBadge}>
                  <Text style={styles.statusBadgeText}>{detail.status_label}</Text>
                </View>
              </View>
              <KeyVal label="Creado" value={formatDateTime(detail.created_at) ?? undefined} />
              <KeyVal
                label="Entrega programada"
                value={formatDateTime(detail.scheduled_delivery_at) ?? undefined}
              />
              <KeyVal label="Notas del pedido" value={detail.notes ?? undefined} />
            </GlassCard>

            <SectionTitle>ENTREGA · QUIEN RECIBE</SectionTitle>
            <GlassCard
              contentStyle={styles.cardPad}
              radius={liquidRadius.card}
              style={styles.cardMargin}
            >
              <KeyVal label="Destinatario" value={detail.delivery.recipient_name ?? undefined} />
              <KeyVal label="Teléfono" value={detail.delivery.phone ?? undefined} />
              <KeyVal label="Documento" value={detail.delivery.recipient_document ?? undefined} />
              <KeyVal
                label="Dirección"
                value={
                  [
                    detail.delivery.address,
                    detail.delivery.city,
                    detail.delivery.state,
                  ]
                    .filter(Boolean)
                    .join(' · ') || undefined
                }
              />
              <KeyVal label="Indicaciones" value={detail.delivery.notes ?? undefined} />
            </GlassCard>

            <SectionTitle>ÍTEMS ({detail.items.length})</SectionTitle>
            <GlassCard
              contentStyle={styles.itemsCard}
              radius={liquidRadius.card}
              style={styles.cardMargin}
            >
              {detail.items.length === 0 ? (
                <Text style={styles.muted}>No hay líneas en este pedido.</Text>
              ) : (
                detail.items.map((item, index) => (
                  <ItemLine
                    key={item.id}
                    item={item}
                    showDivider={index < detail.items.length - 1}
                  />
                ))
              )}
            </GlassCard>

            <SectionTitle>ALIADO</SectionTitle>
            <GlassCard
              contentStyle={styles.cardPad}
              radius={liquidRadius.card}
              style={styles.cardMargin}
            >
              <KeyVal label="Nombre" value={detail.partner?.name ?? undefined} />
              <KeyVal label="Código" value={detail.partner?.code ?? undefined} />
              <KeyVal label="Teléfono" value={detail.partner?.phone ?? undefined} />
              {!detail.partner ? (
                <Text style={styles.muted}>Sin datos de aliado.</Text>
              ) : null}
            </GlassCard>

            <SectionTitle>CLIENTE (PEDIDO)</SectionTitle>
            <GlassCard
              contentStyle={styles.cardPad}
              radius={liquidRadius.card}
              style={styles.cardMargin}
            >
              {detail.client ? (
                <>
                  <KeyVal label="Nombre" value={detail.client.name ?? undefined} />
                  <KeyVal label="Teléfono" value={detail.client.phone ?? undefined} />
                  <KeyVal label="Correo" value={detail.client.email ?? undefined} />
                  <KeyVal
                    label="Dirección"
                    value={
                      [detail.client.address, detail.client.city, detail.client.state]
                        .filter(Boolean)
                        .join(' · ') || undefined
                    }
                  />
                </>
              ) : (
                <Text style={styles.muted}>
                  Pedido sin cliente vinculado (solo aliado).
                </Text>
              )}
            </GlassCard>

            <Text style={styles.footerHint}>
              Verifica que la dirección y los ítems coincidan antes de salir a ruta.
            </Text>
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
    paddingVertical: 4,
    paddingRight: 12,
  },
  backChevron: {
    fontSize: 34,
    fontWeight: '300',
    color: colors.primary,
    marginRight: 2,
    marginTop: -4,
  },
  backLabel: {
    ...liquidType.headline,
  },
  scroll: {
    paddingTop: 12,
    paddingHorizontal: liquidSpace.cardPad,
  },
  scrollTransparent: {
    backgroundColor: 'transparent',
  },
  cardMargin: {
    marginBottom: 16,
  },
  center: {
    paddingVertical: 48,
    alignItems: 'center',
    gap: 12,
  },
  hint: {
    ...liquidType.footnote,
    fontSize: 15,
  },
  errorPad: {
    padding: 18,
  },
  errorText: {
    fontSize: 15,
    color: 'rgba(255,190,190,0.98)',
    marginBottom: 12,
    lineHeight: 22,
  },
  retry: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.primary,
  },
  heroPad: {
    padding: 20,
  },
  heroNumber: {
    ...liquidType.title2,
    marginBottom: 12,
  },
  badgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-start',
    marginBottom: 16,
    gap: 12,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 8,
    backgroundColor: 'rgba(248,113,113,0.2)',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(248,113,113,0.45)',
  },
  statusBadgeText: {
    ...liquidType.subheadline,
    fontSize: 13,
    fontWeight: '700',
    color: 'rgba(255,200,200,0.95)',
    letterSpacing: 0.2,
  },
  sectionTitle: {
    ...liquidType.sectionLabel,
    marginBottom: 8,
    marginLeft: 4,
  },
  cardPad: {
    paddingHorizontal: liquidSpace.cardPad,
    paddingVertical: 16,
  },
  kv: {
    marginBottom: 12,
  },
  kvLabel: {
    ...liquidType.caption1,
    marginBottom: 4,
  },
  kvValue: {
    ...liquidType.body,
    fontSize: 16,
    lineHeight: 22,
  },
  muted: {
    ...liquidType.body,
    fontSize: 15,
    color: 'rgba(255,255,255,0.5)',
    lineHeight: 22,
  },
  itemsCard: {
    paddingVertical: 4,
  },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 14,
    paddingHorizontal: 16,
  },
  itemMain: {
    flex: 1,
    paddingRight: 10,
  },
  itemName: {
    ...liquidType.headline,
    fontSize: 16,
    marginBottom: 4,
  },
  itemSku: {
    ...liquidType.footnote,
    color: 'rgba(255,255,255,0.45)',
    marginBottom: 4,
  },
  itemQty: {
    ...liquidType.headline,
    fontSize: 17,
    minWidth: 48,
    textAlign: 'right',
    color: 'rgba(252,228,34,0.92)',
    ...liquidType.tabular,
  },
  itemDivider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: liquidHairline.separator,
    marginLeft: 16,
    opacity: 0.85,
  },
  footerHint: {
    ...liquidType.footnote,
    textAlign: 'center',
    marginHorizontal: 16,
    marginTop: 4,
  },
});

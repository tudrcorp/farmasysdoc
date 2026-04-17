import Ionicons from '@expo/vector-icons/Ionicons';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import type { PendingJob } from '../lib/api';
import { colors } from '../lib/colors';
import { liquidAndroid, liquidType } from '../lib/liquidHigTheme';

export type DeliveryJobRowVariant = 'pending' | 'active';

function recipientHeadline(job: PendingJob): string {
  const d = job.delivery;
  const name =
    d.recipient_name?.trim() ||
    job.client?.name?.trim() ||
    job.to_branch?.name?.trim() ||
    null;
  const phone = d.phone?.trim() || job.client?.phone?.trim() || null;
  if (name && phone) {
    return `${name} · ${phone}`;
  }
  if (name) {
    return name;
  }
  if (phone) {
    return phone;
  }
  return job.kind === 'transfer' ? 'Traslado entre sucursales' : 'Destinatario por confirmar';
}

function typeChipMeta(job: PendingJob): { label: string; tone: 'transfer' | 'client' | 'partner' } {
  if (job.kind === 'transfer') {
    return { label: 'Traslado', tone: 'transfer' };
  }
  if (job.source === 'aliado') {
    return { label: 'Pedido · Aliado', tone: 'partner' };
  }
  return { label: 'Pedido · Cliente', tone: 'client' };
}

type Props = {
  job: PendingJob;
  /** Pendientes: tomar con GPS. Gestión: abrir ruta / mapa. */
  variant: DeliveryJobRowVariant;
  onOpenDetail: () => void;
  onPrimaryPress: () => void;
  primaryLoading?: boolean;
};

export function PendingOrderRow({
  job,
  variant,
  onOpenDetail,
  onPrimaryPress,
  primaryLoading = false,
}: Props) {
  const isTransfer = job.kind === 'transfer';
  const subtitle = isTransfer
    ? job.source_label
    : job.source === 'aliado'
      ? job.partner?.name?.trim() || 'Aliado'
      : job.source_label || 'Cliente';

  const title =
    job.order_number ?? (isTransfer ? `Traslado #${job.id}` : `Pedido #${job.id}`);

  const stageLabel = job.job_stage === 'en_proceso' ? 'En proceso' : 'Pendiente';
  const typeMeta = typeChipMeta(job);

  const accentStyle =
    variant === 'active' ? styles.accentActive : styles.accentPending;

  return (
    <View style={[styles.row, accentStyle]}>
      <Pressable
        accessibilityRole="button"
        accessibilityHint="Abre el detalle completo."
        android_ripple={liquidAndroid.rippleWhite}
        onPress={onOpenDetail}
        style={({ pressed }) => [styles.mainHit, pressed && styles.pressed]}
      >
        <View style={styles.main}>
          <View style={styles.chipsRow}>
            <View
              style={[
                styles.chip,
                typeMeta.tone === 'transfer' && styles.chipTypeTransfer,
                typeMeta.tone === 'client' && styles.chipTypeClient,
                typeMeta.tone === 'partner' && styles.chipTypePartner,
              ]}
            >
              <Text style={styles.chipText}>{typeMeta.label}</Text>
            </View>
            <View
              style={[
                styles.chip,
                job.job_stage === 'en_proceso' ? styles.chipStageActive : styles.chipStagePending,
              ]}
            >
              <Text style={styles.chipText}>{stageLabel}</Text>
            </View>
          </View>

          <Text numberOfLines={1} style={styles.title}>
            {title}
          </Text>

          <Text numberOfLines={2} style={styles.recipient}>
            {recipientHeadline(job)}
          </Text>
          <View
            accessibilityLabel={`Origen: ${subtitle}`}
            style={styles.aliadoRow}
          >
            <Ionicons
              color="rgba(255,255,255,0.32)"
              name={isTransfer ? 'swap-horizontal-outline' : 'storefront-outline'}
              size={14}
            />
            <Text numberOfLines={1} style={styles.aliadoText}>
              <Text style={styles.aliadoPrefix}>
                {isTransfer ? 'Ruta · ' : job.source === 'aliado' ? 'Aliado · ' : 'Origen · '}
              </Text>
              {subtitle}
            </Text>
          </View>
        </View>
      </Pressable>

      <View style={styles.trailing}>
        <Pressable
          accessibilityHint={
            variant === 'pending'
              ? 'Requiere GPS activo para tomar la tarea.'
              : 'Abre la navegación hacia el destino.'
          }
          accessibilityLabel={
            variant === 'pending'
              ? isTransfer
                ? 'Tomar traslado'
                : 'Tomar pedido'
              : 'Abrir ruta'
          }
          android_ripple={
            variant === 'pending' ? liquidAndroid.rippleCta : liquidAndroid.rippleWhite
          }
          disabled={primaryLoading}
          hitSlop={{ top: 10, bottom: 10, left: 8, right: 8 }}
          onPress={onPrimaryPress}
          style={({ pressed }) => [
            styles.primaryBtn,
            variant === 'pending' ? styles.primaryBtnTake : styles.primaryBtnRoute,
            pressed && !primaryLoading && styles.primaryBtnPressed,
            primaryLoading && styles.primaryBtnDisabled,
          ]}
        >
          {primaryLoading ? (
            <ActivityIndicator
              color={variant === 'pending' ? '#0d3338' : '#062c2a'}
              size="small"
            />
          ) : (
            <Ionicons
              color={variant === 'pending' ? '#0d3338' : '#062c2a'}
              name={variant === 'pending' ? 'hand-left-outline' : 'navigate-outline'}
              size={18}
            />
          )}
        </Pressable>
        <Pressable
          accessibilityLabel="Ver detalle"
          android_ripple={liquidAndroid.rippleWhite}
          hitSlop={10}
          onPress={onOpenDetail}
          style={({ pressed }) => [pressed && styles.pressed]}
        >
          <Text style={styles.chevron}>›</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: 72,
    paddingVertical: 12,
    paddingLeft: 14,
    paddingRight: 10,
    borderLeftWidth: 3,
    borderLeftColor: 'transparent',
  },
  accentPending: {
    borderLeftColor: 'rgba(252,228,34,0.85)',
  },
  accentActive: {
    borderLeftColor: 'rgba(45,212,191,0.9)',
  },
  mainHit: {
    flex: 1,
    paddingRight: 8,
    minWidth: 0,
  },
  pressed: {
    opacity: 0.72,
  },
  main: {
    minWidth: 0,
    gap: 6,
  },
  chipsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 6,
    marginBottom: 2,
  },
  chip: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
    borderWidth: StyleSheet.hairlineWidth,
  },
  chipText: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 0.35,
    color: 'rgba(255,255,255,0.92)',
  },
  chipTypeTransfer: {
    backgroundColor: 'rgba(139,92,246,0.28)',
    borderColor: 'rgba(167,139,250,0.45)',
  },
  chipTypeClient: {
    backgroundColor: 'rgba(59,130,246,0.22)',
    borderColor: 'rgba(96,165,250,0.4)',
  },
  chipTypePartner: {
    backgroundColor: 'rgba(249,115,22,0.22)',
    borderColor: 'rgba(251,146,60,0.42)',
  },
  chipStagePending: {
    backgroundColor: 'rgba(251,191,36,0.22)',
    borderColor: 'rgba(252,211,77,0.45)',
  },
  chipStageActive: {
    backgroundColor: 'rgba(45,212,191,0.2)',
    borderColor: 'rgba(94,234,212,0.48)',
  },
  title: {
    ...liquidType.headline,
    marginBottom: 0,
    minWidth: 0,
  },
  recipient: {
    ...liquidType.body,
    fontSize: 15,
    fontWeight: '600',
    color: 'rgba(255,255,255,0.92)',
    lineHeight: 20,
  },
  aliadoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minWidth: 0,
    marginTop: 1,
  },
  aliadoText: {
    flex: 1,
    minWidth: 0,
    fontSize: 12,
    fontWeight: '500',
    letterSpacing: -0.1,
    color: 'rgba(255,255,255,0.58)',
  },
  aliadoPrefix: {
    fontWeight: '500',
    color: 'rgba(255,255,255,0.38)',
  },
  trailing: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
  },
  primaryBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: StyleSheet.hairlineWidth,
  },
  primaryBtnTake: {
    backgroundColor: colors.primary,
    borderColor: 'rgba(255,255,255,0.28)',
  },
  primaryBtnRoute: {
    backgroundColor: 'rgba(45,212,191,0.92)',
    borderColor: 'rgba(255,255,255,0.32)',
  },
  primaryBtnPressed: {
    opacity: 0.88,
    transform: [{ scale: 0.96 }],
  },
  primaryBtnDisabled: {
    opacity: 0.75,
  },
  chevron: {
    fontSize: 22,
    fontWeight: '300',
    color: 'rgba(255,255,255,0.35)',
    paddingHorizontal: 4,
    minWidth: 22,
    textAlign: 'center',
  },
});

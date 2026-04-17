import { useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useState } from 'react';
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
import { logoutRequest } from '../../../lib/api';
import { colors } from '../../../lib/colors';
import {
  liquidCanvas,
  liquidHairline,
  liquidRadius,
  liquidShadowCta,
  liquidSpace,
  liquidType,
} from '../../../lib/liquidHigTheme';

/** Tab bar anclada abajo: alto de ítems + home indicator + aire sobre la tarjeta. */
function tabBarContentInset(bottomInset: number): number {
  const bar = 68;
  return bar + bottomInset + 24;
}

export default function AccountScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { token, user, signOut } = useAuth();
  const [busy, setBusy] = useState(false);

  async function onLogout() {
    if (!token) {
      await signOut();
      router.replace('/(auth)/login');
      return;
    }
    setBusy(true);
    try {
      await logoutRequest(token);
    } catch {
      // Cerramos sesión local aunque falle el revoke.
    } finally {
      await signOut();
      setBusy(false);
      router.replace('/(auth)/login');
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
        <Text style={styles.navTitle}>Cuenta</Text>
        <Text style={styles.navSubtitle}>Sesión y cierre seguro</Text>
      </View>

      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          { paddingBottom: bottomPad },
        ]}
        showsVerticalScrollIndicator={false}
        style={styles.scrollTransparent}
      >
        <GlassCard
          contentStyle={styles.cardInner}
          radius={liquidRadius.cardProminent}
          style={styles.card}
        >
          <Text style={styles.sectionLabel}>USUARIO</Text>
          <Text style={styles.name}>{user?.name ?? '—'}</Text>
          <Text style={styles.email}>{user?.email ?? ''}</Text>
        </GlassCard>

        <Text style={styles.hintBlock}>
          Tu sesión usa un token seguro en el dispositivo. Al cerrar sesión se
          revoca en el servidor cuando hay conexión.
        </Text>

        <Pressable
          accessibilityRole="button"
          android_ripple={{ color: 'rgba(0,0,0,0.18)' }}
          disabled={busy}
          onPress={onLogout}
          style={({ pressed }) => [
            styles.logoutOuter,
            pressed && !busy && styles.logoutPressed,
            busy && styles.logoutDisabled,
          ]}
        >
          {busy ? (
            <ActivityIndicator color="#0d3338" />
          ) : (
            <Text style={styles.logoutText}>Cerrar sesión</Text>
          )}
        </Pressable>
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
  scroll: {
    paddingTop: 20,
    paddingHorizontal: liquidSpace.screenH,
  },
  scrollTransparent: {
    backgroundColor: 'transparent',
  },
  card: {
    marginBottom: 18,
  },
  cardInner: {
    padding: liquidSpace.cardPadLg,
  },
  sectionLabel: {
    ...liquidType.sectionLabel,
    marginBottom: 10,
  },
  name: {
    ...liquidType.title2,
    marginBottom: 6,
  },
  email: {
    ...liquidType.bodySecondary,
    fontSize: 15,
  },
  hintBlock: {
    ...liquidType.footnote,
    fontSize: 14,
    lineHeight: 21,
    marginBottom: 28,
  },
  logoutOuter: {
    backgroundColor: colors.primary,
    borderRadius: liquidRadius.pill,
    paddingVertical: 16,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.35)',
    ...liquidShadowCta(),
  },
  logoutPressed: {
    opacity: 0.88,
    transform: [{ scale: 0.99 }],
  },
  logoutDisabled: {
    opacity: 0.65,
  },
  logoutText: {
    ...liquidType.cta,
  },
});

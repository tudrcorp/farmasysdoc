import { Redirect, Stack } from 'expo-router';
import { ActivityIndicator, Platform, StyleSheet, View } from 'react-native';

import { useAuth } from '../../contexts/AuthContext';
import { colors } from '../../lib/colors';
import { liquidCanvas } from '../../lib/liquidHigTheme';

const stackAnim = {
  /** Transición tipo iOS al abrir el detalle desde la lista. */
  orderDetail: {
    animation: 'default' as const,
    gestureEnabled: true,
    fullScreenGestureEnabled: true,
    animationDuration: Platform.OS === 'ios' ? 380 : 280,
    animationMatchesGesture: true,
    gestureDirection: 'horizontal' as const,
  },
  /** Entrada suave al área autenticada (tabs). */
  tabsGroup: {
    animation: 'fade' as const,
    animationDuration: 260,
  },
};

export default function AppGroupLayout() {
  const { token, isReady } = useAuth();

  if (!isReady) {
    return (
      <View style={sheet.boot}>
        <ActivityIndicator color={colors.primary} size="large" />
      </View>
    );
  }

  if (!token) {
    return <Redirect href="/(auth)/login" />;
  }

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: liquidCanvas.background },
        gestureEnabled: true,
        fullScreenGestureEnabled: true,
      }}
    >
      <Stack.Screen name="(tabs)" options={stackAnim.tabsGroup} />
      <Stack.Screen name="order/[id]" options={stackAnim.orderDetail} />
      <Stack.Screen
        name="delivery-route/[orderId]"
        options={stackAnim.orderDetail}
      />
      <Stack.Screen name="transfer/[id]" options={stackAnim.orderDetail} />
    </Stack>
  );
}

const sheet = StyleSheet.create({
  boot: {
    flex: 1,
    backgroundColor: liquidCanvas.background,
    justifyContent: 'center',
    alignItems: 'center',
  },
});

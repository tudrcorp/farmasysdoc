import Ionicons from '@expo/vector-icons/Ionicons';
import { Tabs } from 'expo-router';
import type { Ref } from 'react';
import { Platform, StyleSheet, type View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { LiquidGlassTabBarBackground } from '../../../components/liquid/LiquidGlassTabBarBackground';
import { LiquidTabBarButton } from '../../../components/liquid/LiquidTabBarButton';
import { colors } from '../../../lib/colors';
import { liquidGlass, liquidRadius, liquidShadowTabBar } from '../../../lib/liquidHigTheme';

const TAB_BAR_RADIUS = liquidRadius.tabBar;
/** Alto del área táctil de ítems (encima del home indicator). */
const TAB_BAR_HEIGHT = 68;

export default function AppTabsLayout() {
  const insets = useSafeAreaInsets();
  const bottomSafe = insets.bottom;

  const tabBarTotalHeight = TAB_BAR_HEIGHT + bottomSafe;

  const tabBarTopRadiusStyle =
    Platform.OS === 'ios'
      ? {
          borderTopLeftRadius: TAB_BAR_RADIUS,
          borderTopRightRadius: TAB_BAR_RADIUS,
          borderBottomLeftRadius: 0,
          borderBottomRightRadius: 0,
          borderCurve: 'continuous' as const,
        }
      : {
          borderTopLeftRadius: TAB_BAR_RADIUS,
          borderTopRightRadius: TAB_BAR_RADIUS,
          borderBottomLeftRadius: 0,
          borderBottomRightRadius: 0,
        };

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: 'rgba(255,255,255,0.42)',
        tabBarLabelStyle: {
          fontSize: 10,
          fontWeight: '700',
          letterSpacing: 0.6,
          marginBottom: 2,
          textTransform: 'uppercase',
        },
        tabBarIconStyle: { marginTop: 2 },
        tabBarItemStyle: {
          paddingVertical: 2,
        },
        tabBarStyle: {
          position: 'absolute',
          left: 16,
          right: 16,
          bottom: 0,
          height: tabBarTotalHeight,
          paddingHorizontal: 6,
          paddingTop: 4,
          paddingBottom: bottomSafe,
          overflow: 'hidden',
          backgroundColor: 'transparent',
          borderTopWidth: 0,
          borderWidth: StyleSheet.hairlineWidth * 2,
          borderBottomWidth: 0,
          borderColor: liquidGlass.border,
          ...tabBarTopRadiusStyle,
          ...liquidShadowTabBar(),
        },
        tabBarBackground: () => (
          <LiquidGlassTabBarBackground
            flushBottom
            radius={TAB_BAR_RADIUS}
          />
        ),
        tabBarButton: (props) => {
          const { ref, ...buttonProps } = props;
          return (
            <LiquidTabBarButton
              {...buttonProps}
              ref={ref as Ref<View> | undefined}
            />
          );
        },
        sceneStyle: { backgroundColor: 'transparent' },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Pendientes',
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons
              color={color}
              name={focused ? 'time' : 'time-outline'}
              size={focused ? size + 4 : size + 2}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="gestion"
        options={{
          title: 'Gestión',
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons
              color={color}
              name={focused ? 'navigate' : 'navigate-outline'}
              size={focused ? size + 4 : size + 2}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: 'Cuenta',
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons
              color={color}
              name={focused ? 'person-circle' : 'person-circle-outline'}
              size={focused ? size + 4 : size + 2}
            />
          ),
        }}
      />
    </Tabs>
  );
}

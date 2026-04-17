import { BlurView } from 'expo-blur';
import { LinearGradient } from 'expo-linear-gradient';
import type { ReactNode } from 'react';
import { Platform, StyleSheet, View, type ViewStyle } from 'react-native';

import {
  liquidContinuousRadius,
  liquidGlass,
  liquidRadius,
  liquidShadowCard,
} from '../../lib/liquidHigTheme';

const isWeb = Platform.OS === 'web';

type Props = {
  children: ReactNode;
  /** Radio del vidrio (esquinas). */
  radius?: number;
  style?: ViewStyle;
  contentStyle?: ViewStyle;
};

/**
 * Panel cristal (blur iOS / gradiente Android) alineado al tema liquid HIG.
 */
export function GlassCard({
  children,
  radius = liquidRadius.cardProminent,
  style,
  contentStyle,
}: Props) {
  const r = radius;
  const corner = liquidContinuousRadius(r);
  const clipStyle: ViewStyle[] = [
    styles.clip,
    corner,
    Platform.OS === 'android' ? styles.clipAndroid : {},
  ];

  return (
    <View style={[liquidShadowCard(), corner, style]}>
      <View
        collapsable={Platform.OS === 'android' ? false : undefined}
        style={clipStyle}
      >
        {isWeb ? (
          <View
            style={[
              StyleSheet.absoluteFill,
              { backgroundColor: liquidGlass.webCardFill },
            ]}
          />
        ) : Platform.OS === 'android' ? (
          <>
            <LinearGradient
              colors={[...liquidGlass.androidCardGradient]}
              end={{ x: 0.5, y: 1 }}
              pointerEvents="none"
              start={{ x: 0.5, y: 0 }}
              style={StyleSheet.absoluteFill}
            />
            <View
              pointerEvents="none"
              style={[
                StyleSheet.absoluteFill,
                { backgroundColor: liquidGlass.veil },
              ]}
            />
          </>
        ) : (
          <BlurView
            intensity={liquidGlass.intensityCard}
            style={StyleSheet.absoluteFill}
            tint={liquidGlass.blurTintDark}
          />
        )}
        <View style={[styles.inner, contentStyle]}>{children}</View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  clip: {
    overflow: 'hidden',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: liquidGlass.border,
  },
  clipAndroid: {
    backgroundColor: 'transparent',
  },
  inner: {
    backgroundColor: 'transparent',
    ...Platform.select({
      android: {
        elevation: 0,
      },
    }),
  },
});

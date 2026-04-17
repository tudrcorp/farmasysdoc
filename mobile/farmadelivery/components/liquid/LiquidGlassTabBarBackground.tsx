import { BlurView } from 'expo-blur';
import { LinearGradient } from 'expo-linear-gradient';
import { Platform, StyleSheet, View } from 'react-native';

import { liquidGlass } from '../../lib/liquidHigTheme';

type Props = {
  /** Debe coincidir con el borderRadius del tabBarStyle. */
  radius?: number;
  /** Barra pegada al borde inferior de la pantalla (solo redondeo superior). */
  flushBottom?: boolean;
};

/**
 * Fondo de barra de pestañas liquid glass: blur en iOS, gradiente en Android (sin artefactos del blur nativo).
 */
export function LiquidGlassTabBarBackground({
  radius = 30,
  flushBottom = false,
}: Props) {
  const shell = [
    StyleSheet.absoluteFillObject,
    flushBottom
      ? {
          borderTopLeftRadius: radius,
          borderTopRightRadius: radius,
          borderBottomLeftRadius: 0,
          borderBottomRightRadius: 0,
          overflow: 'hidden' as const,
          ...(Platform.OS === 'ios'
            ? { borderCurve: 'continuous' as const }
            : {}),
        }
      : { borderRadius: radius, overflow: 'hidden' as const },
  ];

  if (Platform.OS === 'web') {
    return (
      <View style={shell}>
        <LinearGradient
          colors={['rgba(32, 72, 76, 0.92)', 'rgba(12, 38, 42, 0.94)']}
          end={{ x: 0.5, y: 1 }}
          start={{ x: 0.5, y: 0 }}
          style={StyleSheet.absoluteFill}
        />
        <View
          style={[
            styles.specular,
            { borderTopLeftRadius: radius, borderTopRightRadius: radius },
          ]}
        />
      </View>
    );
  }

  if (Platform.OS === 'android') {
    return (
      <View style={shell}>
        <LinearGradient
          colors={[...liquidGlass.androidTabBarGradient]}
          end={{ x: 0.5, y: 1 }}
          start={{ x: 0.5, y: 0 }}
          style={StyleSheet.absoluteFill}
        />
        <LinearGradient
          colors={['rgba(255,255,255,0.12)', 'rgba(255,255,255,0.03)', 'transparent']}
          end={{ x: 0.5, y: 0.55 }}
          locations={[0, 0.35, 1]}
          pointerEvents="none"
          start={{ x: 0.5, y: 0 }}
          style={StyleSheet.absoluteFill}
        />
        <LinearGradient
          colors={['transparent', 'rgba(24,172,178,0.14)', 'transparent']}
          end={{ x: 1, y: 0.5 }}
          pointerEvents="none"
          start={{ x: 0, y: 0.5 }}
          style={StyleSheet.absoluteFill}
        />
        <View
          pointerEvents="none"
          style={[
            StyleSheet.absoluteFill,
            { backgroundColor: liquidGlass.veilSoft },
          ]}
        />
        <View
          style={[
            styles.specular,
            { borderTopLeftRadius: radius, borderTopRightRadius: radius },
          ]}
        />
      </View>
    );
  }

  return (
    <View style={shell}>
      <BlurView
        intensity={liquidGlass.intensityTabBarIos}
        style={StyleSheet.absoluteFill}
        tint={liquidGlass.blurTintDark}
      />
      <LinearGradient
        colors={['rgba(255,255,255,0.14)', 'rgba(255,255,255,0.03)', 'transparent']}
        end={{ x: 0.5, y: 0.55 }}
        locations={[0, 0.35, 1]}
        pointerEvents="none"
        start={{ x: 0.5, y: 0 }}
        style={StyleSheet.absoluteFill}
      />
      <LinearGradient
        colors={['transparent', 'rgba(24,172,178,0.12)', 'transparent']}
        end={{ x: 1, y: 0.5 }}
        pointerEvents="none"
        start={{ x: 0, y: 0.5 }}
        style={StyleSheet.absoluteFill}
      />
      <View
        style={[
          styles.specular,
          { borderTopLeftRadius: radius, borderTopRightRadius: radius },
        ]}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  specular: {
    position: 'absolute',
    top: 0,
    left: '10%',
    right: '10%',
    height: StyleSheet.hairlineWidth * 2,
    backgroundColor: liquidGlass.specularLine,
    borderRadius: 2,
  },
});

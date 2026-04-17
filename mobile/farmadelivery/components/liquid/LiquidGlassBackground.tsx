import { LinearGradient } from 'expo-linear-gradient';
import { StyleSheet, View, type ViewStyle } from 'react-native';

type Props = {
  style?: ViewStyle;
};

/**
 * Fondo de marca con gradiente y orbes (misma familia visual que el login).
 */
export function LiquidGlassBackground({ style }: Props) {
  return (
    <View pointerEvents="none" style={[StyleSheet.absoluteFill, style]}>
      <LinearGradient
        colors={['#021a1d', '#063d42', '#0a5f66', '#063a3e']}
        end={{ x: 0.85, y: 1 }}
        locations={[0, 0.35, 0.72, 1]}
        start={{ x: 0.1, y: 0 }}
        style={StyleSheet.absoluteFill}
      />
      <View pointerEvents="none" style={StyleSheet.absoluteFill}>
        <LinearGradient
          colors={['transparent', 'rgba(24,172,178,0.22)', 'transparent']}
          style={styles.orb1}
        />
        <LinearGradient
          colors={['rgba(252,228,34,0.18)', 'rgba(14,148,154,0.12)', 'transparent']}
          style={styles.orb2}
        />
        <LinearGradient
          colors={['transparent', 'rgba(255,255,255,0.06)', 'transparent']}
          style={styles.sheen}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  orb1: {
    position: 'absolute',
    top: '-8%',
    right: '-20%',
    width: 320,
    height: 320,
    borderRadius: 160,
    opacity: 0.95,
  },
  orb2: {
    position: 'absolute',
    bottom: '5%',
    left: '-25%',
    width: 280,
    height: 280,
    borderRadius: 140,
    opacity: 0.9,
  },
  sheen: {
    position: 'absolute',
    top: '28%',
    left: '-30%',
    width: '90%',
    height: 120,
    borderRadius: 60,
    transform: [{ rotate: '-12deg' }],
  },
});

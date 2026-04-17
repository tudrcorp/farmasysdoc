/**
 * Tema visual alineado a Human Interface Guidelines + estética liquid glass
 * (iPhone / iOS reciente), aplicado también en Android con gradientes y sombras equivalentes.
 */
import { Platform, StyleSheet, type TextStyle, type ViewStyle } from 'react-native';

import { colors } from './colors';

/** Fondo de lienzo de toda la app (detrás del gradiente decorativo). */
export const liquidCanvas = {
  background: '#021a1d',
} as const;

/** Vidrio: bordes, rellenos y capas compartidas iOS/Android/Web. */
export const liquidGlass = {
  border: 'rgba(255,255,255,0.32)',
  borderStrong: 'rgba(255,255,255,0.34)',
  /** Superficie tipo material oscuro (Android / fallback web). */
  androidCardGradient: [
    'rgba(32, 58, 62, 0.78)',
    'rgba(20, 46, 50, 0.85)',
    'rgba(28, 52, 56, 0.8)',
  ] as const,
  /** Barra flotante: un poco más denso que las tarjetas. */
  androidTabBarGradient: [
    'rgba(22, 52, 56, 0.92)',
    'rgba(10, 36, 40, 0.96)',
    'rgba(18, 44, 48, 0.94)',
  ] as const,
  webCardFill: 'rgba(28, 52, 56, 0.58)',
  webCardFillLogin: 'rgba(28, 52, 56, 0.62)',
  veil: 'rgba(255,255,255,0.07)',
  veilSoft: 'rgba(255,255,255,0.05)',
  specularLine: 'rgba(255,255,255,0.5)',
  blurTintDark: 'dark' as const,
  blurTintLight: 'light' as const,
  intensityCard: 52,
  /** Tarjeta principal (login): un poco más de profundidad que listas. */
  intensityHero: 58,
  intensityTabBarIos: 72,
  intensityTabPillIos: 36,
  /** Brillo superior en tarjetas grandes (login). */
  sheenTop: 'rgba(255,255,255,0.45)',
} as const;

export const liquidRadius = {
  card: 22,
  cardProminent: 26,
  cardHero: 34,
  tabBar: 30,
  input: 18,
  chip: 14,
  pill: 999,
  tabPill: 20,
} as const;

export const liquidSpace = {
  screenH: 20,
  screenHLoose: 22,
  cardPad: 18,
  cardPadLg: 22,
} as const;

/** Esquinas continuas (squircle) en iOS 15+; en Android radio estándar. */
export function liquidContinuousRadius(r: number): ViewStyle {
  if (Platform.OS === 'ios') {
    return { borderRadius: r, borderCurve: 'continuous' };
  }
  return { borderRadius: r };
}

export const liquidHairline = {
  separator: 'rgba(255,255,255,0.18)',
} as const;

/** Tipografía estilo SF: jerarquía clara, tracking negativo en títulos grandes. */
export const liquidType = StyleSheet.create({
  largeTitle: {
    fontSize: 34,
    fontWeight: '700',
    letterSpacing: 0.35,
    color: 'rgba(255,255,255,0.96)',
    ...Platform.select({
      ios: { fontVariant: ['proportional-nums' as const] },
      default: {},
    }),
  },
  title1: {
    fontSize: 28,
    fontWeight: '700',
    letterSpacing: -0.5,
    color: 'rgba(255,255,255,0.96)',
  },
  title2: {
    fontSize: 22,
    fontWeight: '700',
    letterSpacing: -0.4,
    color: 'rgba(255,255,255,0.96)',
  },
  title3: {
    fontSize: 20,
    fontWeight: '700',
    letterSpacing: -0.35,
    color: 'rgba(255,255,255,0.95)',
  },
  headline: {
    fontSize: 17,
    fontWeight: '600',
    letterSpacing: -0.41,
    color: 'rgba(255,255,255,0.96)',
  },
  body: {
    fontSize: 17,
    fontWeight: '400',
    letterSpacing: -0.24,
    color: 'rgba(255,255,255,0.92)',
  },
  bodySecondary: {
    fontSize: 16,
    fontWeight: '400',
    lineHeight: 23,
    color: 'rgba(255,255,255,0.62)',
  },
  subheadline: {
    fontSize: 15,
    fontWeight: '500',
    letterSpacing: -0.2,
    color: 'rgba(252,228,34,0.88)',
  },
  footnote: {
    fontSize: 13,
    fontWeight: '400',
    lineHeight: 19,
    color: 'rgba(255,255,255,0.55)',
  },
  caption1: {
    fontSize: 13,
    fontWeight: '600',
    color: 'rgba(255,255,255,0.45)',
  },
  /** Secciones en mayúsculas (estilo iOS grouped). */
  sectionLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1.4,
    color: 'rgba(252,228,34,0.78)',
    textTransform: 'uppercase' as TextStyle['textTransform'],
  },
  navTitle: {
    fontSize: 20,
    fontWeight: '700',
    letterSpacing: -0.5,
    color: 'rgba(255,255,255,0.95)',
  },
  navSubtitle: {
    fontSize: 13,
    fontWeight: '500',
    color: 'rgba(255,255,255,0.48)',
    marginTop: 4,
  },
  formLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1.6,
    color: 'rgba(252,228,34,0.88)',
  },
  cta: {
    fontSize: 17,
    fontWeight: '700',
    letterSpacing: -0.2,
    color: '#0d3338',
  },
  tabular: {
    fontVariant: ['tabular-nums' as const],
  },
});

export function liquidShadowCard(): ViewStyle {
  if (Platform.OS === 'web') {
    return {
      boxShadow: '0 18px 48px rgba(0,0,0,0.4)',
    };
  }
  if (Platform.OS === 'android') {
    return { elevation: 14 };
  }
  return {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 18 },
    shadowOpacity: 0.4,
    shadowRadius: 32,
  };
}

export function liquidShadowCardHero(): ViewStyle {
  if (Platform.OS === 'web') {
    return { boxShadow: '0 28px 80px rgba(0,0,0,0.45)' };
  }
  if (Platform.OS === 'android') {
    return { elevation: 18 };
  }
  return {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 28 },
    shadowOpacity: 0.45,
    shadowRadius: 48,
  };
}

export function liquidShadowCta(): ViewStyle {
  if (Platform.OS === 'android') {
    return { elevation: 10 };
  }
  return {
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.42,
    shadowRadius: 20,
  };
}

export function liquidShadowTabBar(): ViewStyle {
  if (Platform.OS === 'android') {
    return { elevation: 18 };
  }
  if (Platform.OS === 'web') {
    return { boxShadow: '0 16px 40px rgba(0,0,0,0.35)' };
  }
  return {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 16 },
    shadowOpacity: 0.42,
    shadowRadius: 28,
  };
}

/** Ripple Material suave acorde al vidrio oscuro. */
export const liquidAndroid = {
  rippleWhite: { color: 'rgba(255,255,255,0.12)' },
  rippleCta: { color: 'rgba(0,0,0,0.18)' },
} as const;

import { BlurView } from 'expo-blur';
import { LinearGradient } from 'expo-linear-gradient';
import { forwardRef, type ReactNode } from 'react';
import {
  Platform,
  Pressable,
  type PressableProps,
  type PressableStateCallbackType,
  StyleSheet,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';

import { colors } from '../../lib/colors';
import { liquidGlass, liquidRadius } from '../../lib/liquidHigTheme';

const PILL_R = liquidRadius.tabPill;

/**
 * Botón de pestaña con píldora cristal cuando está activo (HIG / liquid glass en iOS y Android).
 */
export const LiquidTabBarButton = forwardRef<View, PressableProps>(
  function LiquidTabBarButton(
    { children, style, accessibilityState, ...rest },
    ref,
  ) {
    const selected = accessibilityState?.selected === true;

    const renderInner = (pressableState: PressableStateCallbackType): ReactNode => (
      <View
        style={[
          styles.pill,
          { borderRadius: PILL_R },
          selected && styles.pillSelected,
          Platform.OS === 'ios' && selected && styles.pillSelectedIos,
        ]}
      >
        {selected ? (
          <>
            {Platform.OS === 'web' ? (
              <View style={[StyleSheet.absoluteFill, styles.pillWeb]} />
            ) : Platform.OS === 'android' ? (
              <>
                <LinearGradient
                  colors={[
                    'rgba(255,255,255,0.22)',
                    'rgba(255,255,255,0.08)',
                    'rgba(252,228,34,0.12)',
                  ]}
                  end={{ x: 1, y: 1 }}
                  pointerEvents="none"
                  start={{ x: 0, y: 0 }}
                  style={[StyleSheet.absoluteFill, { borderRadius: PILL_R }]}
                />
                <View
                  pointerEvents="none"
                  style={[
                    StyleSheet.absoluteFill,
                    {
                      borderRadius: PILL_R,
                      backgroundColor: 'rgba(255,255,255,0.04)',
                    },
                  ]}
                />
              </>
            ) : (
              <BlurView
                intensity={liquidGlass.intensityTabPillIos}
                style={StyleSheet.absoluteFill}
                tint={liquidGlass.blurTintLight}
              />
            )}
            <LinearGradient
              colors={[
                'rgba(252,228,34,0.22)',
                'rgba(255,255,255,0.08)',
                'rgba(24,172,178,0.1)',
              ]}
              end={{ x: 1, y: 1 }}
              pointerEvents="none"
              start={{ x: 0, y: 0 }}
              style={[StyleSheet.absoluteFill, { borderRadius: PILL_R }]}
            />
            <View style={styles.pillSpecular} />
          </>
        ) : null}
        <View style={styles.content}>
          {typeof children === 'function' ? children(pressableState) : children}
        </View>
      </View>
    );

    return (
      <View ref={ref} collapsable={false} style={styles.root}>
        <Pressable
          {...rest}
          accessibilityState={accessibilityState}
          style={(state): StyleProp<ViewStyle> => {
            const fromNav: StyleProp<ViewStyle> =
              typeof style === 'function' ? style(state) : style;

            return [styles.pressableFill, fromNav];
          }}
        >
          {(pressableState) => renderInner(pressableState)}
        </Pressable>
      </View>
    );
  },
);

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  pressableFill: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  pill: {
    flex: 1,
    width: '100%',
    maxWidth: 128,
    marginVertical: 5,
    marginHorizontal: 4,
    overflow: 'hidden',
    justifyContent: 'center',
    alignItems: 'center',
  },
  pillSelected: {
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(255,255,255,0.38)',
  },
  pillSelectedIos: {
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.45,
    shadowRadius: 12,
  },
  pillWeb: {
    backgroundColor: 'rgba(255,255,255,0.14)',
    borderRadius: PILL_R,
  },
  pillSpecular: {
    position: 'absolute',
    top: 0,
    left: '18%',
    right: '18%',
    height: 1,
    backgroundColor: 'rgba(255,255,255,0.55)',
    borderRadius: 1,
    zIndex: 1,
  },
  content: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 4,
    zIndex: 2,
  },
});

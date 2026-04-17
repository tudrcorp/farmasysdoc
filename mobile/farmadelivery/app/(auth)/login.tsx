import { BlurView } from 'expo-blur';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { LiquidGlassBackground } from '../../components/liquid/LiquidGlassBackground';
import { useAuth } from '../../contexts/AuthContext';
import { loginRequest } from '../../lib/api';
import { colors } from '../../lib/colors';
import {
  liquidCanvas,
  liquidContinuousRadius,
  liquidGlass,
  liquidRadius,
  liquidShadowCardHero,
  liquidShadowCta,
  liquidSpace,
  liquidType,
} from '../../lib/liquidHigTheme';

const isWeb = Platform.OS === 'web';
const CARD_R = liquidRadius.cardHero;

export default function LoginScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit() {
    setError(null);
    setLoading(true);
    try {
      const data = await loginRequest(email, password);
      await signIn(data.token, data.user);
      router.replace('/(app)/(tabs)');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error inesperado.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <View style={styles.root}>
      <LiquidGlassBackground />

      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.flexTransparent}
      >
        <ScrollView
          contentContainerStyle={[
            styles.scroll,
            {
              paddingTop: insets.top + 20,
              paddingBottom: insets.bottom + 28,
            },
          ]}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          style={styles.scrollViewTransparent}
        >
          <View style={[liquidShadowCardHero(), liquidContinuousRadius(CARD_R)]}>
            <View
              collapsable={false}
              style={[
                styles.cardClip,
                liquidContinuousRadius(CARD_R),
                Platform.OS === 'android' && styles.cardClipAndroid,
              ]}
            >
              {isWeb ? (
                <View
                  style={[
                    StyleSheet.absoluteFill,
                    { backgroundColor: liquidGlass.webCardFillLogin },
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
                  intensity={liquidGlass.intensityHero}
                  style={StyleSheet.absoluteFill}
                  tint={liquidGlass.blurTintDark}
                />
              )}
              <View style={styles.cardInner}>
                <View style={styles.specularEdge} />

                <Image
                  accessibilityIgnoresInvertColors
                  resizeMode="contain"
                  source={require('../../assets/farmadoc-light.png')}
                  style={styles.logo}
                />

                <Text style={styles.title}>Farmadoc Delivery</Text>
                <Text style={styles.subtitle}>
                  Ingresa con el correo y clave de tu usuario.
                </Text>

                <Text style={styles.label}>CORREO</Text>
                <View style={styles.inputGlass}>
                  <TextInput
                    autoCapitalize="none"
                    autoComplete="email"
                    autoCorrect={false}
                    keyboardType="email-address"
                    onChangeText={setEmail}
                    placeholder="correo@ejemplo.com"
                    placeholderTextColor="rgba(255,255,255,0.38)"
                    style={styles.input}
                    underlineColorAndroid="transparent"
                    value={email}
                  />
                </View>

                <Text style={styles.label}>CONTRASEÑA</Text>
                <View style={styles.inputGlass}>
                  <TextInput
                    autoCapitalize="none"
                    autoComplete="password"
                    onChangeText={setPassword}
                    placeholder="••••••••"
                    placeholderTextColor="rgba(255,255,255,0.38)"
                    secureTextEntry
                    style={styles.input}
                    underlineColorAndroid="transparent"
                    value={password}
                  />
                </View>

                {error ? (
                  <View style={styles.errorGlass}>
                    <ScrollView
                      nestedScrollEnabled
                      showsVerticalScrollIndicator
                      style={styles.errorScroll}
                    >
                      <Text style={styles.error}>{error}</Text>
                    </ScrollView>
                  </View>
                ) : null}

                <Pressable
                  disabled={loading}
                  onPress={onSubmit}
                  style={({ pressed }) => [
                    styles.ctaOuter,
                    pressed && styles.ctaPressed,
                    loading && styles.ctaDisabled,
                  ]}
                >
                  <LinearGradient
                    colors={['#FFF59D', colors.primary, '#D4BE1A']}
                    end={{ x: 1, y: 1 }}
                    start={{ x: 0, y: 0 }}
                    style={styles.ctaGradient}
                  >
                    {loading ? (
                      <ActivityIndicator color="#0d3338" />
                    ) : (
                      <Text style={styles.ctaText}>Entrar</Text>
                    )}
                  </LinearGradient>
                </Pressable>
              </View>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: liquidCanvas.background,
  },
  flex: {
    flex: 1,
  },
  flexTransparent: {
    flex: 1,
    backgroundColor: 'transparent',
  },
  scrollViewTransparent: {
    backgroundColor: 'transparent',
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
            paddingHorizontal: liquidSpace.screenHLoose,
  },
  /**
   * Un solo contenedor con overflow + radio: recorta blur y capas internas
   * (evita esquinas cuadradas oscuras detrás del borde redondeado).
   */
  cardClip: {
    overflow: 'hidden',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: liquidGlass.borderStrong,
  },
  /**
   * Android: evita que el sistema aplique fondo opaco al grupo nativo del borde redondeado.
   */
  cardClipAndroid: {
    backgroundColor: 'transparent',
  },
  cardInner: {
    paddingHorizontal: 26,
    paddingTop: 30,
    paddingBottom: 28,
    backgroundColor: 'transparent',
    ...Platform.select({
      android: {
        elevation: 0,
      },
    }),
  },
  specularEdge: {
    position: 'absolute',
    top: 0,
    left: '12%',
    right: '12%',
    height: 1,
    backgroundColor: liquidGlass.sheenTop,
    borderRadius: 1,
  },
  logo: {
    width: '100%',
    height: 52,
    marginBottom: 22,
  },
  title: {
    ...liquidType.title1,
    textAlign: 'center',
    marginBottom: 8,
  },
  subtitle: {
    ...liquidType.bodySecondary,
    textAlign: 'center',
    marginBottom: 26,
  },
  label: {
    ...liquidType.formLabel,
    marginBottom: 8,
  },
  inputGlass: {
    borderRadius: liquidRadius.input,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.22)',
    backgroundColor: 'rgba(255,255,255,0.08)',
    marginBottom: 16,
    ...Platform.select({
      ios: {
        shadowColor: '#fff',
        shadowOffset: { width: 0, height: 0 },
        shadowOpacity: 0.08,
        shadowRadius: 8,
      },
    }),
  },
  input: {
    paddingHorizontal: 16,
    paddingVertical: Platform.OS === 'ios' ? 15 : 12,
    ...liquidType.body,
    ...Platform.select({
      android: {
        backgroundColor: 'transparent',
      },
    }),
  },
  errorGlass: {
    marginBottom: 14,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: liquidRadius.chip,
    backgroundColor: 'rgba(248,113,113,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(248,113,113,0.35)',
  },
  errorScroll: {
    maxHeight: 220,
  },
  error: {
    color: 'rgba(255,200,200,0.98)',
    fontSize: 13,
    lineHeight: 19,
  },
  ctaOuter: {
    marginTop: 10,
    borderRadius: liquidRadius.pill,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.42)',
    ...liquidShadowCta(),
  },
  ctaGradient: {
    paddingVertical: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  ctaPressed: {
    opacity: 0.92,
    transform: [{ scale: 0.985 }],
  },
  ctaDisabled: {
    opacity: 0.65,
  },
  ctaText: {
    ...liquidType.cta,
  },
});

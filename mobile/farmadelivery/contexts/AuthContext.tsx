import * as SecureStore from 'expo-secure-store';
import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { Platform } from 'react-native';

import type { DeliveryUser } from '../lib/api';

const TOKEN_KEY = 'farmadelivery_sanctum_token';
const USER_KEY = 'farmadelivery_user_json';

type AuthContextValue = {
  token: string | null;
  user: DeliveryUser | null;
  isReady: boolean;
  signIn: (token: string, user: DeliveryUser) => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<DeliveryUser | null>(null);
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        if (Platform.OS === 'web') {
          setToken(null);
          setUser(null);
        } else {
          const [t, u] = await Promise.all([
            SecureStore.getItemAsync(TOKEN_KEY),
            SecureStore.getItemAsync(USER_KEY),
          ]);
          if (cancelled) {
            return;
          }
          setToken(t);
          if (u) {
            try {
              setUser(JSON.parse(u) as DeliveryUser);
            } catch {
              setUser(null);
            }
          }
        }
      } finally {
        if (!cancelled) {
          setIsReady(true);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const signIn = useCallback(async (newToken: string, newUser: DeliveryUser) => {
    if (Platform.OS !== 'web') {
      await SecureStore.setItemAsync(TOKEN_KEY, newToken);
      await SecureStore.setItemAsync(USER_KEY, JSON.stringify(newUser));
    }
    setToken(newToken);
    setUser(newUser);
  }, []);

  const signOut = useCallback(async () => {
    if (Platform.OS !== 'web') {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      await SecureStore.deleteItemAsync(USER_KEY);
    }
    setToken(null);
    setUser(null);
  }, []);

  const value = useMemo(
    () => ({ token, user, isReady, signIn, signOut }),
    [token, user, isReady, signIn, signOut],
  );

  return (
    <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth debe usarse dentro de AuthProvider.');
  }
  return ctx;
}

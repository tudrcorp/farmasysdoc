import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

import { AuthProvider } from '../contexts/AuthContext';
import { liquidCanvas } from '../lib/liquidHigTheme';

export default function RootLayout() {
  return (
    <AuthProvider>
      <StatusBar style="light" />
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: liquidCanvas.background },
          animation: 'default',
        }}
      />
    </AuthProvider>
  );
}

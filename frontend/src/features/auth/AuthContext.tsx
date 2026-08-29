import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { apiClient } from '../../lib/apiClient';
import { tokenStore } from '../../lib/tokenStore';
import type { ApiSuccess, User } from '../../types/api';

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!tokenStore.getAccessToken()) {
      setIsLoading(false);
      return;
    }

    apiClient
      .get<ApiSuccess<User>>('/users/me')
      .then(({ data }) => setUser(data.data))
      .catch(() => tokenStore.clear())
      .finally(() => setIsLoading(false));
  }, []);

  async function login(email: string, password: string) {
    const { data } = await apiClient.post<ApiSuccess<{ accessToken: string; refreshToken: string; user: User }>>(
      '/auth/login',
      { email, password },
    );
    tokenStore.setTokens(data.data.accessToken, data.data.refreshToken);
    setUser(data.data.user);
  }

  async function logout() {
    const refreshToken = tokenStore.getRefreshToken();
    tokenStore.clear();
    setUser(null);
    if (refreshToken) {
      await apiClient.post('/auth/logout', { refreshToken }).catch(() => undefined);
    }
  }

  return <AuthContext.Provider value={{ user, isLoading, login, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within an AuthProvider');
  return ctx;
}

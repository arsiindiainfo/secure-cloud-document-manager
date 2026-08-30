import { useEffect, useState, type ReactNode } from 'react';
import { apiClient } from '../../lib/apiClient';
import { tokenStore } from '../../lib/tokenStore';
import type { ApiSuccess, User } from '../../types/api';
import { AuthContext } from './context';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  // No token means nothing to check — start already "loaded" rather than
  // flipping isLoading to false synchronously inside the effect below.
  const [isLoading, setIsLoading] = useState(() => tokenStore.getAccessToken() !== null);

  useEffect(() => {
    if (!tokenStore.getAccessToken()) return;

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

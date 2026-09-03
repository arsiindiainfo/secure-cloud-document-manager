/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { createContext } from 'react';
import type { User } from '../../types/api';

export interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  login: (email: string, password: string, recaptchaToken?: string | null) => Promise<void>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);


export interface AdminRole {
  name: string;
  slug: string;
}

export interface AdminUser {
  id: number;
  first_name: string;
  last_name: string;
  email: string | null;
  phone: string | null;
  status: string | null;
  roles: AdminRole[];
  permissions: string[];
  created_at: string | null;
  updated_at: string | null;
}

export interface AdminLoginResponse {
  message: string;
  user: AdminUser;
  token: string;
}

export interface AdminMeResponse {
  user: AdminUser;
}

export interface ApiValidationErrors {
  [key: string]: string[] | undefined;
}

export interface ApiErrorPayload {
  message?: string;
  errors?: ApiValidationErrors;
}

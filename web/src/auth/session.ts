export type SessionUser = {
  id: string
  name: string
  email: string
  isSuperAdmin: boolean
  emailVerifiedAt: string | null
}

export type MeResponse = {
  id: string
  name: string
  email: string
  is_super_admin: boolean
  email_verified_at: string | null
}

export function sessionFromMe(data: MeResponse): SessionUser {
  return {
    id: data.id,
    name: data.name,
    email: data.email,
    isSuperAdmin: data.is_super_admin,
    emailVerifiedAt: data.email_verified_at,
  }
}

type Session = { user: null } | { user: SessionUser }

let session: Session = { user: null }

export function getSession(): Session {
  return session
}

export function setSession(user: SessionUser): void {
  session = { user }
}

export function clearSession(): void {
  session = { user: null }
}

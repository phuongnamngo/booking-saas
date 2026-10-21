export type SessionUser = {
  id: string
  name: string
  email: string
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

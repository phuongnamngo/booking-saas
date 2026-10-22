import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'

type ToastKind = 'success' | 'error'

type ShowToast = (kind: ToastKind, message: string) => void

const ToastContext = createContext<ShowToast | null>(null)

// The provider and the hook share this context.
// eslint-disable-next-line react-refresh/only-export-components
export function useToast(): ShowToast {
  const showToast = useContext(ToastContext)

  if (showToast === null) {
    throw new Error('Toast is only available inside the desk.')
  }

  return showToast
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toast, setToast] = useState<{ kind: ToastKind; message: string } | null>(null)

  useEffect(() => {
    if (toast === null) {
      return
    }

    const timer = window.setTimeout(() => setToast(null), 4000)

    return () => window.clearTimeout(timer)
  }, [toast])

  return (
    <ToastContext.Provider value={(kind, message) => setToast({ kind, message })}>
      {children}
      {toast ? <p role="status">{toast.message}</p> : null}
    </ToastContext.Provider>
  )
}

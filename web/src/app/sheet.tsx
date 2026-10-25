import { useEffect, useRef, type ReactNode } from 'react'

export function Sheet({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  const panel = useRef<HTMLElement>(null)

  useEffect(() => {
    panel.current?.focus()

    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose()
      }
    }

    window.addEventListener('keydown', onKey)

    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div className="sheet-layer">
      <button type="button" className="sheet-scrim" aria-label="Đóng" tabIndex={-1} onClick={onClose} />
      <aside className="side-sheet" role="dialog" aria-modal="true" aria-label={title} tabIndex={-1} ref={panel}>
        <header className="side-sheet-head">
          <h2>{title}</h2>
          <button type="button" className="icon-button" aria-label="Đóng bảng" onClick={onClose}>
            ×
          </button>
        </header>
        {children}
      </aside>
    </div>
  )
}

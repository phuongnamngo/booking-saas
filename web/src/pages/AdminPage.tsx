import { useEffect, useState, type CSSProperties } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { listFrom } from './listFrom'

type TenantRow = {
  id: string
  name: string
  slug: string
  plan: string
  suspended_at: string | null
}

export function AdminPage() {
  const [rows, setRows] = useState<TenantRow[]>([])

  useEffect(() => {
    let cancelled = false

    async function load() {
      try {
        const response = await api.get('/api/v1/admin/tenants')
        if (!cancelled) {
          setRows(listFrom<TenantRow>(response.data))
        }
      } catch {
        if (!cancelled) {
          setRows([])
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [])

  async function changePlan(id: string, plan: string) {
    await ensureCsrfCookie()
    await api.patch(`/api/v1/admin/tenants/${id}`, { plan })
    setRows((current) => current.map((row) => (row.id === id ? { ...row, plan } : row)))
  }

  async function suspend(id: string) {
    await ensureCsrfCookie()
    await api.post(`/api/v1/admin/tenants/${id}/suspend`)
    setRows((current) =>
      current.map((row) => (row.id === id ? { ...row, suspended_at: new Date().toISOString() } : row)),
    )
  }

  async function unsuspend(id: string) {
    await ensureCsrfCookie()
    await api.post(`/api/v1/admin/tenants/${id}/unsuspend`)
    setRows((current) => current.map((row) => (row.id === id ? { ...row, suspended_at: null } : row)))
  }

  return (
    <div className="desk">
      <aside className="rail">
        <p className="mark">Sổ lịch</p>
        <p className="mark-sub">Quản trị</p>
      </aside>
      <div className="desk-main">
        <div className="desk-bar" />
        <div className="page">
        <div className="page-head">
          <div>
            <h1>Tenant</h1>
            <p className="mute">Gói dịch vụ và trạng thái của từng cơ sở</p>
          </div>
        </div>
        {rows.length === 0 ? <p className="empty">Chưa có tenant</p> : null}
        <ul className="rows">
          {rows.map((row, index) => (
            <li key={row.id} style={{ '--i': index } as CSSProperties}>
              <div>
                <p className="row-title">{row.name}</p>
                <p className="row-meta">
                  <span>{row.slug}</span>
                  {row.suspended_at ? <span className="pill pill-cancelled">Đang khóa</span> : null}
                </p>
              </div>
              <div className="row-actions">
                <label>
                  <span className="sr-only">Gói</span>
                  <select
                    aria-label="Gói"
                    value={row.plan}
                    onChange={(event) => void changePlan(row.id, event.target.value)}
                  >
                    <option value="free">free</option>
                    <option value="pro">pro</option>
                  </select>
                </label>
                {row.suspended_at ? (
                  <button type="button" onClick={() => void unsuspend(row.id)}>
                    Mở khóa
                  </button>
                ) : (
                  <button className="danger" type="button" onClick={() => void suspend(row.id)}>
                    Khóa
                  </button>
                )}
              </div>
            </li>
          ))}
        </ul>
        </div>
      </div>
    </div>
  )
}

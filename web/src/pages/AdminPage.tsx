import { useEffect, useState } from 'react'
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
        <h1>Tenant</h1>
        <ul>
          {rows.map((row) => (
            <li key={row.id}>
              <p>
                <span>{row.name}</span>
                <span> · {row.slug} · {row.plan}</span>
                {row.suspended_at ? <span> · đang khóa</span> : null}
              </p>
              <label>
                Gói
                <select
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
                <button type="button" onClick={() => void suspend(row.id)}>
                  Khóa
                </button>
              )}
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}

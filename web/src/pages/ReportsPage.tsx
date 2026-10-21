import { useEffect, useState } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { readApiError } from './readApiError'

function today(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

export function ReportsPage() {
  const [from] = useState(today)
  const [to] = useState(today)
  const [revenue, setRevenue] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    async function load() {
      try {
        const response = await api.get('/api/v1/reports', {
          params: { from, to, group: 'day' },
        })
        if (!cancelled) {
          setRevenue((response.data as { revenue: number }).revenue)
        }
      } catch (caught) {
        if (!cancelled) {
          setError(readApiError(caught))
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [from, to])

  async function exportCsv() {
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/reports/exports', { from, to, group: 'day' })
    } catch (caught) {
      setError(readApiError(caught))
    }
  }

  return (
    <section className="screen">
      <h1>Báo cáo</h1>
      {error ? <p role="alert">{error}</p> : null}
      {revenue !== null ? (
        <p className="figure">
          <span>Doanh thu</span>
          <strong>{revenue}</strong>
          <span>đ</span>
        </p>
      ) : null}
      <button className="primary" type="button" onClick={() => void exportCsv()}>
        Xuất CSV
      </button>
    </section>
  )
}

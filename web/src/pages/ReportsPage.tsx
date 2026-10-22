import { useEffect, useState } from 'react'
import { Bar, BarChart, XAxis, YAxis } from 'recharts'
import { api, ensureCsrfCookie } from '../api/client'
import { useToast } from '../app/toast'
import { readApiError } from './readApiError'

type PeriodPoint = { period: string; revenue: number }
type NamedPoint = { id: string; name: string; revenue: number }
type HourPoint = { hour: string; count: number }

type ReportPayload = {
  revenue?: number
  cancel_rate?: number
  no_show_rate?: number
  by_period?: PeriodPoint[]
  by_service?: NamedPoint[]
  by_staff?: NamedPoint[]
  peak_hours?: HourPoint[]
}

function today(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

export function ReportsPage() {
  const [from] = useState(today)
  const [to] = useState(today)
  const [group, setGroup] = useState<'day' | 'week' | 'month'>('day')
  const [revenue, setRevenue] = useState<number | null>(null)
  const [cancelRate, setCancelRate] = useState<number | null>(null)
  const [noShowRate, setNoShowRate] = useState<number | null>(null)
  const [byPeriod, setByPeriod] = useState<PeriodPoint[]>([])
  const [byService, setByService] = useState<NamedPoint[]>([])
  const [byStaff, setByStaff] = useState<NamedPoint[]>([])
  const [peakHours, setPeakHours] = useState<HourPoint[]>([])
  const [error, setError] = useState<string | null>(null)
  const showToast = useToast()

  useEffect(() => {
    let cancelled = false

    async function load() {
      try {
        const response = await api.get('/api/v1/reports', {
          params: { from, to, group },
        })
        if (!cancelled) {
          const data = response.data as ReportPayload
          setRevenue(data.revenue ?? null)
          setCancelRate(data.cancel_rate ?? null)
          setNoShowRate(data.no_show_rate ?? null)
          setByPeriod(data.by_period ?? [])
          setByService(data.by_service ?? [])
          setByStaff(data.by_staff ?? [])
          setPeakHours(data.peak_hours ?? [])
        }
      } catch (caught) {
        if (!cancelled) {
          const message = readApiError(caught)
          setError(message)
          showToast('error', message)
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [from, to, group])

  async function exportCsv() {
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/reports/exports', { from, to, group })
      showToast('success', 'Đã gửi xuất CSV.')
    } catch (caught) {
      const message = readApiError(caught)
      setError(message)
      showToast('error', message)
    }
  }

  return (
    <section className="screen">
      <h1>Báo cáo</h1>
      {error ? <p role="alert">{error}</p> : null}
      <label>
        Nhóm
        <select value={group} onChange={(event) => setGroup(event.target.value as 'day' | 'week' | 'month')}>
          <option value="day">Ngày</option>
          <option value="week">Tuần</option>
          <option value="month">Tháng</option>
        </select>
      </label>
      {revenue !== null ? (
        <p className="figure">
          <span>Doanh thu</span>
          <strong>{revenue}</strong>
          <span>đ</span>
        </p>
      ) : null}
      {cancelRate !== null ? <p>Hủy {cancelRate}</p> : null}
      {noShowRate !== null ? <p>Vắng {noShowRate}</p> : null}
      <BarChart width={480} height={200} data={byPeriod}>
        <XAxis dataKey="period" />
        <YAxis />
        <Bar dataKey="revenue" />
      </BarChart>
      <BarChart width={480} height={200} data={byService}>
        <XAxis dataKey="name" />
        <YAxis />
        <Bar dataKey="revenue" />
      </BarChart>
      <BarChart width={480} height={200} data={byStaff}>
        <XAxis dataKey="name" />
        <YAxis />
        <Bar dataKey="revenue" />
      </BarChart>
      <BarChart width={480} height={200} data={peakHours}>
        <XAxis dataKey="hour" />
        <YAxis />
        <Bar dataKey="count" />
      </BarChart>
      <button className="primary" type="button" onClick={() => void exportCsv()}>
        Xuất CSV
      </button>
    </section>
  )
}

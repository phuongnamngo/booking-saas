import { useEffect, useState, type CSSProperties } from 'react'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts'
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

  const money = new Intl.NumberFormat('vi-VN')
  const compact = new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 })
  const percent = (rate: number) => `${Math.round(rate * 1000) / 10}`
  const charts: { title: string; data: object[]; x: string; y: string }[] = [
    { title: 'Doanh thu theo kỳ', data: byPeriod, x: 'period', y: 'revenue' },
    { title: 'Doanh thu theo dịch vụ', data: byService, x: 'name', y: 'revenue' },
    { title: 'Doanh thu theo nhân viên', data: byStaff, x: 'name', y: 'revenue' },
    { title: 'Khung giờ đông', data: peakHours, x: 'hour', y: 'count' },
  ]

  return (
    <section className="screen">
      <div className="page-head">
        <div>
          <h1>Báo cáo</h1>
          <p className="mute">Doanh thu, tỷ lệ hủy và khung giờ đông của quầy</p>
        </div>
        <div className="actions">
          <label>
            Nhóm
            <select value={group} onChange={(event) => setGroup(event.target.value as 'day' | 'week' | 'month')}>
              <option value="day">Ngày</option>
              <option value="week">Tuần</option>
              <option value="month">Tháng</option>
            </select>
          </label>
          <button className="primary" type="button" onClick={() => void exportCsv()}>
            Xuất CSV
          </button>
        </div>
      </div>
      {error ? <p role="alert">{error}</p> : null}
      <div className="figures">
        <p className="figure" style={{ '--i': 0 } as CSSProperties}>
          <span>Doanh thu</span>
          <strong>
            {revenue !== null ? money.format(revenue) : '—'}
            <small>đ</small>
          </strong>
        </p>
        <p className="figure" style={{ '--i': 1 } as CSSProperties}>
          <span>Tỷ lệ hủy</span>
          <strong>
            {cancelRate !== null ? percent(cancelRate) : '—'}
            <small>%</small>
          </strong>
        </p>
        <p className="figure" style={{ '--i': 2 } as CSSProperties}>
          <span>Tỷ lệ vắng</span>
          <strong>
            {noShowRate !== null ? percent(noShowRate) : '—'}
            <small>%</small>
          </strong>
        </p>
      </div>
      <div className="charts">
        {charts.map((chart, index) => (
          <figure className="chart" key={chart.title} style={{ '--i': index } as CSSProperties}>
            <h2>{chart.title}</h2>
            <BarChart width={420} height={220} data={chart.data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
              <CartesianGrid vertical={false} stroke="#e3e9e6" />
              <XAxis dataKey={chart.x} tickLine={false} axisLine={{ stroke: '#d7e0dc' }} tick={{ fill: '#5e6f6a', fontSize: 12 }} />
              <YAxis tickLine={false} axisLine={false} tick={{ fill: '#5e6f6a', fontSize: 12 }} tickFormatter={(value: number) => compact.format(value)} width={56} />
              <Bar dataKey={chart.y} fill="#0f6e56" radius={[6, 6, 0, 0]} maxBarSize={40} animationDuration={400} />
            </BarChart>
          </figure>
        ))}
      </div>
    </section>
  )
}

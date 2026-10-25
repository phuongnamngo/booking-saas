import { useEffect, useState } from 'react'
import { useParams, useSearchParams } from 'react-router-dom'
import axios from 'axios'
import { api, ensureCsrfCookie } from '../api/client'
import { isClosed, statusLabel } from '../app/status'
import { readApiError } from './readApiError'

type AppointmentView = {
  id: string
  status: string
  starts_at: string
  staff_id: string | null
}

function formatClock(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function formatDay(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
}

function readManageError(caught: unknown): string {
  if (axios.isAxiosError(caught)) {
    const starts = caught.response?.data?.errors?.starts_at
    if (Array.isArray(starts) && typeof starts[0] === 'string') {
      return starts[0]
    }
  }

  return readApiError(caught)
}

export function ManageAppointmentPage() {
  const { slug = '', id = '' } = useParams()
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const [appointment, setAppointment] = useState<AppointmentView | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function load() {
      try {
        const response = await api.get<AppointmentView>(`/api/v1/public/${slug}/appointments/${id}`, {
          params: { token },
        })
        if (!cancelled) {
          setAppointment(response.data)
        }
      } catch (caught) {
        if (!cancelled) {
          setError(readManageError(caught))
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [slug, id, token])

  async function cancel() {
    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      const response = await api.post<AppointmentView>(
        `/api/v1/public/${slug}/appointments/${id}/cancel`,
        {},
        { params: { token } },
      )
      setAppointment(response.data)
    } catch (caught) {
      setError(readManageError(caught))
    } finally {
      setPending(false)
    }
  }

  return (
    <main className="stage">
      <p className="mark">Sổ lịch</p>
      <section className="stage-card">
        <h1>Lịch hẹn</h1>
        {error ? <p role="alert">{error}</p> : null}
        {appointment ? (
          <>
            <span className={`pill pill-${appointment.status}`}>{statusLabel(appointment.status)}</span>
            <time className="appt-time" dateTime={appointment.starts_at}>
              {formatClock(appointment.starts_at)}
              <small>{formatDay(appointment.starts_at)}</small>
            </time>
            <button
              className="danger"
              type="button"
              onClick={() => void cancel()}
              disabled={pending || isClosed(appointment.status)}
            >
              Hủy lịch
            </button>
          </>
        ) : null}
        {appointment === null && error === null ? <p className="mute">Đang tải</p> : null}
      </section>
    </main>
  )
}

import { useEffect, useState } from 'react'
import { useParams, useSearchParams } from 'react-router-dom'
import axios from 'axios'
import { api, ensureCsrfCookie } from '../api/client'
import { readApiError } from './readApiError'

type AppointmentView = {
  id: string
  status: string
  starts_at: string
  staff_id: string | null
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
      <h1>Lịch hẹn</h1>
      {error ? <p role="alert">{error}</p> : null}
      {appointment ? (
        <>
          <p>{appointment.status}</p>
          <p>{appointment.starts_at}</p>
          <button type="button" onClick={() => void cancel()} disabled={pending || appointment.status === 'cancelled'}>
            Hủy lịch
          </button>
        </>
      ) : null}
    </main>
  )
}

import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { expect, it } from 'vitest'
import { InvitationPage } from '../src/pages/InvitationPage'

it('shows an alert when the name is missing', async () => {
  render(
    <MemoryRouter initialEntries={['/invitations/plain-token']}>
      <Routes>
        <Route path="/invitations/:token" element={<InvitationPage />} />
      </Routes>
    </MemoryRouter>,
  )

  expect(screen.getByRole('heading', { name: 'Nhận lời mời' })).toBeInTheDocument()
  fireEvent.change(screen.getByLabelText('Mật khẩu'), { target: { value: 'password' } })
  fireEvent.click(screen.getByRole('button', { name: 'Tham gia' }))

  expect(await screen.findByRole('alert')).toBeInTheDocument()
})

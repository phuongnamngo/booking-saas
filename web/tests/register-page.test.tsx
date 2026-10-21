import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { expect, it } from 'vitest'
import { RegisterPage } from '../src/pages/RegisterPage'

it('shows the register heading and an alert when email is missing', async () => {
  render(
    <MemoryRouter>
      <RegisterPage />
    </MemoryRouter>,
  )

  expect(screen.getByRole('heading', { name: 'Đăng ký' })).toBeInTheDocument()

  fireEvent.change(screen.getByLabelText('Tên cơ sở'), { target: { value: 'Spa Một' } })
  fireEvent.change(screen.getByLabelText('Đường dẫn'), { target: { value: 'spa-mot' } })
  fireEvent.change(screen.getByLabelText('Tên của bạn'), { target: { value: 'An' } })
  fireEvent.change(screen.getByLabelText('Mật khẩu'), { target: { value: 'password' } })
  fireEvent.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

  expect(await screen.findByRole('alert')).toBeInTheDocument()
})

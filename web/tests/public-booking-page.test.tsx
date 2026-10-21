import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { expect, it } from 'vitest'
import { AppRouter } from '../src/app/router'

it('shows the booking heading', () => {
  render(
    <MemoryRouter initialEntries={['/t/spa-mot']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(screen.getByRole('heading', { name: 'Đặt lịch' })).toBeInTheDocument()
})

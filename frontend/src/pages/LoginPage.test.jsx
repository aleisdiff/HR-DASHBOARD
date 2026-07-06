import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import LoginPage from './LoginPage.jsx'
import { AuthProvider } from '../context/AuthContext.jsx'
import { I18nProvider } from '../context/I18nContext.jsx'
import { ThemeProvider } from '../context/ThemeContext.jsx'

vi.mock('../context/AuthContext.jsx', async () => {
  const actual = await vi.importActual('../context/AuthContext.jsx')
  return {
    ...actual,
    useAuth: () => ({
      login: vi.fn(),
      isAuthenticated: false,
    }),
  }
})

describe('LoginPage', () => {
  it('renders login form fields', () => {
    render(
      <MemoryRouter>
        <ThemeProvider>
          <I18nProvider>
            <AuthProvider>
              <LoginPage />
            </AuthProvider>
          </I18nProvider>
        </ThemeProvider>
      </MemoryRouter>,
    )

    expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
  })
})

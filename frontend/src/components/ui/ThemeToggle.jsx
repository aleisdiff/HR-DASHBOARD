import PropTypes from 'prop-types'

function SunIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2.5M12 19.5V22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2 12h2.5M19.5 12H22M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8" />
    </svg>
  )
}

function MoonIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" />
    </svg>
  )
}

export default function ThemeToggle({ isDark, onToggle, className = '' }) {
  return (
    <button
      type="button"
      onClick={onToggle}
      className={`inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition hover:-translate-y-0.5 ${className}`}
      aria-label="Toggle theme"
      title="Toggle theme"
    >
      {isDark ? <SunIcon /> : <MoonIcon />}
      <span>{isDark ? 'Light mode' : 'Dark mode'}</span>
    </button>
  )
}

ThemeToggle.propTypes = {
  isDark: PropTypes.bool.isRequired,
  onToggle: PropTypes.func.isRequired,
  className: PropTypes.string,
}

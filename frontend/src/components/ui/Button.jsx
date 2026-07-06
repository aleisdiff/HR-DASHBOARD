import PropTypes from 'prop-types'

const variantMap = {
  primary: 'bg-slate-900 text-white hover:bg-slate-700',
  ghost: 'bg-white/60 text-slate-800 border border-slate-300 hover:bg-white',
  success: 'bg-emerald-600 text-white hover:bg-emerald-700',
  danger: 'bg-rose-600 text-white hover:bg-rose-700',
}

export default function Button({
  children,
  type = 'button',
  variant = 'primary',
  disabled = false,
  className = '',
  onClick,
}) {
  return (
    <button
      type={type}
      disabled={disabled}
      onClick={onClick}
      className={`rounded-xl px-4 py-2 text-sm font-semibold transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60 ${variantMap[variant]} ${className}`}
    >
      {children}
    </button>
  )
}

Button.propTypes = {
  children: PropTypes.node.isRequired,
  type: PropTypes.oneOf(['button', 'submit', 'reset']),
  variant: PropTypes.oneOf(['primary', 'ghost', 'success', 'danger']),
  disabled: PropTypes.bool,
  className: PropTypes.string,
  onClick: PropTypes.func,
}

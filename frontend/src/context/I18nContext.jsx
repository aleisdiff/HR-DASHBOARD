/* eslint-disable react-refresh/only-export-components */
import PropTypes from 'prop-types'
import { createContext, useContext, useMemo, useState } from 'react'

const dictionary = {
  en: {
    language: 'Language',
    dashboard: 'Dashboard',
    notifications: 'Notifications',
    markAllRead: 'Mark all as read',
    analytics: 'Analytics',
    teamCalendar: 'Team Calendar',
    policyManager: 'Policy Manager',
    leaveType: 'Leave type',
    fullDay: 'Full day',
    halfDay: 'Half day',
    adminNote: 'Admin note',
  },
  it: {
    language: 'Lingua',
    dashboard: 'Dashboard',
    notifications: 'Notifiche',
    markAllRead: 'Segna tutte come lette',
    analytics: 'Analitiche',
    teamCalendar: 'Calendario Team',
    policyManager: 'Gestione Policy',
    leaveType: 'Tipo ferie',
    fullDay: 'Giornata intera',
    halfDay: 'Mezza giornata',
    adminNote: 'Nota admin',
  },
}

const I18nContext = createContext(null)

export function I18nProvider({ children }) {
  const [locale, setLocale] = useState('it')

  const value = useMemo(
    () => ({
      locale,
      setLocale,
      t: dictionary[locale],
    }),
    [locale],
  )

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
}

I18nProvider.propTypes = {
  children: PropTypes.node.isRequired,
}

export function useI18n() {
  const context = useContext(I18nContext)

  if (!context) {
    throw new Error('useI18n must be used within an I18nProvider')
  }

  return context
}
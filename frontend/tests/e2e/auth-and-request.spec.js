import { expect, test } from '@playwright/test'

test('employee login and leave request flow', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('employee@company.test')
  await page.getByLabel('Password').fill('password123')
  await page.getByRole('button', { name: /sign in/i }).click()

  await expect(page.getByText(/welcome/i)).toBeVisible()
  await expect(page.getByText(/leave balance/i)).toBeVisible()

  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  const dayAfterTomorrow = new Date()
  dayAfterTomorrow.setDate(dayAfterTomorrow.getDate() + 2)

  const fmt = (d) => d.toISOString().slice(0, 10)

  await page.getByLabel('Start date').fill(fmt(tomorrow))
  await page.getByLabel('End date').fill(fmt(dayAfterTomorrow))
  await page.getByLabel('Reason').fill('E2E test request')
  await page.getByRole('button', { name: /submit request/i }).click()

  await expect(page.getByText(/leave request submitted successfully/i)).toBeVisible()
})

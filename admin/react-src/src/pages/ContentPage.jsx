import React from 'react'
import { Grid, Text } from '@mantine/core'
import { IconActivity, IconAlertTriangle, IconChecks, IconLayoutDashboard } from '@tabler/icons-react'

import { MetricCard, PageHeader, Surface } from '../components/Enterprise.jsx'
import { decodeText } from '../lib/text.js'
import { getPageTitle, getSectionForFile, sectionLabels, getLanguage, pageTranslations } from '../lib/pageMeta.js'

function collectMetrics(sourceContent, trans) {
  const cards = sourceContent.querySelectorAll('.health-card, .small-box, .info-box, .dr-card, .qd-card, .b-card').length
  const warnings = sourceContent.querySelectorAll('.health-status.warning, .label-warning, .badge-warning, .alert-warning').length
  const critical = sourceContent.querySelectorAll('.health-status.critical, .label-danger, .badge-danger, .alert-danger').length
  const actions = sourceContent.querySelectorAll('a.btn, button.btn, input[type="submit"]').length

  return [
    { label: trans.modules, value: cards, description: trans.modulesDesc, icon: IconLayoutDashboard, tone: 'primary' },
    { label: trans.healthy, value: Math.max(0, cards - warnings - critical), description: trans.healthyDesc, icon: IconChecks, tone: 'success' },
    { label: trans.warnings, value: warnings, description: trans.warningsDesc, icon: IconAlertTriangle, tone: 'warning' },
    { label: trans.actions, value: actions, description: trans.actionsDesc, icon: IconActivity, tone: 'primary' },
  ]
}

export default function ContentPage({ sourceContent, pageName }) {
  const lang = getLanguage()
  const trans = pageTranslations[lang] || pageTranslations['ar']

  const hostRef = React.useRef(null)
  const originalParentRef = React.useRef(sourceContent.parentNode)
  const nextSiblingRef = React.useRef(sourceContent.nextSibling)
  const metrics = React.useMemo(() => collectMetrics(sourceContent, trans), [sourceContent, trans])
  const title = decodeText(getPageTitle(pageName))
  const section = sectionLabels[getSectionForFile(pageName)] || sectionLabels.system

  React.useEffect(() => {
    document.querySelector('.content-header')?.classList.add('admin-source-hidden')
  }, [])

  React.useLayoutEffect(() => {
    const host = hostRef.current
    const originalParent = originalParentRef.current
    const nextSibling = nextSiblingRef.current
    if (!host) return undefined

    host.appendChild(sourceContent)
    sourceContent.classList.add('saas-content-source')
    sourceContent.style.display = 'block'

    return () => {
      sourceContent.classList.remove('saas-content-source')
      sourceContent.style.display = ''
      originalParent?.insertBefore(sourceContent, nextSibling)
    }
  }, [sourceContent])

  return (
    <main className="saas-page saas-content-page" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      <PageHeader
        eyebrow={section}
        title={title}
        description={trans.contentPageDesc}
      />

      <Grid gutter="md" mb="md">
        {metrics.map((metric) => (
          <Grid.Col span={{ base: 12, sm: 6, lg: 3 }} key={metric.label}>
            <MetricCard {...metric} />
          </Grid.Col>
        ))}
      </Grid>

      <Surface className="saas-content-surface">
        <Text className="saas-card-eyebrow" mb="xs">{trans.operationalView}</Text>
        <div ref={hostRef} className="saas-content-host" />
      </Surface>
    </main>
  )
}

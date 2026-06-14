import React from 'react'
import { Grid, Text } from '@mantine/core'
import { IconActivity, IconAlertTriangle, IconChecks, IconLayoutDashboard } from '@tabler/icons-react'

import { MetricCard, PageHeader, Surface } from '../components/Enterprise.jsx'
import { decodeText } from '../lib/text.js'
import { getPageTitle, getSectionForFile, sectionLabels } from '../lib/pageMeta.js'

function collectMetrics(sourceContent) {
  const cards = sourceContent.querySelectorAll('.health-card, .small-box, .info-box, .dr-card, .qd-card, .b-card').length
  const warnings = sourceContent.querySelectorAll('.health-status.warning, .label-warning, .badge-warning, .alert-warning').length
  const critical = sourceContent.querySelectorAll('.health-status.critical, .label-danger, .badge-danger, .alert-danger').length
  const actions = sourceContent.querySelectorAll('a.btn, button.btn, input[type="submit"]').length

  return [
    { label: 'الوحدات', value: cards, description: 'بطاقات ومؤشرات تشغيلية', icon: IconLayoutDashboard, tone: 'primary' },
    { label: 'سليم', value: Math.max(0, cards - warnings - critical), description: 'مؤشرات دون إنذار', icon: IconChecks, tone: 'success' },
    { label: 'تنبيهات', value: warnings, description: 'تحتاج متابعة', icon: IconAlertTriangle, tone: 'warning' },
    { label: 'إجراءات', value: actions, description: 'أوامر متاحة', icon: IconActivity, tone: 'primary' },
  ]
}

export default function ContentPage({ sourceContent, pageName }) {
  const hostRef = React.useRef(null)
  const originalParentRef = React.useRef(sourceContent.parentNode)
  const nextSiblingRef = React.useRef(sourceContent.nextSibling)
  const metrics = React.useMemo(() => collectMetrics(sourceContent), [sourceContent])
  const title = decodeText(getPageTitle(pageName))
  const section = sectionLabels[getSectionForFile(pageName)] || 'النظام'

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
    <main className="saas-page saas-content-page" dir="rtl">
      <PageHeader
        eyebrow={section}
        title={title}
        description="واجهة تشغيلية موحدة تعرض محتوى الصفحة الأصلي داخل إطار SaaS حديث مع الحفاظ على المسارات والإجراءات كما هي."
      />

      <Grid gutter="md" mb="md">
        {metrics.map((metric) => (
          <Grid.Col span={{ base: 12, sm: 6, lg: 3 }} key={metric.label}>
            <MetricCard {...metric} />
          </Grid.Col>
        ))}
      </Grid>

      <Surface className="saas-content-surface">
        <Text className="saas-card-eyebrow" mb="xs">Operational view</Text>
        <div ref={hostRef} className="saas-content-host" />
      </Surface>
    </main>
  )
}

import React from 'react'
import { Badge, Button, Card, Group, SimpleGrid, Text, ThemeIcon } from '@mantine/core'
import {
  IconAdjustmentsHorizontal,
  IconBell,
  IconCode,
  IconDeviceFloppy,
  IconForms,
  IconPhoto,
  IconPlug,
  IconSettings,
  IconWorld,
} from '@tabler/icons-react'

import { MetricCard, PageHeader } from '../components/Enterprise.jsx'
import { decodeText } from '../lib/text.js'
import { getLanguage, pageTranslations, sectionLabels } from '../lib/pageMeta.js'

const hiddenSettingsTabs = new Set(['tab_4', 'tab_5', 'tab_6', 'tab_7'])

const iconMap = [
  { test: /logo|favicon|banner|photo|image/i, icon: IconPhoto, tone: 'primary' },
  { test: /contact|footer|home|products/i, icon: IconWorld, tone: 'success' },
  { test: /message|telegram|sms|notification/i, icon: IconBell, tone: 'warning' },
  { test: /pixel|ecotrack|integration/i, icon: IconPlug, tone: 'primary' },
  { test: /script|head|body|code/i, icon: IconCode, tone: 'danger' },
]

function tabMeta(title) {
  return iconMap.find((item) => item.test.test(title)) || { icon: IconSettings, tone: 'primary' }
}

function scrapeSettingsTabs(sourceTabs) {
  const anchors = Array.from(sourceTabs.querySelectorAll('.nav-tabs a[href^="#"]'))

  return anchors.map((anchor, index) => {
    const id = anchor.getAttribute('href')?.replace('#', '')
    const pane = id ? sourceTabs.querySelector(`#${CSS.escape(id)}`) : null
    if (!id || !pane || hiddenSettingsTabs.has(id)) return null

    const lang = getLanguage()
    const fallback = lang === 'ar' ? `\u0642\u0633\u0645 ${index + 1}` : `Section ${index + 1}`
    const title = decodeText(anchor.textContent || fallback)
    const forms = pane.querySelectorAll('form').length
    const fields = pane.querySelectorAll('input, select, textarea').length
    const uploads = pane.querySelectorAll('input[type="file"]').length
    const meta = tabMeta(title)

    return {
      id,
      title,
      pane,
      forms,
      fields,
      uploads,
      Icon: meta.icon,
      tone: meta.tone,
      active: anchor.closest('li')?.classList.contains('active') || pane.classList.contains('active'),
    }
  }).filter(Boolean)
}

export default function SettingsPage({ sourceTabs }) {
  const lang = getLanguage()
  const trans = pageTranslations[lang] || pageTranslations['ar']

  const [tabs] = React.useState(() => scrapeSettingsTabs(sourceTabs))
  const [activeId, setActiveId] = React.useState(() => tabs.find((tab) => tab.active)?.id || tabs[0]?.id || '')
  const paneHostRef = React.useRef(null)
  const tabContentRef = React.useRef(sourceTabs.querySelector('.tab-content'))

  const activeTab = tabs.find((tab) => tab.id === activeId) || tabs[0]
  const totalForms = tabs.reduce((sum, tab) => sum + tab.forms, 0)
  const totalFields = tabs.reduce((sum, tab) => sum + tab.fields, 0)
  const totalUploads = tabs.reduce((sum, tab) => sum + tab.uploads, 0)

  React.useEffect(() => {
    document.querySelector('.content-header')?.classList.add('admin-source-hidden')
    sourceTabs.style.display = 'none'
    sourceTabs.closest('.box, .row, .col-md-12')?.classList.add('admin-settings-source-shell')
  }, [sourceTabs])

  React.useLayoutEffect(() => {
    const host = paneHostRef.current
    const pane = activeTab?.pane
    const tabContent = tabContentRef.current
    if (!host || !pane) return undefined

    host.replaceChildren()
    host.appendChild(pane)
    pane.style.display = 'block'
    pane.classList.add('active', 'saas-settings-active-pane')

    return () => {
      pane.classList.remove('saas-settings-active-pane')
      pane.style.display = ''
      tabContent?.appendChild(pane)
    }
  }, [activeTab])

  if (!tabs.length) return null

  return (
    <main className="saas-page saas-settings-page" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      <PageHeader
        eyebrow={sectionLabels.system || 'System'}
        title={trans.storeSettings}
        description={trans.settingsDesc}
      />

      <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }} spacing="md" mb="md">
        <MetricCard label={trans.sections} value={tabs.length} description={trans.sectionsDesc} icon={IconAdjustmentsHorizontal} tone="primary" />
        <MetricCard label={trans.forms} value={totalForms} description={trans.formsDesc} icon={IconForms} tone="success" />
        <MetricCard label={trans.fields} value={totalFields} description={trans.fieldsDesc} icon={IconSettings} tone="warning" />
        <MetricCard label={trans.files} value={totalUploads} description={trans.filesDesc} icon={IconPhoto} tone="primary" />
      </SimpleGrid>

      <Card className="saas-surface saas-settings-picker" withBorder>
        <Text className="saas-card-eyebrow">Settings</Text>
        <Text className="saas-card-title" mb="md">{trans.settingsSections}</Text>
        <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }} spacing="sm">
          {tabs.map((tab) => (
            <button
              type="button"
              className={`saas-settings-tab ${tab.id === activeTab.id ? 'is-active' : ''}`}
              onClick={() => setActiveId(tab.id)}
              key={tab.id}
            >
              <ThemeIcon radius="md" variant="light" color={tab.tone === 'danger' ? 'red' : tab.tone === 'warning' ? 'orange' : tab.tone === 'success' ? 'teal' : 'indigo'}>
                <tab.Icon size={17} stroke={1.8} />
              </ThemeIcon>
              <span>
                <strong>{tab.title}</strong>
                <small>{tab.forms} {trans.formCount}{trans.comma} {tab.fields} {trans.fieldCount}</small>
              </span>
            </button>
          ))}
        </SimpleGrid>
      </Card>

      <Card className="saas-surface saas-settings-editor" withBorder>
        <Group justify="space-between" align="flex-start" mb="md">
          <div>
            <Text className="saas-card-eyebrow">Configuration</Text>
            <Text className="saas-card-title">{activeTab.title}</Text>
          </div>
          <Group gap="xs">
            <Badge variant="light" color="gray">{activeTab.fields} {trans.fieldCount}</Badge>
            <Button
              size="sm"
              radius="md"
              color="indigo"
              leftSection={<IconDeviceFloppy size={15} />}
              onClick={() => paneHostRef.current?.querySelector('button[type="submit"], input[type="submit"]')?.click()}
            >
              {trans.saveSection}
            </Button>
          </Group>
        </Group>
        <div ref={paneHostRef} className="saas-settings-pane-host" />
      </Card>
    </main>
  )
}


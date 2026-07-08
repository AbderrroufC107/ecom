import React from 'react'
import {
  Badge,
  Box,
  Button,
  Checkbox,
  Grid,
  Group,
  NumberInput,
  PasswordInput,
  Progress,
  Select,
  Stepper,
  Switch,
  Tabs,
  Text,
  TextInput,
  Textarea,
  ThemeIcon,
} from '@mantine/core'
import {
  IconArrowLeft,
  IconArrowRight,
  IconCheck,
  IconInfoCircle,
  IconPackage,
  IconPhoto,
  IconSettings,
  IconSpeakerphone,
  IconTag,
  IconUpload,
} from '@tabler/icons-react'

import { PageHeader, Surface, ToolbarButton } from '../components/Enterprise.jsx'
import { cleanText, decodeText } from '../lib/text.js'
import { getPageTitle, getLanguage, pageTranslations } from '../lib/pageMeta.js'



function inferSection(input) {
  const name = (input.getAttribute('name') || '').toLowerCase()
  const groupText = cleanText(input.closest('.form-group, fieldset, .box')?.textContent || '').toLowerCase()
  const value = `${name} ${groupText}`

  if (/price|qty|quantity|offer|purchase|discount|amount|cost/.test(value)) return 'pricing'
  // Split the old catch-all "content" into: main descriptions vs. announcement/SEO,
  // so neither step becomes one long strip of tall textareas.
  if (/announcement|seo|meta|keyword|tagline|slug/.test(value)) return 'seo'
  if (/description|content|detail|short|note/.test(value)) return 'content'
  if (/photo|image|file|landing|banner|gallery|favicon|logo/.test(value)) return 'media'
  if (/active|featured|status|pixel|color|size|tag|publish|popular/.test(value)) return 'options'
  return 'basics'
}

function fieldLabel(input, fallbackIndex) {
  const id = input.getAttribute('id')
  const namedLabel = id ? document.querySelector(`label[for="${id}"]`) : null
  const groupLabel = input.closest('.form-group, .control-group, fieldset')?.querySelector('label.control-label, label')
  const name = input.getAttribute('name') || `field_${fallbackIndex}`
  return decodeText(namedLabel?.textContent || groupLabel?.textContent || name.replace(/[_-]+/g, ' '))
}

function scrapeFormFields(form) {
  const fields = []
  const inputs = Array.from(form.querySelectorAll('input, select, textarea')).filter((input) => {
    const type = (input.getAttribute('type') || '').toLowerCase()
    const name = input.getAttribute('name')
    return name && !['hidden', 'submit', 'button', 'reset'].includes(type) && name !== 'form1'
  })

  inputs.forEach((input, index) => {
    const tag = input.tagName.toLowerCase()
    const inputType = (input.getAttribute('type') || 'text').toLowerCase()
    const type = tag === 'select' ? 'select' : tag === 'textarea' ? 'textarea' : inputType
    const name = input.getAttribute('name')
    const options = type === 'select'
      ? Array.from(input.options).map((option) => ({ value: option.value, label: decodeText(option.textContent) }))
      : []

    fields.push({
      id: `${name}-${index}`,
      name,
      label: fieldLabel(input, index).replace(/:$/, ''),
      type,
      value: type === 'checkbox' || type === 'radio' ? input.checked : input.value || '',
      placeholder: decodeText(input.getAttribute('placeholder') || ''),
      required: input.required || input.closest('.form-group')?.classList.contains('required'),
      options,
      section: inferSection(input),
      legacyInput: input,
    })
  })

  return fields
}

export default function ProductForm({ sourceForm, isEdit, pageName = '', titleOverride = '', eyebrow = '' }) {
  const lang = getLanguage()
  const trans = pageTranslations[lang] || pageTranslations['ar']

  const [fields, setFields] = React.useState([])
  const [values, setValues] = React.useState({})
  const [activeStep, setActiveStep] = React.useState(0)

  const localizedSections = React.useMemo(() => {
    return [
      { id: 'basics', label: trans.basics, description: trans.basicsDesc, icon: IconPackage },
      { id: 'pricing', label: trans.pricing, description: trans.pricingDesc, icon: IconTag },
      { id: 'content', label: trans.contentTab, description: trans.contentTabDesc, icon: IconInfoCircle },
      { id: 'seo', label: trans.seoTab, description: trans.seoTabDesc, icon: IconSpeakerphone },
      { id: 'media', label: trans.media, description: trans.mediaDesc, icon: IconPhoto },
      { id: 'options', label: trans.options, description: trans.optionsDesc, icon: IconSettings },
    ]
  }, [trans])

  React.useEffect(() => {
    if (!sourceForm) return
    let frame = 0
    const refreshFields = () => {
      const scraped = scrapeFormFields(sourceForm)
      setFields(scraped)
      setValues((prev) => {
        const next = {}
        scraped.forEach((field) => {
          next[field.id] = Object.prototype.hasOwnProperty.call(prev, field.id) ? prev[field.id] : field.value
          if (field.type === 'file' && !field.legacyInput.dataset.adminReactFileBound) {
            field.legacyInput.dataset.adminReactFileBound = '1'
            field.legacyInput.addEventListener('change', () => {
              setValues((current) => ({ ...current, [field.id]: field.legacyInput.files?.[0]?.name || '' }))
            })
          }
        })
        return next
      })
    }

    refreshFields()
    sourceForm.classList.add('admin-source-hidden')
    document.querySelectorAll('.content-header').forEach((node) => {
      node.style.display = 'none'
    })

    const observer = new MutationObserver(() => {
      window.cancelAnimationFrame(frame)
      frame = window.requestAnimationFrame(refreshFields)
    })
    observer.observe(sourceForm, { childList: true, subtree: true })

    return () => {
      window.cancelAnimationFrame(frame)
      observer.disconnect()
      sourceForm.classList.remove('admin-source-hidden')
    }
  }, [sourceForm])

  const progress = React.useMemo(() => {
    if (!fields.length) return 0
    const filled = fields.filter((field) => {
      const value = values[field.id]
      return field.type === 'checkbox' || field.type === 'radio' ? value : Boolean(String(value || '').trim())
    }).length
    return Math.round((filled / fields.length) * 100)
  }, [fields, values])

  if (!fields.length) return null

  const isRtl = lang === 'ar'
  const prevIcon = isRtl ? <IconArrowRight size={16} /> : <IconArrowLeft size={16} />
  const nextIcon = isRtl ? <IconArrowLeft size={16} /> : <IconArrowRight size={16} />

  const titleFallback = isEdit ? trans.editData : trans.addData
  const defaultFormTitle = isEdit ? trans.editProductDetails : trans.addNewProduct
  const title = titleOverride || (pageName ? getPageTitle(pageName, titleFallback) : defaultFormTitle)

  const eyebrowVal = eyebrow || '\u0627\u0644\u0645\u062a\u062c\u0631 \u0648\u0627\u0644\u0645\u0646\u062a\u062c\u0627\u062a'
  const eyebrowTranslated = eyebrowVal === '\u0627\u0644\u0645\u062a\u062c\u0631 \u0648\u0627\u0644\u0645\u0646\u062a\u062c\u0627\u062a' ? (trans.catalog || eyebrowVal) : eyebrowVal

  const submit = () => {
    const submitButton = sourceForm.querySelector('button[name="form1"], input[name="form1"], button[type="submit"], input[type="submit"]')
    if (submitButton) submitButton.click()
    else sourceForm.submit()
  }

  const updateField = (field, value) => {
    setValues((prev) => ({ ...prev, [field.id]: value }))
    const input = field.legacyInput

    if (field.type === 'checkbox' || field.type === 'radio') {
      input.checked = Boolean(value)
    } else if (field.type !== 'file') {
      input.value = value ?? ''
    }

    input.dispatchEvent(new Event('input', { bubbles: true }))
    input.dispatchEvent(new Event('change', { bubbles: true }))

    if (window.jQuery && input.tagName.toLowerCase() === 'textarea' && window.jQuery(input).data('summernote')) {
      window.jQuery(input).summernote('code', value || '')
    }
  }

  const grouped = localizedSections.map((section) => ({
    ...section,
    fields: fields.filter((field) => field.section === section.id),
  })).filter((section) => section.fields.length)

  const activeSection = grouped[activeStep] || grouped[0]

  return (
    <main className="saas-page" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      <PageHeader
        eyebrow={eyebrowTranslated}
        title={title}
        description={trans.formDesc}
        metrics={[
          { label: trans.fields, value: fields.length },
          { label: trans.completeness, value: `${progress}%` },
        ]}
        actions={<ToolbarButton icon={IconCheck} variant="filled" onClick={submit}>{isEdit ? trans.saveChanges : trans.save}</ToolbarButton>}
      />

      <Grid gutter="md">
        <Grid.Col span={{ base: 12, lg: 8 }}>
          <Surface className="saas-form-surface">
            <Stepper active={activeStep} onStepClick={setActiveStep} mb="lg" className="saas-stepper">
              {grouped.map((section) => (
                <Stepper.Step
                  key={section.id}
                  label={section.label}
                  description={section.description}
                  icon={<section.icon size={16} />}
                />
              ))}
            </Stepper>

            <Tabs value={activeSection.id} className="saas-form-mobile-tabs" onChange={(value) => {
              const nextIndex = grouped.findIndex((section) => section.id === value)
              if (nextIndex >= 0) setActiveStep(nextIndex)
            }}>
              <Tabs.List>
                {grouped.map((section) => (
                  <Tabs.Tab value={section.id} key={section.id}>{section.label}</Tabs.Tab>
                ))}
              </Tabs.List>
            </Tabs>

            <div className="saas-form-grid">
              {activeSection.fields.map((field) => renderField(field, values[field.id], updateField, trans))}
            </div>

            <Group justify="space-between" mt="xl">
              <Button
                variant="default"
                radius="md"
                disabled={activeStep === 0}
                rightSection={prevIcon}
                onClick={() => setActiveStep((value) => Math.max(0, value - 1))}
              >
                {trans.previous}
              </Button>
              {activeStep < grouped.length - 1 ? (
                <Button
                  color="indigo"
                  radius="md"
                  leftSection={nextIcon}
                  onClick={() => setActiveStep((value) => Math.min(grouped.length - 1, value + 1))}
                >
                  {trans.next}
                </Button>
              ) : (
                <Button color="teal" radius="md" leftSection={<IconCheck size={16} />} onClick={submit}>
                  {trans.confirmSave}
                </Button>
              )}
            </Group>
          </Surface>
        </Grid.Col>

        <Grid.Col span={{ base: 12, lg: 4 }}>
          <Surface className="saas-form-summary">
            <Group gap="sm" mb="md">
              <ThemeIcon variant="light" color="indigo" radius="md">
                <IconPackage size={18} />
              </ThemeIcon>
              <div>
                <Text fw={850}>{trans.formSummary}</Text>
                <Text size="xs" c="dimmed">{trans.syncDesc}</Text>
              </div>
            </Group>

            <Text size="xs" c="dimmed" mb={6}>{trans.compRatio}</Text>
            <Group justify="space-between" mb="xs">
              <Text fw={900} size="xl" c="indigo.7">{progress}%</Text>
              <Badge variant="light" color={progress > 75 ? 'teal' : progress > 40 ? 'orange' : 'red'}>
                {progress > 75 ? trans.ready : trans.needsReview}
              </Badge>
            </Group>
            <Progress value={progress} radius="xl" size={8} mb="lg" />

            <div className="saas-form-summary-list">
              {grouped.map((section) => (
                <div key={section.id}>
                  <span>{section.label}</span>
                  <strong>{section.fields.length}</strong>
                </div>
              ))}
            </div>
          </Surface>
        </Grid.Col>
      </Grid>
    </main>
  )
}

function renderField(field, value, updateField, trans) {
  const common = {
    label: field.label,
    description: field.required ? trans.required : undefined,
    placeholder: field.placeholder,
    size: 'md',
  }

  if (field.type === 'select') {
    return (
      <Select
        key={field.id}
        {...common}
        data={field.options}
        value={String(value ?? '')}
        searchable
        clearable={!field.required}
        onChange={(next) => updateField(field, next || '')}
      />
    )
  }

  if (field.type === 'textarea') {
    return (
      <Textarea
        key={field.id}
        {...common}
        value={String(value ?? '')}
        minRows={4}
        maxRows={9}
        autosize
        onChange={(event) => updateField(field, event.currentTarget.value)}
      />
    )
  }

  if (field.type === 'checkbox') {
    return (
      <Switch
        key={field.id}
        label={field.label}
        checked={Boolean(value)}
        size="md"
        onChange={(event) => updateField(field, event.currentTarget.checked)}
      />
    )
  }

  if (field.type === 'radio') {
    return (
      <Checkbox
        key={field.id}
        label={field.label}
        checked={Boolean(value)}
        size="md"
        onChange={(event) => updateField(field, event.currentTarget.checked)}
      />
    )
  }

  if (field.type === 'file') {
    return (
      <Box key={field.id} className="saas-file-field">
        <Text size="md" fw={750} mb={6}>{field.label}</Text>
        <Button size="md" variant="default" radius="md" leftSection={<IconUpload size={18} />} onClick={() => field.legacyInput.click()}>
          {trans.chooseFile}
        </Button>
        <Text size="sm" c="dimmed" mt={6}>{value || trans.noFileChosen}</Text>
      </Box>
    )
  }

  if (field.type === 'number') {
    return (
      <NumberInput
        key={field.id}
        {...common}
        value={value === '' ? '' : Number(value)}
        onChange={(next) => updateField(field, next ?? '')}
      />
    )
  }

  if (field.type === 'password') {
    return (
      <PasswordInput
        key={field.id}
        {...common}
        value={String(value ?? '')}
        onChange={(event) => updateField(field, event.currentTarget.value)}
      />
    )
  }

  return (
    <TextInput
      key={field.id}
      {...common}
      type={['email', 'url', 'tel', 'date', 'time'].includes(field.type) ? field.type : 'text'}
      value={String(value ?? '')}
      onChange={(event) => updateField(field, event.currentTarget.value)}
    />
  )
}


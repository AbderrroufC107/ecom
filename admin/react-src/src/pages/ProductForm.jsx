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
  IconTag,
  IconUpload,
} from '@tabler/icons-react'

import { PageHeader, Surface, ToolbarButton } from '../components/Enterprise.jsx'
import { cleanText, decodeText } from '../lib/text.js'
import { getPageTitle } from '../lib/pageMeta.js'

const sections = [
  { id: 'basics', label: 'الأساسيات', description: 'اسم المنتج والتصنيف', icon: IconPackage },
  { id: 'pricing', label: 'التسعير والمخزون', description: 'الأسعار والكميات', icon: IconTag },
  { id: 'content', label: 'المحتوى', description: 'الوصف والملاحظات', icon: IconInfoCircle },
  { id: 'media', label: 'الصور والملفات', description: 'الصور الرئيسية والمعرض', icon: IconPhoto },
  { id: 'options', label: 'النشر والتتبع', description: 'الحالة والخيارات', icon: IconSettings },
]

function inferSection(input) {
  const name = (input.getAttribute('name') || '').toLowerCase()
  const groupText = cleanText(input.closest('.form-group, fieldset, .box')?.textContent || '').toLowerCase()
  const value = `${name} ${groupText}`

  if (/price|qty|quantity|offer|purchase|discount|amount|cost/.test(value)) return 'pricing'
  if (/description|content|detail|short|note|announcement|seo/.test(value)) return 'content'
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

export default function ProductForm({ sourceForm, isEdit, pageName = '', titleOverride = '', eyebrow = 'المتجر والمنتجات' }) {
  const [fields, setFields] = React.useState([])
  const [values, setValues] = React.useState({})
  const [activeStep, setActiveStep] = React.useState(0)

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

  const title = titleOverride || (pageName ? getPageTitle(pageName, isEdit ? 'تعديل البيانات' : 'إضافة البيانات') : (isEdit ? 'تعديل بيانات المنتج' : 'إضافة منتج جديد'))

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

  const submit = () => {
    const submitButton = sourceForm.querySelector('button[name="form1"], input[name="form1"], button[type="submit"], input[type="submit"]')
    if (submitButton) submitButton.click()
    else sourceForm.submit()
  }

  const grouped = sections.map((section) => ({
    ...section,
    fields: fields.filter((field) => field.section === section.id),
  })).filter((section) => section.fields.length)

  const activeSection = grouped[activeStep] || grouped[0]

  return (
    <main className="saas-page" dir="rtl">
      <PageHeader
        eyebrow={eyebrow}
        title={title}
        description="نموذج حديث متزامن مع النموذج الأصلي لضمان بقاء الحفظ، التحقق، والرفع كما هي في الخلفية."
        metrics={[
          { label: 'الحقول', value: fields.length },
          { label: 'الاكتمال', value: `${progress}%` },
        ]}
        actions={<ToolbarButton icon={IconCheck} variant="filled" onClick={submit}>{isEdit ? 'حفظ التعديلات' : 'حفظ'}</ToolbarButton>}
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
              {activeSection.fields.map((field) => renderField(field, values[field.id], updateField))}
            </div>

            <Group justify="space-between" mt="xl">
              <Button
                variant="default"
                radius="md"
                disabled={activeStep === 0}
                rightSection={<IconArrowRight size={16} />}
                onClick={() => setActiveStep((value) => Math.max(0, value - 1))}
              >
                السابق
              </Button>
              {activeStep < grouped.length - 1 ? (
                <Button
                  color="indigo"
                  radius="md"
                  leftSection={<IconArrowLeft size={16} />}
                  onClick={() => setActiveStep((value) => Math.min(grouped.length - 1, value + 1))}
                >
                  التالي
                </Button>
              ) : (
                <Button color="teal" radius="md" leftSection={<IconCheck size={16} />} onClick={submit}>
                  تأكيد وحفظ
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
                <Text fw={850}>ملخص النموذج</Text>
                <Text size="xs" c="dimmed">يتزامن مع نموذج PHP الأصلي</Text>
              </div>
            </Group>

            <Text size="xs" c="dimmed" mb={6}>نسبة الاكتمال</Text>
            <Group justify="space-between" mb="xs">
              <Text fw={900} size="xl" c="indigo.7">{progress}%</Text>
              <Badge variant="light" color={progress > 75 ? 'teal' : progress > 40 ? 'orange' : 'red'}>
                {progress > 75 ? 'جاهز' : 'يحتاج مراجعة'}
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

function renderField(field, value, updateField) {
  const common = {
    label: field.label,
    description: field.required ? 'مطلوب' : undefined,
    placeholder: field.placeholder,
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
        onChange={(event) => updateField(field, event.currentTarget.checked)}
      />
    )
  }

  if (field.type === 'file') {
    return (
      <Box key={field.id} className="saas-file-field">
        <Text size="sm" fw={750} mb={6}>{field.label}</Text>
        <Button variant="default" radius="md" leftSection={<IconUpload size={16} />} onClick={() => field.legacyInput.click()}>
          اختيار ملف
        </Button>
        <Text size="xs" c="dimmed" mt={6}>{value || 'لم يتم اختيار ملف'}</Text>
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

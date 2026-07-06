import {
  ActionIcon,
  Badge,
  Box,
  Button,
  Card,
  Group,
  Progress,
  SimpleGrid,
  Skeleton,
  Stack,
  Text,
  ThemeIcon,
} from '@mantine/core'
import {
  IconArrowLeft,
  IconChevronLeft,
  IconCircleCheck,
  IconClock,
  IconDatabaseOff,
  IconDots,
  IconExclamationCircle,
  IconExternalLink,
  IconInfoCircle,
  IconPlus,
} from '@tabler/icons-react'

import { decodeText } from '../lib/text.js'
import { sharedTrans, legacyMappers } from '../lib/pageMeta.js'

export const palette = {
  primary: '#4f46e5',
  success: '#14b8a6',
  warning: '#d97706',
  danger: '#dc2626',
  background: '#f8fafc',
  card: '#ffffff',
  text: '#0f172a',
  muted: '#64748b',
}

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
  metrics,
  compact = false,
}) {
  return (
    <section className={`saas-page-header${compact ? ' is-compact' : ''}`}>
      <div className="saas-page-header-main">
        {eyebrow ? <Text className="saas-eyebrow">{decodeText(eyebrow)}</Text> : null}
        <Group gap="xs" align="center" wrap="nowrap">
          <Text component="h1" className="saas-page-title">
            {decodeText(title)}
          </Text>
        </Group>
        {description ? (
          <Text className="saas-page-description">{decodeText(description)}</Text>
        ) : null}
      </div>

      {metrics?.length ? (
        <div className="saas-header-metrics">
          {metrics.slice(0, 4).map((metric) => (
            <div className="saas-header-metric" key={`${metric.label}-${metric.value}`}>
              <span>{decodeText(metric.label)}</span>
              <strong>{decodeText(metric.value)}</strong>
            </div>
          ))}
        </div>
      ) : null}

      {actions ? <Group className="saas-page-actions">{actions}</Group> : null}
    </section>
  )
}

export function Surface({ children, title, eyebrow, action, className = '', compact = false }) {
  return (
    <Card className={`saas-surface ${compact ? 'is-compact' : ''} ${className}`} withBorder>
      {(title || eyebrow || action) && (
        <Group justify="space-between" align="flex-start" mb="md" gap="md">
          <div>
            {eyebrow ? <Text className="saas-card-eyebrow">{decodeText(eyebrow)}</Text> : null}
            {title ? <Text className="saas-card-title">{decodeText(title)}</Text> : null}
          </div>
          {action}
        </Group>
      )}
      {children}
    </Card>
  )
}

export function MetricCard({
  label,
  value,
  description,
  icon: Icon = IconInfoCircle,
  tone = 'primary',
  progress,
}) {
  return (
    <Card className={`saas-metric-card tone-${tone}`} withBorder>
      <Group justify="space-between" align="flex-start" gap="md" wrap="nowrap">
        <div className="saas-metric-copy">
          <Text className="saas-metric-label">{decodeText(label)}</Text>
          <Text className="saas-metric-value">{decodeText(value)}</Text>
          {description ? <Text className="saas-metric-description">{decodeText(description)}</Text> : null}
        </div>
        <ThemeIcon className="saas-metric-icon" variant="light">
          <Icon size={20} stroke={1.8} />
        </ThemeIcon>
      </Group>
      {typeof progress === 'number' ? (
        <Progress value={Math.max(0, Math.min(100, progress))} mt="md" radius="xl" size={6} />
      ) : null}
    </Card>
  )
}

export function StatusPill({ children, tone, icon }) {
  const normalizedTone = tone || statusTone(children)
  const Icon = icon || statusIcon(normalizedTone)
  return (
    <Badge
      className={`saas-status-pill tone-${normalizedTone}`}
      leftSection={Icon ? <Icon size={12} stroke={2} /> : null}
      variant="light"
    >
      {decodeText(children)}
    </Badge>
  )
}

export function EmptyState({
  title,
  description,
  action,
}) {
  const displayTitle = title || sharedTrans.noData
  const displayDescription = description || sharedTrans.noDataDesc
  return (
    <Stack className="saas-empty-state" align="center" gap="xs">
      <ThemeIcon size={44} radius="md" variant="light" color="gray">
        <IconDatabaseOff size={22} stroke={1.7} />
      </ThemeIcon>
      <Text fw={800}>{decodeText(displayTitle)}</Text>
      <Text size="sm" c="dimmed" ta="center">
        {decodeText(displayDescription)}
      </Text>
      {action}
    </Stack>
  )
}

export function SkeletonPage() {
  return (
    <Box className="saas-page">
      <section className="saas-page-header">
        <div className="saas-page-header-main">
          <Skeleton height={12} width={110} radius="xl" mb="sm" />
          <Skeleton height={30} width={260} radius="sm" mb="sm" />
          <Skeleton height={14} width="60%" radius="xl" />
        </div>
      </section>
      <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }} spacing="md" mb="md">
        {[0, 1, 2, 3].map((item) => (
          <Card className="saas-surface" withBorder key={item}>
            <Skeleton height={16} width="45%" mb="lg" />
            <Skeleton height={28} width="70%" mb="sm" />
            <Skeleton height={10} width="90%" />
          </Card>
        ))}
      </SimpleGrid>
      <Card className="saas-surface" withBorder>
        <Skeleton height={320} radius="md" />
      </Card>
    </Box>
  )
}

export function ToolbarButton({ href, children, icon: Icon = IconExternalLink, variant = 'default', onClick }) {
  const handleClick = onClick || (href && href.startsWith('javascript:') ? (e) => {
    e.preventDefault()
    try { /* eslint-disable-next-line no-new-func */ new Function(href.slice('javascript:'.length))() } catch(err) { console.warn('action error', err) }
  } : undefined)
  const props = (href && !href.startsWith('javascript:')) ? { component: 'a', href } : {}
  return (
    <Button
      {...props}
      className="saas-toolbar-button"
      size="sm"
      radius="md"
      variant={variant}
      color={variant === 'filled' ? 'indigo' : 'gray'}
      leftSection={<Icon size={16} stroke={1.8} />}
      onClick={handleClick}
    >
      {decodeText(children)}
    </Button>
  )
}

export function IconOnlyAction({ label, icon: Icon = IconDots, href, color = 'gray', onClick }) {
  const props = href ? { component: 'a', href } : {}
  return (
    <ActionIcon
      {...props}
      aria-label={decodeText(label)}
      title={decodeText(label)}
      variant="subtle"
      color={color}
      radius="md"
      onClick={onClick}
    >
      <Icon size={17} stroke={1.8} />
    </ActionIcon>
  )
}

export function AddButton({ href, children, icon = IconPlus, onClick }) {
  const label = children || sharedTrans.add
  return (
    <ToolbarButton href={href} icon={icon} variant="filled" onClick={onClick}>
      {label}
    </ToolbarButton>
  )
}

export function LinkAction({ href, children }) {
  const label = children || sharedTrans.viewAll
  return (
    <Button
      component="a"
      href={href}
      variant="subtle"
      color="indigo"
      size="xs"
      radius="md"
      rightSection={<IconChevronLeft size={14} stroke={1.8} />}
    >
      {decodeText(label)}
    </Button>
  )
}

export function BackAction({ href = 'javascript:history.back()', children }) {
  const label = children || sharedTrans.back
  return (
    <Button
      component="a"
      href={href}
      variant="default"
      size="sm"
      radius="md"
      rightSection={<IconArrowLeft size={15} stroke={1.8} />}
    >
      {decodeText(label)}
    </Button>
  )
}

function statusTone(value) {
  const text = decodeText(value).toLowerCase()
  if (legacyMappers.toneSuccess.test(text)) return 'success'
  if (legacyMappers.toneWarning.test(text)) return 'warning'
  if (legacyMappers.toneDanger.test(text)) return 'danger'
  return 'neutral'
}

function statusIcon(tone) {
  if (tone === 'success') return IconCircleCheck
  if (tone === 'warning') return IconClock
  if (tone === 'danger') return IconExclamationCircle
  return IconInfoCircle
}

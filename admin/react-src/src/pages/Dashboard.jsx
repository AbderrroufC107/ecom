import React from 'react'
import { Anchor, Badge, Grid, Group, SimpleGrid, Table, Text, ThemeIcon } from '@mantine/core'
import {
  IconAlertTriangle,
  IconArrowUpRight,
  IconChartAreaLine,
  IconCircleCheck,
  IconClipboardList,
  IconCurrencyDollar,
  IconPackage,
  IconShoppingCart,
  IconUsers,
} from '@tabler/icons-react'
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'

import { LinkAction, MetricCard, PageHeader, StatusPill, Surface, ToolbarButton } from '../components/Enterprise.jsx'
import { decodeText, parseNumeric } from '../lib/text.js'

function text(selector) {
  return decodeText(document.querySelector(selector)?.textContent || '')
}

function parseScriptArray(name) {
  let result = []
  document.querySelectorAll('script').forEach((script) => {
    const match = (script.textContent || '').match(new RegExp(`${name}\\s*=\\s*(\\[[^;]+\\])`))
    if (!match) return
    try {
      result = JSON.parse(match[1])
    } catch {
      result = []
    }
  })
  return result
}

function scrapeKpis() {
  const cards = Array.from(document.querySelectorAll('.admin-kpi-card, .sd-stat, .stat-widget'))
  return cards.map((card, index) => {
    const label = decodeText(card.querySelector('span, h4, .stat-widget-info h4')?.textContent || '')
    const value = decodeText(card.querySelector('strong, h2, .stat-widget-info h2')?.textContent || '0')
    const description = decodeText(card.querySelector('small')?.textContent || '')
    const iconClass = card.querySelector('i')?.className || ''
    let icon = IconShoppingCart
    let tone = 'primary'

    if (/money|line-chart|dollar/.test(iconClass) || /إيراد|القيمة|revenue/i.test(label)) {
      icon = IconCurrencyDollar
      tone = 'success'
    } else if (/user|employee|customer/.test(iconClass) || /عملاء|موظف/.test(label)) {
      icon = IconUsers
      tone = 'primary'
    } else if (/cube|shopping-bag|product/.test(iconClass) || /منتج|مخزون/.test(label)) {
      icon = IconPackage
      tone = /منخفض|low/i.test(label) ? 'danger' : 'warning'
    } else if (/clock|pending|warning/.test(iconClass) || /معلق|انتظار/.test(label)) {
      icon = IconAlertTriangle
      tone = 'warning'
    } else if (/check|completed/.test(iconClass) || /مكتمل|مؤكد/.test(label)) {
      icon = IconCircleCheck
      tone = 'success'
    }

    return { id: `kpi-${index}`, label, value, description, icon, tone }
  })
}

function scrapeMiniCards() {
  return Array.from(document.querySelectorAll('.admin-mini-card, .admin-perf-card')).map((card, index) => ({
    id: `mini-${index}`,
    label: decodeText(card.querySelector('span')?.textContent || ''),
    value: decodeText(card.querySelector('strong')?.textContent || ''),
    description: decodeText(card.querySelector('small')?.textContent || ''),
    tone: card.classList.contains('is-danger') || card.classList.contains('is-trailer')
      ? 'danger'
      : card.classList.contains('is-warning')
        ? 'warning'
        : card.classList.contains('is-leader')
          ? 'success'
          : 'primary',
  })).filter((item) => item.label || item.value)
}

function scrapeRecentOrders() {
  const richItems = Array.from(document.querySelectorAll('.admin-order-list .admin-order-item')).map((item, index) => {
    const main = item.querySelector('.admin-order-main')
    const side = item.querySelector('.admin-order-side')
    const strong = decodeText(main?.querySelector('strong')?.textContent || '')
    const parts = strong.split('-')
    return {
      id: parts[0]?.trim() || `#${index + 1}`,
      product: parts.slice(1).join('-').trim() || strong,
      customer: decodeText(main?.querySelector('p')?.textContent || ''),
      date: decodeText(main?.querySelector('small')?.textContent || ''),
      price: decodeText(side?.querySelector('strong')?.textContent || ''),
      status: decodeText(main?.querySelector('.admin-status-badge')?.textContent || ''),
      href: side?.querySelector('a')?.getAttribute('href') || 'order.php',
    }
  })

  if (richItems.length) return richItems

  const storeTables = Array.from(document.querySelectorAll('.sd-table'))
  const firstTable = storeTables[0]
  if (!firstTable) return []

  return Array.from(firstTable.querySelectorAll('tbody tr')).map((row, index) => {
    const cells = row.querySelectorAll('td')
    const link = row.querySelector('a')
    return {
      id: decodeText(link?.textContent || cells[0]?.textContent || `#${index + 1}`),
      product: decodeText(cells[0]?.textContent || ''),
      customer: '',
      date: decodeText(cells[3]?.textContent || ''),
      price: decodeText(cells[1]?.textContent || ''),
      status: decodeText(cells[2]?.textContent || ''),
      href: link?.getAttribute('href') || 'order.php',
    }
  }).filter((item) => item.id || item.product)
}

function scrapeLowStock() {
  const compact = Array.from(document.querySelectorAll('.admin-compact-list article.admin-compact-item')).map((item) => {
    const stock = item.querySelector('.admin-stock-pill')
    if (!stock) return null
    const main = item.querySelector('.admin-compact-main')
    return {
      name: decodeText(main?.querySelector('strong')?.textContent || ''),
      price: decodeText(main?.querySelector('small')?.textContent || ''),
      qty: decodeText(stock.textContent || ''),
      href: item.querySelector('a')?.getAttribute('href') || 'product.php',
      danger: stock.classList.contains('is-zero'),
    }
  }).filter(Boolean)

  if (compact.length) return compact

  const storeTables = Array.from(document.querySelectorAll('.sd-table'))
  const stockTable = storeTables[1]
  if (stockTable) {
    return Array.from(stockTable.querySelectorAll('tbody tr')).map((row) => {
      const cells = row.querySelectorAll('td')
      return {
        name: decodeText(cells[0]?.textContent || ''),
        qty: decodeText(cells[1]?.textContent || ''),
        price: decodeText(cells[2]?.textContent || ''),
        href: 'product.php',
        danger: parseNumeric(cells[1]?.textContent) <= 0,
      }
    }).filter((item) => item.name)
  }

  return Array.from(document.querySelectorAll('.modern-list li')).map((item) => ({
    name: decodeText(item.querySelector('.item-details h4')?.textContent || ''),
    price: decodeText(item.querySelector('.item-details p')?.textContent || ''),
    qty: decodeText(item.querySelector('.modern-badge')?.textContent || ''),
    href: 'product.php',
    danger: item.querySelector('.modern-badge')?.classList.contains('badge-danger'),
  })).filter((item) => item.name)
}

function scrapeQuickLinks() {
  const links = Array.from(document.querySelectorAll('.admin-quick-links .admin-quick-link')).map((link) => ({
    href: link.getAttribute('href') || '#',
    title: decodeText(link.querySelector('strong')?.textContent || link.textContent),
    description: decodeText(link.querySelector('span')?.textContent || ''),
  }))

  if (links.length) return links

  return [
    { href: 'order.php', title: 'إدارة الطلبات', description: 'متابعة وتأكيد الطلبات' },
    { href: 'product.php', title: 'المنتجات', description: 'المخزون والأسعار' },
    { href: 'incomplete-orders.php', title: 'الاسترجاع', description: 'طلبات غير مكتملة' },
    { href: 'settings.php', title: 'الإعدادات', description: 'إعدادات المتجر' },
  ]
}

function scrapeDashboardData(legacyContainer) {
  if (!legacyContainer) return null

  const isStoreDash = Boolean(document.querySelector('.store-dash, .premium-dashboard'))
  const chartLabels = parseScriptArray('chartLabels')
  const revenueData = parseScriptArray('revenueData')
  const ordersData = parseScriptArray('ordersData')
  const kpis = scrapeKpis()
  const recentOrders = scrapeRecentOrders()
  const lowStock = scrapeLowStock()
  const miniCards = scrapeMiniCards()

  const chartData = chartLabels.length
    ? chartLabels.map((label, index) => ({
        name: decodeText(label),
        revenue: Number(revenueData[index] || 0),
        orders: Number(ordersData[index] || 0),
      }))
    : recentOrders.slice(0, 8).reverse().map((order) => ({
        name: order.id,
        revenue: parseNumeric(order.price),
        orders: 1,
      }))

  return {
    isStoreDash,
    title: isStoreDash ? text('.store-dash .sd-title') || 'لوحة المتجر' : 'لوحة الإدارة',
    greeting: text('.admin-dashboard-hero-main h2') || text('.sd-hero h2') || 'مرحباً بك',
    description: text('.admin-dashboard-hero-main p') || text('.sd-hero p') || text('.admin-dashboard-header p'),
    heroStats: Array.from(document.querySelectorAll('.admin-dashboard-hero-stats .admin-hero-stat, .hero-stats .hero-stat-item, .sd-hero-info')).map((stat) => ({
      label: decodeText(stat.querySelector('span')?.textContent || ''),
      value: decodeText(stat.querySelector('strong')?.textContent || ''),
    })).filter((item) => item.label || item.value),
    kpis,
    miniCards,
    recentOrders,
    lowStock,
    quickLinks: scrapeQuickLinks(),
    chartData,
  }
}

export default function Dashboard({ legacyContainer }) {
  const data = React.useMemo(() => scrapeDashboardData(legacyContainer), [legacyContainer])

  React.useEffect(() => {
    if (!data) return
    legacyContainer.style.display = 'none'
    document.querySelectorAll('.admin-dashboard-header, .store-dash > h1, .store-dash > .sd-subtitle').forEach((node) => {
      node.style.display = 'none'
    })
  }, [data, legacyContainer])

  if (!data) return null

  const kpis = data.kpis.length
    ? data.kpis.slice(0, 4)
    : [
        { id: 'orders', label: 'إجمالي الطلبات', value: '0', description: 'لا توجد بيانات', icon: IconClipboardList, tone: 'primary' },
        { id: 'revenue', label: 'الإيراد', value: '0', description: 'لا توجد بيانات', icon: IconCurrencyDollar, tone: 'success' },
        { id: 'products', label: 'المنتجات', value: '0', description: 'لا توجد بيانات', icon: IconPackage, tone: 'warning' },
        { id: 'customers', label: 'العملاء', value: '0', description: 'لا توجد بيانات', icon: IconUsers, tone: 'primary' },
      ]

  return (
    <main className="saas-page" dir="rtl">
      <PageHeader
        eyebrow={data.isStoreDash ? 'مركز المتجر' : 'ملخص تنفيذي'}
        title={data.greeting}
        description={data.description}
        metrics={data.heroStats}
        actions={(
          <>
            <ToolbarButton href="order.php" icon={IconClipboardList} variant="filled">فتح الطلبات</ToolbarButton>
            <ToolbarButton href="product.php" icon={IconPackage}>المنتجات</ToolbarButton>
          </>
        )}
      />

      <SimpleGrid cols={{ base: 1, sm: 2, xl: 4 }} spacing="md" mb="md">
        {kpis.map((kpi) => (
          <MetricCard
            key={kpi.id}
            label={kpi.label}
            value={kpi.value}
            description={kpi.description}
            icon={kpi.icon}
            tone={kpi.tone}
          />
        ))}
      </SimpleGrid>

      {data.miniCards.length ? (
        <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }} spacing="md" mb="md">
          {data.miniCards.slice(0, 4).map((metric) => (
            <MetricCard
              key={metric.id}
              label={metric.label}
              value={metric.value}
              description={metric.description}
              icon={metric.tone === 'danger' ? IconAlertTriangle : IconChartAreaLine}
              tone={metric.tone}
            />
          ))}
        </SimpleGrid>
      ) : null}

      <Grid gutter="md" mb="md">
        <Grid.Col span={{ base: 12, lg: 8 }}>
          <Surface
            title={data.isStoreDash ? 'منحنى الأداء' : 'قيمة أحدث الطلبات'}
            eyebrow="Analytics"
            action={<LinkAction href="order-statistics.php">الإحصائيات</LinkAction>}
          >
            <div className="saas-chart">
              <ResponsiveContainer width="100%" height={300}>
                {data.isStoreDash ? (
                  <AreaChart data={data.chartData} margin={{ top: 10, right: 8, left: 0, bottom: 0 }}>
                    <defs>
                      <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#4f46e5" stopOpacity={0.22} />
                        <stop offset="95%" stopColor="#4f46e5" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                    <XAxis dataKey="name" tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#64748b' }} />
                    <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#64748b' }} />
                    <Tooltip content={<ChartTooltip />} />
                    <Area type="monotone" dataKey="revenue" stroke="#4f46e5" strokeWidth={3} fill="url(#revenueFill)" />
                    <Area type="monotone" dataKey="orders" stroke="#14b8a6" strokeWidth={2} fill="transparent" />
                  </AreaChart>
                ) : (
                  <BarChart data={data.chartData} margin={{ top: 10, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                    <XAxis dataKey="name" tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#64748b' }} />
                    <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#64748b' }} />
                    <Tooltip content={<ChartTooltip />} />
                    <Bar dataKey="revenue" radius={[6, 6, 0, 0]}>
                      {data.chartData.map((entry, index) => (
                        <Cell key={`${entry.name}-${index}`} fill={index === data.chartData.length - 1 ? '#4f46e5' : '#c7d2fe'} />
                      ))}
                    </Bar>
                  </BarChart>
                )}
              </ResponsiveContainer>
            </div>
          </Surface>
        </Grid.Col>

        <Grid.Col span={{ base: 12, lg: 4 }}>
          <Surface title="إجراءات سريعة" eyebrow="Workflow">
            <div className="saas-quick-grid">
              {data.quickLinks.slice(0, 6).map((link) => (
                <a className="saas-quick-tile" href={link.href} key={`${link.href}-${link.title}`}>
                  <span>
                    <strong>{link.title}</strong>
                    <small>{link.description}</small>
                  </span>
                  <IconArrowUpRight size={16} stroke={1.8} />
                </a>
              ))}
            </div>
          </Surface>
        </Grid.Col>
      </Grid>

      <Grid gutter="md">
        <Grid.Col span={{ base: 12, lg: 8 }}>
          <Surface title="آخر الطلبات النشطة" eyebrow="Orders" action={<LinkAction href="order.php">عرض الكل</LinkAction>}>
            <Table.ScrollContainer minWidth={720}>
              <Table className="saas-table" verticalSpacing="md">
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>الطلب</Table.Th>
                    <Table.Th>العميل</Table.Th>
                    <Table.Th>التاريخ</Table.Th>
                    <Table.Th>الحالة</Table.Th>
                    <Table.Th ta="left">القيمة</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {data.recentOrders.slice(0, 6).map((order) => (
                    <Table.Tr key={`${order.id}-${order.date}`}>
                      <Table.Td>
                        <Anchor href={order.href} fw={800} size="sm">{order.id}</Anchor>
                        <Text size="xs" c="dimmed" mt={4}>{order.product}</Text>
                      </Table.Td>
                      <Table.Td><Text size="sm" fw={700}>{order.customer || '-'}</Text></Table.Td>
                      <Table.Td><Text size="xs" c="dimmed">{order.date || '-'}</Text></Table.Td>
                      <Table.Td><StatusPill>{order.status || 'قيد المتابعة'}</StatusPill></Table.Td>
                      <Table.Td ta="left"><Text size="sm" fw={800} c="indigo.7">{order.price || '-'}</Text></Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </Table.ScrollContainer>
          </Surface>
        </Grid.Col>

        <Grid.Col span={{ base: 12, lg: 4 }}>
          <Surface title="تنبيهات المخزون" eyebrow="Inventory" action={<LinkAction href="product.php">إدارة</LinkAction>}>
            <div className="saas-alert-list">
              {data.lowStock.length ? data.lowStock.slice(0, 6).map((item) => (
                <a className="saas-alert-row" href={item.href} key={`${item.name}-${item.qty}`}>
                  <ThemeIcon color={item.danger ? 'red' : 'orange'} variant="light" radius="md">
                    <IconAlertTriangle size={17} stroke={1.8} />
                  </ThemeIcon>
                  <span>
                    <strong>{item.name}</strong>
                    <small>{item.price}</small>
                  </span>
                  <Badge color={item.danger ? 'red' : 'orange'} variant="light">{item.qty}</Badge>
                </a>
              )) : (
                <Text c="dimmed" size="sm" ta="center" py="xl">لا توجد تنبيهات مخزون حالياً.</Text>
              )}
            </div>
          </Surface>
        </Grid.Col>
      </Grid>
    </main>
  )
}

function ChartTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null
  const row = payload[0]?.payload || {}
  return (
    <div className="saas-chart-tooltip">
      <Text size="xs" fw={800}>{decodeText(label || row.name)}</Text>
      {payload.map((item) => (
        <Group justify="space-between" gap="lg" key={item.dataKey}>
          <Text size="xs" c="dimmed">{item.dataKey === 'orders' ? 'الطلبات' : 'الإيراد'}</Text>
          <Text size="xs" fw={800}>{item.value}</Text>
        </Group>
      ))}
    </div>
  )
}

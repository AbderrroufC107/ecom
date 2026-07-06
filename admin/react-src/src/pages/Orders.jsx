import React from 'react'
import {
  Badge,
  Button,
  Card,
  Drawer,
  Grid,
  Group,
  Modal,
  Select,
  Tabs,
  Text,
  TextInput,
  Tooltip,
} from '@mantine/core'
import { DataTable } from 'mantine-datatable'
import 'mantine-datatable/styles.css'
import {
  IconAlertTriangle,
  IconClipboardList,
  IconEye,
  IconFilter,
  IconPhone,
  IconSearch,
  IconTrash,
  IconTruck,
  IconUserCheck,
} from '@tabler/icons-react'

import { EmptyState, IconOnlyAction, MetricCard, PageHeader, StatusPill, ToolbarButton } from '../components/Enterprise.jsx'
import { cleanText, decodeText, parseNumeric } from '../lib/text.js'
import { getLanguage, pageTranslations, legacyOrdersMappers } from '../lib/pageMeta.js'

const PAGE_SIZE = 12

function scrapeOrdersData() {
  const tabElements = document.querySelectorAll('#orderStatusTabs a[data-toggle="tab"]')
  if (!tabElements.length) return null

  const tabs = Array.from(tabElements).map((link) => {
    const id = link.getAttribute('href')?.replace('#', '') || ''
    return {
      id,
      title: decodeText(link.querySelector('span:not(.count)')?.textContent || link.textContent),
      count: parseNumeric(link.querySelector('.count')?.textContent || 0),
    }
  })

  const bulkOptionsByTab = {}
  Array.from(document.querySelectorAll('.orders-status-tab-content .tab-pane')).forEach((pane) => {
    const select = pane.querySelector('form.bulk-form select.bulk-action-select')
    bulkOptionsByTab[pane.id] = select
      ? Array.from(select.options)
          .filter((option) => option.value)
          .map((option) => ({ value: option.value, label: decodeText(option.textContent) }))
      : []
  })

  const orders = []
  Array.from(document.querySelectorAll('.orders-status-tab-content .tab-pane')).forEach((pane) => {
    Array.from(pane.querySelectorAll('tbody tr')).forEach((row, index) => {
      if (row.querySelector('.empty, td[colspan]')) return

      const checkbox = row.querySelector('.js-order-checkbox, input[type="checkbox"][name="order_ids[]"], input[type="checkbox"][name="ids[]"]')
      if (!checkbox) return

      const data = checkbox.dataset || {}
      const cells = row.querySelectorAll('td')
      const orderId = checkbox.value || cleanText(cells[0]?.textContent || `row-${index}`)
      const detailLink = row.querySelector('.row-actions a[href*="order-details.php"], a[href*="order-details.php"]')
      const deleteLink = row.querySelector('.row-actions a[href*="order-delete.php"], a[href*="order-delete.php"]')
      const primaryAction = row.querySelector('.row-actions button, .row-actions a.btn:not([href*="order-delete.php"]):not([href*="order-details.php"])')

      orders.push({
        id: orderId,
        tabId: pane.id,
        product: decodeText(row.querySelector('.order-main strong')?.textContent || cells[1]?.textContent || '\u0637\u0644\u0628'),
        customerName: decodeText(cells[2]?.querySelector('strong')?.textContent || cells[2]?.textContent || ''),
        customerPhone: cleanText(cells[2]?.querySelector('a[href^="tel:"]')?.textContent || cells[2]?.querySelector('a')?.textContent || ''),
        assignedEmployee: decodeText(cells[3]?.textContent || '\u063a\u064a\u0631 \u0645\u0648\u0632\u0639'),
        followup: decodeText(cells[4]?.querySelector('.pill')?.textContent || cells[4]?.textContent || ''),
        followupHint: decodeText(cells[4]?.querySelector('.callbox')?.textContent || ''),
        delivery: decodeText(cells[5]?.querySelector('.pill')?.textContent || cells[5]?.textContent || ''),
        status: decodeText(cells[6]?.querySelector('.pill, .label, .badge')?.textContent || cells[6]?.textContent || ''),
        price: decodeText(data.orderTotal || cells[7]?.textContent || ''),
        priceNumber: parseNumeric(data.orderTotal || cells[7]?.textContent || ''),
        date: decodeText(data.orderDate || row.querySelector('.order-meta')?.textContent || ''),
        qty: decodeText(data.orderQuantity || ''),
        detailHref: detailLink?.getAttribute('href') || `order-details.php?id=${orderId}`,
        deleteHref: deleteLink?.getAttribute('href') || '',
        checkboxEl: checkbox,
        primaryActionEl: primaryAction,
      })
    })
  })

  const activeLink = document.querySelector('#orderStatusTabs li.active a[data-toggle="tab"]')
  const activeTabId = activeLink?.getAttribute('href')?.replace('#', '') || tabs[0]?.id || ''
  const root = document.getElementById('orders-react-root')

  return {
    tabs,
    orders,
    bulkOptionsByTab,
    activeTabId,
    summary: {
      total: root?.dataset.total || orders.length,
      today: root?.dataset.today || 0,
      pending: root?.dataset.pending || 0,
      pendingNoCalls: root?.dataset.pendingNoCalls || 0,
      pendingReady: root?.dataset.pendingReady || 0,
      pendingFollowup: root?.dataset.pendingFollowup || 0,
      completedAmount: root?.dataset.completedAmount || '',
    },
  }
}

function sortRecords(records, sortStatus) {
  const sorted = [...records]
  const { columnAccessor, direction } = sortStatus
  sorted.sort((a, b) => {
    const aValue = a[columnAccessor]
    const bValue = b[columnAccessor]
    const result = typeof aValue === 'number' || typeof bValue === 'number'
      ? Number(aValue || 0) - Number(bValue || 0)
      : String(aValue || '').localeCompare(String(bValue || ''), 'ar')
    return direction === 'asc' ? result : -result
  })
  return sorted
}

export default function Orders() {
  const lang = getLanguage()
  const trans = pageTranslations[lang] || pageTranslations['ar']

  const scrapedData = React.useMemo(() => scrapeOrdersData(), [])
  const data = scrapedData || {
    tabs: [],
    orders: [],
    bulkOptionsByTab: {},
    activeTabId: '',
    summary: {
      total: 0,
      today: 0,
      pending: 0,
      pendingNoCalls: 0,
      pendingReady: 0,
      pendingFollowup: 0,
      completedAmount: '',
    },
  }
  const [activeTab, setActiveTab] = React.useState(scrapedData?.activeTabId || '')
  const [searchQuery, setSearchQuery] = React.useState('')
  const [filterEmployee, setFilterEmployee] = React.useState('all')
  const [filterDelivery, setFilterDelivery] = React.useState('all')
  const [selectedRecords, setSelectedRecords] = React.useState([])
  const [page, setPage] = React.useState(1)
  const [sortStatus, setSortStatus] = React.useState({ columnAccessor: 'date', direction: 'desc' })
  const [drawer, setDrawer] = React.useState({ opened: false, title: '', url: '' })
  const [deleteTarget, setDeleteTarget] = React.useState(null)

  React.useEffect(() => {
    document.querySelectorAll('.orders-page > .hero, .orders-page > .pending-cues, .orders-tabs-custom, .content-header').forEach((node) => {
      node.style.display = 'none'
    })
  }, [])

  const employeeOptions = React.useMemo(() => {
    const options = [
      { value: 'all', label: trans.allEmployees },
      { value: 'unassigned', label: trans.unassigned },
    ]
    const seen = new Set()
    data.orders.forEach((order) => {
      if (!order.assignedEmployee || legacyOrdersMappers.unassigned.test(order.assignedEmployee)) return
      if (!seen.has(order.assignedEmployee)) {
        seen.add(order.assignedEmployee)
        options.push({ value: order.assignedEmployee, label: order.assignedEmployee })
      }
    })
    return options
  }, [data.orders, trans])

  const deliveryOptions = React.useMemo(() => {
    const options = [{ value: 'all', label: trans.allDeliveries }]
    const seen = new Set()
    data.orders.forEach((order) => {
      if (!order.delivery || seen.has(order.delivery)) return
      seen.add(order.delivery)
      options.push({ value: order.delivery, label: order.delivery })
    })
    return options
  }, [data.orders, trans])

  const filteredOrders = React.useMemo(() => {
    return data.orders.filter((order) => {
      if (order.tabId !== activeTab) return false
      if (filterEmployee === 'unassigned' && !legacyOrdersMappers.unassigned.test(order.assignedEmployee)) return false
      if (filterEmployee !== 'all' && filterEmployee !== 'unassigned' && order.assignedEmployee !== filterEmployee) return false
      if (filterDelivery !== 'all' && order.delivery !== filterDelivery) return false

      if (searchQuery) {
        const query = searchQuery.toLowerCase()
        const haystack = `${order.id} ${order.product} ${order.customerName} ${order.customerPhone} ${order.assignedEmployee} ${order.delivery} ${order.status}`.toLowerCase()
        if (!haystack.includes(query)) return false
      }

      return true
    })
  }, [activeTab, data.orders, filterDelivery, filterEmployee, searchQuery])

  const sortedOrders = React.useMemo(() => sortRecords(filteredOrders, sortStatus), [filteredOrders, sortStatus])
  const paginatedOrders = React.useMemo(() => {
    const start = (page - 1) * PAGE_SIZE
    return sortedOrders.slice(start, start + PAGE_SIZE)
  }, [page, sortedOrders])
  const tableHeight = Math.min(620, Math.max(420, paginatedOrders.length * 78 + 150))

  const activeBulkOptions = data.bulkOptionsByTab[activeTab] || []

  const handleTabChange = (value) => {
    setActiveTab(value)
    setPage(1)
    setSelectedRecords([])
    const link = document.querySelector(`#orderStatusTabs a[href="#${value}"]`)
    if (link && window.jQuery) window.jQuery(link).tab('show')
    else link?.click()
  }

  const handleSelectionChange = (records) => {
    setSelectedRecords(records)
    data.orders.forEach((order) => {
      order.checkboxEl.checked = records.some((record) => record.id === order.id)
      order.checkboxEl.closest('tr')?.classList.toggle('is-selected-row', order.checkboxEl.checked)
    })
  }

  const handleBulkAction = (actionValue) => {
    if (!selectedRecords.length) return
    const activeForm = document.querySelector(`#${activeTab} form.bulk-form`)
    const select = activeForm?.querySelector('select.bulk-action-select')
    if (select) select.value = actionValue
    const submit = activeForm?.querySelector('button[type="submit"], input[type="submit"]')
    if (submit) submit.click()
    else activeForm?.submit()
  }

  const openDetails = (order) => {
    const separator = order.detailHref.includes('?') ? '&' : '?'
    setDrawer({
      opened: true,
      title: `${trans.orderDetailTitle} ${order.id}`,
      url: `${order.detailHref}${separator}react_card=1`,
    })
  }

  const resetFilters = () => {
    setSearchQuery('')
    setFilterEmployee('all')
    setFilterDelivery('all')
    setPage(1)
  }

  if (!scrapedData) return null

  return (
    <main className="saas-page" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      <PageHeader
        eyebrow={trans.salesEyebrow}
        title={trans.ordersManagement}
        description={trans.ordersDesc}
        actions={(
          <>
            <ToolbarButton href="order-statistics.php" icon={IconClipboardList}>{trans.statistics}</ToolbarButton>
            <ToolbarButton href="incomplete-orders.php" icon={IconAlertTriangle}>{trans.incomplete}</ToolbarButton>
          </>
        )}
      />

      <SimpleSummary data={data.summary} trans={trans} />

      <Card className="saas-surface saas-orders-shell" withBorder>
        <Tabs value={activeTab} onChange={handleTabChange} className="saas-tabs">
          <Tabs.List>
            {data.tabs.map((tab) => (
              <Tabs.Tab value={tab.id} key={tab.id} rightSection={<Badge size="xs" variant="light">{tab.count}</Badge>}>
                {tab.title}
              </Tabs.Tab>
            ))}
          </Tabs.List>
        </Tabs>

        <Grid gutter="sm" align="center" mt="md">
          <Grid.Col span={{ base: 12, lg: 5 }}>
            <TextInput
              leftSection={<IconSearch size={16} stroke={1.8} />}
              placeholder={trans.searchOrdersPlaceholder}
              value={searchQuery}
              onChange={(event) => {
                setSearchQuery(event.currentTarget.value)
                setPage(1)
              }}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
            <Select
              data={employeeOptions}
              value={filterEmployee}
              onChange={(value) => {
                setFilterEmployee(value || 'all')
                setPage(1)
              }}
              leftSection={<IconUserCheck size={16} stroke={1.8} />}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
            <Select
              data={deliveryOptions}
              value={filterDelivery}
              onChange={(value) => {
                setFilterDelivery(value || 'all')
                setPage(1)
              }}
              leftSection={<IconTruck size={16} stroke={1.8} />}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, lg: 1 }}>
            <Button variant="default" radius="md" fullWidth leftSection={<IconFilter size={15} />} onClick={resetFilters}>
              {trans.filter}
            </Button>
          </Grid.Col>
        </Grid>

        {selectedRecords.length ? (
          <Group className="saas-bulkbar" justify="space-between" mt="md">
            <Text size="sm" fw={800}>{selectedRecords.length} {trans.selectedOrdersCount}</Text>
            <Group gap="xs">
              {activeBulkOptions.map((option) => (
                <Button size="xs" radius="md" variant="light" key={option.value} onClick={() => handleBulkAction(option.value)}>
                  {option.label}
                </Button>
              ))}
            </Group>
          </Group>
        ) : null}

        <DataTable
          className="saas-data-table"
          minHeight={360}
          height={tableHeight}
          withTableBorder={false}
          withColumnBorders={false}
          highlightOnHover
          striped
          records={paginatedOrders}
          columns={[
            {
              accessor: 'id',
              title: trans.orderCol,
              sortable: true,
              width: 132,
              render: (order) => (
                <div>
                  <Text fw={850} size="sm">{order.id}</Text>
                  <Text c="dimmed" size="xs" mt={3}>{order.date || '-'}</Text>
                </div>
              ),
            },
            {
              accessor: 'product',
              title: trans.productCol,
              sortable: true,
              render: (order) => (
                <div>
                  <Text fw={800} size="sm" lineClamp={2}>{order.product}</Text>
                  {order.qty ? <Text c="dimmed" size="xs" mt={3}>{trans.quantityLabel}: {order.qty}</Text> : null}
                </div>
              ),
            },
            {
              accessor: 'customerName',
              title: trans.customerCol,
              sortable: true,
              width: 190,
              render: (order) => (
                <div>
                  <Text fw={750} size="sm">{order.customerName || '-'}</Text>
                  {order.customerPhone ? (
                    <Group gap={4} mt={3} wrap="nowrap">
                      <IconPhone size={12} stroke={1.8} />
                      <Text component="a" href={`tel:${order.customerPhone}`} size="xs" c="dimmed">
                        {order.customerPhone}
                      </Text>
                    </Group>
                  ) : null}
                </div>
              ),
            },
            {
              accessor: 'assignedEmployee',
              title: trans.employeeCol,
              sortable: true,
              width: 150,
              render: (order) => (
                <StatusPill tone={(legacyOrdersMappers.unassigned.test(order.assignedEmployee) || /unassigned/i.test(order.assignedEmployee)) ? 'neutral' : 'primary'}>
                  {legacyOrdersMappers.unassigned.test(order.assignedEmployee) ? trans.unassigned : order.assignedEmployee}
                </StatusPill>
              ),
            },
            {
              accessor: 'followup',
              title: trans.followupCol,
              sortable: true,
              width: 145,
              render: (order) => (
                <Tooltip label={order.followupHint || order.followup} withArrow disabled={!order.followupHint}>
                  <span><StatusPill>{order.followup || trans.notSet}</StatusPill></span>
                </Tooltip>
              ),
            },
            {
              accessor: 'delivery',
              title: trans.deliveryCol,
              sortable: true,
              width: 140,
              render: (order) => <StatusPill tone="success">{order.delivery || '-'}</StatusPill>,
            },
            {
              accessor: 'status',
              title: trans.statusCol,
              sortable: true,
              width: 140,
              render: (order) => <StatusPill>{order.status || '-'}</StatusPill>,
            },
            {
              accessor: 'priceNumber',
              title: trans.priceCol,
              sortable: true,
              textAlign: 'left',
              width: 130,
              render: (order) => <Text fw={850} size="sm" c="indigo.7">{order.price || '-'}</Text>,
            },
            {
              accessor: 'actions',
              title: '',
              textAlign: 'left',
              width: 132,
              render: (order) => (
                <Group gap={4} justify="flex-end" wrap="nowrap">
                  {order.primaryActionEl ? (
                    <IconOnlyAction label={trans.actionPrimary} icon={IconClipboardList} color="teal" onClick={() => order.primaryActionEl.click()} />
                  ) : null}
                  <IconOnlyAction label={trans.actionDetails} icon={IconEye} color="indigo" onClick={() => openDetails(order)} />
                  {order.deleteHref ? (
                    <IconOnlyAction label={trans.actionDelete} icon={IconTrash} color="red" onClick={() => setDeleteTarget(order)} />
                  ) : null}
                </Group>
              ),
            },
          ]}
          totalRecords={sortedOrders.length}
          recordsPerPage={PAGE_SIZE}
          page={page}
          onPageChange={setPage}
          sortStatus={sortStatus}
          onSortStatusChange={(status) => {
            setSortStatus(status)
            setPage(1)
          }}
          selectedRecords={selectedRecords}
          onSelectedRecordsChange={handleSelectionChange}
          emptyState={<EmptyState title={trans.noOrders} description={trans.noOrdersDesc} />}
          paginationText={({ from, to, totalRecords }) => `${from}-${to} ${trans.paginationText} ${totalRecords}`}
        />
      </Card>

      <Drawer
        opened={drawer.opened}
        onClose={() => setDrawer({ opened: false, title: '', url: '' })}
        title={drawer.title}
        position="left"
        size="lg"
        className="saas-drawer"
      >
        {drawer.opened ? <iframe src={drawer.url} title={drawer.title} className="saas-detail-frame" /> : null}
      </Drawer>

      <Modal opened={Boolean(deleteTarget)} onClose={() => setDeleteTarget(null)} title={trans.confirmDeleteTitle} centered>
        <Text size="sm" c="dimmed" mb="lg">
          {trans.confirmDeleteDesc.replace('{id}', deleteTarget?.id || '')}
        </Text>
        <Group justify="flex-end">
          <Button variant="default" radius="md" onClick={() => setDeleteTarget(null)}>{trans.cancel}</Button>
          <Button color="red" radius="md" onClick={() => { window.location.href = deleteTarget.deleteHref }}>{trans.deleteOrder}</Button>
        </Group>
      </Modal>
    </main>
  )
}

function SimpleSummary({ data, trans }) {
  return (
    <Grid gutter="md" mb="md">
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <MetricCard label={trans.summaryTotal} value={data.total} description={trans.summaryTotalDesc} icon={IconClipboardList} tone="primary" />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <MetricCard label={trans.summaryToday} value={data.today} description={trans.summaryTodayDesc} icon={IconClipboardList} tone="success" />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <MetricCard label={trans.summaryPending} value={data.pending} description={trans.summaryPendingDesc.replace('{count}', data.pendingNoCalls)} icon={IconAlertTriangle} tone="warning" />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <MetricCard label={trans.summaryCompleted} value={data.completedAmount || '-'} description={trans.summaryCompletedDesc.replace('{count}', data.pendingReady)} icon={IconClipboardList} tone="success" />
      </Grid.Col>
    </Grid>
  )
}


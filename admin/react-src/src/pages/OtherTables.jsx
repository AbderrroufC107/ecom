import React from 'react'
import { Badge, Button, Card, Group, Select, Tabs, Text, TextInput } from '@mantine/core'
import { DataTable } from 'mantine-datatable'
import 'mantine-datatable/styles.css'
import { IconDownload, IconFilterOff, IconPlus, IconSearch, IconTable } from '@tabler/icons-react'

import { AddButton, EmptyState, PageHeader } from '../components/Enterprise.jsx'
import { decodeText, parseNumeric } from '../lib/text.js'
import { getPageTitle } from '../lib/pageMeta.js'

const PAGE_SIZE = 14

function tableTitle(table, index) {
  const box = table.closest('.box, .dr-card, .sd-card, .card, section')
  const title = box?.querySelector('.box-title, h3, h4, .sd-section-head h3, .dr-section-head h3')?.textContent
  return decodeText(title || `جدول ${index + 1}`)
}

function isDataTable(table) {
  const headers = table.querySelectorAll('thead th')
  const nested = Boolean(table.parentElement?.closest('table'))
  return headers.length > 0 && !nested
}

function scrapeTable(table, tableIndex) {
  const rawHeaders = Array.from(table.querySelectorAll('thead th')).map((th, index) => ({
    index,
    text: decodeText(th.textContent || '#') || '#',
    width: th.getAttribute('width') || th.style.width || undefined,
  }))

  const rows = Array.from(table.querySelectorAll('tbody tr')).map((row, rowIndex) => {
    if (row.querySelector('.empty') || row.querySelector('td[colspan]')) return null

    const checkbox = row.querySelector('input[type="checkbox"][name], input[type="checkbox"].incomplete_cb, input[type="checkbox"].js-order-checkbox')
    const cells = Array.from(row.querySelectorAll('td')).map((td, cellIndex) => ({
      index: cellIndex,
      html: td.innerHTML,
      text: decodeText(td.textContent || ''),
      isAction: Boolean(td.querySelector('a.btn, button.btn, .btn, [data-toggle="modal"]')),
      isCheckbox: Boolean(td.querySelector('input[type="checkbox"]')),
    }))

    if (!cells.length) return null

    return {
      id: checkbox?.value || `table-${tableIndex}-row-${rowIndex}`,
      cells,
      checkboxEl: checkbox,
      legacyRow: row,
    }
  }).filter(Boolean)

  const headers = rawHeaders.filter((header) => {
    const allCheckbox = rows.length > 0 && rows.every((row) => row.cells.find((cell) => cell.index === header.index)?.isCheckbox)
    return !allCheckbox
  })

  const form = table.closest('form')
  const bulkActions = form
    ? Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'))
        .filter((button) => !button.closest('table'))
        .map((button, index) => ({
          id: `bulk-${tableIndex}-${index}`,
          label: decodeText(button.textContent || button.value || 'تنفيذ'),
          button,
        }))
    : []

  return {
    id: `table-${tableIndex}`,
    title: tableTitle(table, tableIndex),
    table,
    headers,
    rows,
    bulkActions,
    selectable: rows.some((row) => row.checkboxEl),
  }
}

function scrapeTables(anchorTable) {
  const tables = Array.from(document.querySelectorAll('.content-wrapper table'))
    .filter(isDataTable)
    .filter((table) => !table.closest('.orders-status-tab-content'))

  const selectedTables = tables.length ? tables : anchorTable ? [anchorTable] : []
  return selectedTables.map(scrapeTable).filter((table) => table.headers.length)
}

function getHeaderActions() {
  return Array.from(document.querySelectorAll('.content-header-right a.btn, .content-header a.btn, .box-header a.btn'))
    .filter((link) => link.getAttribute('href'))
    .slice(0, 3)
    .map((link, index) => ({
      id: `header-action-${index}`,
      href: link.getAttribute('href'),
      label: decodeText(link.textContent || 'إضافة'),
    }))
}

function getPageDescription(pageName) {
  if (/product/.test(pageName)) return 'إدارة المنتجات، المخزون، الأسعار، وحالة النشر من جدول موحد وسريع.'
  if (/customer/.test(pageName)) return 'عرض العملاء والرسائل والحسابات مع بحث سريع وإجراءات مباشرة.'
  if (/delivery|shipping/.test(pageName)) return 'إدارة التوصيل، الشركات، والأسعار حسب المناطق.'
  if (/disaster|backup|system|audit|queue/.test(pageName)) return 'مراقبة تشغيلية مهيكلة للأنظمة، السجلات، والطوابير.'
  if (/billing|commission/.test(pageName)) return 'إدارة الفوترة، الاشتراكات، والعمولات.'
  return 'جدول إداري حديث مع بحث، فرز، تحديد جماعي، وإجراءات سريعة.'
}

function sortRows(rows, sortStatus) {
  const { columnAccessor, direction } = sortStatus
  const columnIndex = Number(String(columnAccessor).replace('col_', ''))
  const sorted = [...rows].sort((a, b) => {
    const aText = a.cells.find((cell) => cell.index === columnIndex)?.text || ''
    const bText = b.cells.find((cell) => cell.index === columnIndex)?.text || ''
    const aNumber = parseNumeric(aText)
    const bNumber = parseNumeric(bText)
    const bothNumeric = aNumber !== 0 || bNumber !== 0
    const result = bothNumeric ? aNumber - bNumber : aText.localeCompare(bText, 'ar')
    return direction === 'asc' ? result : -result
  })
  return sorted
}

function columnWidth(header, rows, headerPosition, isActionColumn) {
  if (header.width) return header.width
  const samples = rows.slice(0, 20).map((row) => row.cells.find((cell) => cell.index === header.index)?.text || '')
  const longest = Math.max(header.text.length, ...samples.map((value) => value.length))

  if (isActionColumn) return 170
  if (headerPosition === 0 || /^#|id$/i.test(header.text)) return 96
  if (/ip|هاتف|phone/i.test(header.text)) return 140
  if (/date|time|تاريخ|وقت/i.test(header.text)) return 170
  if (/data|value|json|message|بيانات|قيمة|رسالة/i.test(header.text) || longest > 70) return 420
  if (longest > 35) return 260
  return 190
}

export default function OtherTables({ legacyTable, pageName }) {
  const [tables] = React.useState(() => scrapeTables(legacyTable))
  const [activeTableId, setActiveTableId] = React.useState(tables[0]?.id || '')
  const [searchQuery, setSearchQuery] = React.useState('')
  const [page, setPage] = React.useState(1)
  const [selectedRowsByTable, setSelectedRowsByTable] = React.useState({})
  const [sortStatus, setSortStatus] = React.useState({ columnAccessor: 'col_0', direction: 'asc' })

  React.useEffect(() => {
    tables.forEach((table) => {
      const reactRoot = document.getElementById('other-tables-react-root')
      const shell = table.table.closest('.box, .card, .panel')
      if (shell && !shell.contains(reactRoot)) {
        shell.style.display = 'none'
      }
      const wrapper = table.table.closest('.dataTables_wrapper')
      if (wrapper && !wrapper.contains(reactRoot)) {
        wrapper.style.display = 'none'
      }
      const tableResponsive = table.table.closest('.table-responsive')
      if (tableResponsive && !tableResponsive.contains(reactRoot)) {
        tableResponsive.style.display = 'none'
      }
      table.table.style.display = 'none'
    })
    const legacyHeader = document.querySelector('.content-header')
    if (legacyHeader) legacyHeader.style.display = 'none'
  }, [tables])

  const emptyTable = React.useMemo(() => ({
    id: 'empty',
    title: 'جدول البيانات',
    headers: [],
    rows: [],
    bulkActions: [],
    selectable: false,
  }), [])
  const activeTable = tables.find((table) => table.id === activeTableId) || tables[0] || emptyTable
  const selectedRows = selectedRowsByTable[activeTable.id] || []
  const headerActions = getHeaderActions()

  const filteredRows = React.useMemo(() => {
    return activeTable.rows.filter((row) => {
      if (!searchQuery) return true
      const query = searchQuery.toLowerCase()
      return row.cells.some((cell) => cell.text.toLowerCase().includes(query))
    })
  }, [activeTable, searchQuery])

  const sortedRows = React.useMemo(() => sortRows(filteredRows, sortStatus), [filteredRows, sortStatus])
  const paginatedRows = React.useMemo(() => {
    const start = (page - 1) * PAGE_SIZE
    return sortedRows.slice(start, start + PAGE_SIZE)
  }, [page, sortedRows])
  const tableHeight = Math.min(720, Math.max(420, paginatedRows.length * 70 + 150))

  const columns = activeTable.headers.map((header, headerPosition) => {
    const isActionColumn = /action|إجراء|حذف|edit|delete/i.test(header.text) ||
      activeTable.rows.some((row) => row.cells.find((cell) => cell.index === header.index)?.isAction)

    return {
      accessor: `col_${header.index}`,
      title: header.text,
      sortable: !isActionColumn,
      width: columnWidth(header, activeTable.rows, headerPosition, isActionColumn),
      textAlign: isActionColumn ? 'left' : 'right',
      noWrap: false,
      render: (row) => {
        const cell = row.cells.find((item) => item.index === header.index)
        if (!cell) return null
        return <div className={`saas-legacy-cell ${cell.text.length > 80 ? 'is-long' : ''}`} dangerouslySetInnerHTML={{ __html: cell.html }} />
      },
    }
  })

  const handleSelectionChange = (records) => {
    setSelectedRowsByTable((prev) => ({ ...prev, [activeTable.id]: records }))
    activeTable.rows.forEach((row) => {
      if (!row.checkboxEl) return
      row.checkboxEl.checked = records.some((record) => record.id === row.id)
      row.legacyRow.classList.toggle('is-selected-row', row.checkboxEl.checked)
    })
  }

  const exportCsv = () => {
    const csvRows = [activeTable.headers.map((header) => `"${header.text.replace(/"/g, '""')}"`)]
    filteredRows.forEach((row) => {
      csvRows.push(activeTable.headers.map((header) => {
        const text = row.cells.find((cell) => cell.index === header.index)?.text || ''
        return `"${text.replace(/"/g, '""')}"`
      }))
    })
    const blob = new Blob([csvRows.map((row) => row.join(',')).join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${pageName || 'admin-table'}-${activeTable.id}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  if (!tables.length) return null

  return (
    <main className="saas-page" dir="rtl">
      <PageHeader
        eyebrow="البيانات"
        title={getPageTitle(pageName, activeTable.title)}
        description={getPageDescription(pageName)}
        metrics={[
          { label: 'الجداول', value: tables.length },
          { label: 'السجلات', value: activeTable.rows.length },
          { label: 'بعد الفلترة', value: filteredRows.length },
        ]}
        actions={(
          <>
            {headerActions.map((action) => (
              <AddButton href={action.href} key={action.id} icon={IconPlus}>{action.label}</AddButton>
            ))}
            <Button radius="md" size="sm" variant="default" leftSection={<IconDownload size={16} />} onClick={exportCsv}>
              CSV
            </Button>
          </>
        )}
      />

      <Card className="saas-surface saas-table-shell" withBorder>
        {tables.length > 1 ? (
          <Tabs
            value={activeTable.id}
            onChange={(value) => {
              setActiveTableId(value)
              setPage(1)
              setSearchQuery('')
            }}
            className="saas-tabs"
            mb="md"
          >
            <Tabs.List>
              {tables.map((table) => (
                <Tabs.Tab key={table.id} value={table.id} leftSection={<IconTable size={14} />} rightSection={<Badge size="xs" variant="light">{table.rows.length}</Badge>}>
                  {table.title}
                </Tabs.Tab>
              ))}
            </Tabs.List>
          </Tabs>
        ) : null}

        <Group justify="space-between" align="center" mb="md" gap="sm">
          <TextInput
            className="saas-table-search"
            leftSection={<IconSearch size={16} />}
            placeholder="بحث سريع في كل الأعمدة..."
            value={searchQuery}
            onChange={(event) => {
              setSearchQuery(event.currentTarget.value)
              setPage(1)
            }}
          />
          <Group gap="xs">
            <Select
              className="saas-density-select"
              value="comfortable"
              data={[{ value: 'comfortable', label: 'كثافة مريحة' }]}
              readOnly
            />
            <Button variant="default" radius="md" leftSection={<IconFilterOff size={15} />} onClick={() => setSearchQuery('')}>
              إعادة ضبط
            </Button>
          </Group>
        </Group>

        {selectedRows.length ? (
          <Group className="saas-bulkbar" justify="space-between" mb="md">
            <Text size="sm" fw={800}>{selectedRows.length} سجل محدد</Text>
            <Group gap="xs">
              {activeTable.bulkActions.length ? activeTable.bulkActions.map((action) => (
                <Button size="xs" radius="md" variant="light" key={action.id} onClick={() => action.button.click()}>
                  {action.label}
                </Button>
              )) : (
                <Badge variant="light" color="gray">لا توجد إجراءات جماعية</Badge>
              )}
            </Group>
          </Group>
        ) : null}

        <DataTable
          className="saas-data-table"
          minHeight={320}
          height={tableHeight}
          withTableBorder={false}
          withColumnBorders={false}
          striped
          highlightOnHover
          records={paginatedRows}
          columns={columns}
          totalRecords={sortedRows.length}
          recordsPerPage={PAGE_SIZE}
          page={page}
          onPageChange={setPage}
          sortStatus={sortStatus}
          onSortStatusChange={(status) => {
            setSortStatus(status)
            setPage(1)
          }}
          selectedRecords={activeTable.selectable ? selectedRows : undefined}
          onSelectedRecordsChange={activeTable.selectable ? handleSelectionChange : undefined}
          emptyState={<EmptyState title="لا توجد سجلات" description="لا توجد نتائج مطابقة للبحث الحالي." />}
          paginationText={({ from, to, totalRecords }) => `${from}-${to} من ${totalRecords}`}
        />
      </Card>
    </main>
  )
}

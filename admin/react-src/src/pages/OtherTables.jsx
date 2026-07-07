import React from 'react'
import { Badge, Button, Card, Group, Select, Tabs, Text, TextInput } from '@mantine/core'
import { DataTable } from 'mantine-datatable'
import 'mantine-datatable/styles.css'
import { IconDownload, IconFilterOff, IconPlus, IconPencil, IconSearch, IconTable, IconTrash } from '@tabler/icons-react'

import { AddButton, EmptyState, PageHeader, ToolbarButton } from '../components/Enterprise.jsx'
import { decodeText, parseNumeric } from '../lib/text.js'
import { getPageTitle, getLanguage, pageTranslations, legacyMappers } from '../lib/pageMeta.js'

const PAGE_SIZE = 14

function tableTitle(table, index) {
  const box = table.closest('.box, .dr-card, .sd-card, .card, section')
  const title = box?.querySelector('.box-title, h3, h4, .sd-section-head h3, .dr-section-head h3')?.textContent
  const lang = getLanguage()
  const fallback = lang === 'ar' ? `\u062c\u062f\u0648\u0644 ${index + 1}` : `Table ${index + 1}`
  return decodeText(title || fallback)
}

function isDataTable(table) {
  const headers = table.querySelectorAll(':scope > thead > th, :scope > thead > tr > th, :scope > tr > th, :scope > tbody > tr > th')
  const nested = Boolean(table.parentElement?.closest('table'))
  return headers.length > 0 && !nested
}

function scrapeTable(table, tableIndex) {
  const rawHeaders = Array.from(table.querySelectorAll(':scope > thead > th, :scope > thead > tr > th, :scope > tr > th, :scope > tbody > tr > th')).map((th, index) => ({
    index,
    text: decodeText(th.textContent || '#') || '#',
    width: th.getAttribute('width') || th.style.width || undefined,
  }))

  const rows = Array.from(table.querySelectorAll(':scope > tbody > tr, :scope > tr')).map((row, rowIndex) => {
    if (row.querySelector('.empty') || row.querySelector('td[colspan]')) return null

    const checkbox = row.querySelector('input[type="checkbox"][name], input[type="checkbox"].incomplete_cb, input[type="checkbox"].js-order-checkbox')
    const cells = Array.from(row.querySelectorAll(':scope > td')).map((td, cellIndex) => ({
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
          label: decodeText(button.textContent || button.value || (getLanguage() === 'ar' ? '\u062a\u0646\u0641\u064a\u0630' : 'Execute')),
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
  // Prefer PHP-defined page actions (most reliable)
  if (window.__pageActions && Array.isArray(window.__pageActions) && window.__pageActions.length > 0) {
    return window.__pageActions.map((action) => ({
      id: action.id || `action-${Math.random()}`,
      href: action.href || null,
      onClick: action.onClick ? () => {
        try { /* eslint-disable-next-line no-new-func */ new Function(action.onClick)() } catch(e) { console.warn('action error', e) }
      } : null,
      label: decodeText(action.label || (getLanguage() === 'ar' ? '\u0625\u0636\u0627\u0641\u0629' : 'Add')),
      variant: action.variant || 'default',
    }))
  }

  // Fallback: scrape from DOM
  const links = Array.from(document.querySelectorAll(
    '.content-header-right a.btn, .content-header a.btn, .box-header a.btn, .emp-actions a.btn'
  ))
  const buttons = Array.from(document.querySelectorAll(
    '.content-header-right button.btn, .content-header button.btn, .box-header button.btn, .emp-actions button.btn'
  )).filter((btn) => btn.getAttribute('onclick') || btn.dataset.action)

  const allActions = [...links, ...buttons]

  return allActions
    .filter((el) => {
      // Exclude elements inside search forms
      if (el.closest('form')) return false
      const href = el.getAttribute('href')
      const onclick = el.getAttribute('onclick')
      return href || onclick
    })
    .slice(0, 4)
    .map((el, index) => {
      const href = el.getAttribute('href') || ''
      const onclick = el.getAttribute('onclick') || ''
      const isJsHref = href.startsWith('javascript:')
      const jsCode = isJsHref ? href.slice('javascript:'.length) : onclick
      const isAction = isJsHref || !!onclick
      return {
        id: `header-action-${index}`,
        href: isAction ? null : href,
        onClick: isAction ? () => { try { /* eslint-disable-next-line no-new-func */ new Function(jsCode)() } catch(e) { console.warn('action error', e) } } : null,
        label: decodeText(el.textContent || (getLanguage() === 'ar' ? '\u0625\u0636\u0627\u0641\u0629' : 'Add')),
        variant: 'filled',
      }
    })
}

function getPageDescription(pageName, trans) {
  if (/product/.test(pageName)) return trans.descProduct
  if (/customer/.test(pageName)) return trans.descCustomer
  if (/delivery|shipping/.test(pageName)) return trans.descDelivery
  if (/disaster|backup|system|audit|queue/.test(pageName)) return trans.descSystem
  if (/billing|commission/.test(pageName)) return trans.descBilling
  return trans.descDefault
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

  if (isActionColumn) return 320
  if (headerPosition === 0 || /^#|id$/i.test(header.text)) return 96
  if (legacyMappers.colPhone.test(header.text)) return 140
  if (legacyMappers.colDateTime.test(header.text)) return 170
  if (legacyMappers.colDataValue.test(header.text) || longest > 70) return 420
  if (longest > 35) return 260
  return 190
}

function parseActionButtons(html) {
  const container = document.createElement('div')
  container.innerHTML = html
  const buttons = []
  container.querySelectorAll('a.btn, button.btn, a[data-toggle="modal"]').forEach((el) => {
    const href = el.getAttribute('href') || ''
    const onclick = el.getAttribute('onclick') || ''
    const dataTarget = el.getAttribute('data-target') || ''
    const dataHref = el.getAttribute('data-href') || ''
    const classes = el.className || ''
    const text = (el.textContent || '').trim()
    let color = 'gray'
    if (/btn-primary/.test(classes)) color = 'indigo'
    else if (/btn-danger/.test(classes)) color = 'red'
    else if (/btn-success/.test(classes)) color = 'teal'
    else if (/btn-warning/.test(classes)) color = 'orange'
    else if (/btn-info/.test(classes)) color = 'blue'

    const isModal = Boolean(dataTarget || /confirm-delete|modal/i.test(dataHref) || /modal/i.test(onclick))
    const isDelete = /btn-danger|delete|حذف|trash/i.test(classes + ' ' + text)
    const isEdit = /btn-primary|edit|تعديل|pencil/i.test(classes + ' ' + text)

    let icon = null
    if (isEdit) icon = <IconPencil size={14} />
    else if (isDelete) icon = <IconTrash size={14} />

    const finalHref = isModal && dataHref ? dataHref : (isModal ? null : href)

    buttons.push({
      href: finalHref,
      color,
      text,
      icon,
      isModal,
      isDelete,
      onclick,
      dataHref,
      dataTarget,
    })
  })
  return buttons
}

function ActionCell({ html, isAction }) {
  if (!isAction) {
    return <div className="saas-legacy-cell" dangerouslySetInnerHTML={{ __html: html }} />
  }
  const buttons = React.useMemo(() => parseActionButtons(html), [html])
  if (!buttons.length) {
    return <div className="saas-legacy-cell" dangerouslySetInnerHTML={{ __html: html }} />
  }
  return (
    <Group gap={6} wrap="nowrap">
      {buttons.map((btn, index) => {
        const handleClick = (e) => {
          e.preventDefault()
          if (btn.isDelete && btn.href) {
            if (window.confirm(getLanguage() === 'ar' ? 'هل أنت متأكد من الحذف؟' : 'Are you sure you want to delete this?')) {
              window.location.href = btn.href
            }
            return
          }
          if (btn.onclick) {
            // Legacy `onclick="return confirm('...')"` guards are meant to gate the link's
            // own navigation, not replace it — evaluate the confirm, then still navigate.
            const isConfirmGuard = /^\s*return\s+confirm\s*\(/i.test(btn.onclick)
            if (isConfirmGuard) {
              let proceed = true
              try { proceed = new Function(btn.onclick)() !== false } catch (err) { console.warn('action button error', err) }
              if (proceed && btn.href) window.location.href = btn.href
              return
            }
            try { /* eslint-disable-next-line no-new-func */ new Function(btn.onclick)() } catch (err) { console.warn('action button error', err) }
            return
          }
          if (btn.href) {
            window.location.href = btn.href
          }
        }
        return (
          <Button
            key={index}
            component="a"
            href={btn.href || undefined}
            size="compact-xs"
            variant="filled"
            color={btn.color}
            radius="md"
            leftSection={btn.icon}
            onClick={handleClick}
          >
            {btn.text}
          </Button>
        )
      })}
    </Group>
  )
}

export default function OtherTables({ legacyTable, pageName }) {
  const lang = getLanguage()
  const trans = pageTranslations[lang] || pageTranslations['ar']

  const [tables] = React.useState(() => {
    const res = scrapeTables(legacyTable)
    if (window.logReact) {
      window.logReact('OtherTables scrapeTables: found ' + res.length + ' tables.')
    }
    return res
  })
  const [headerActions] = React.useState(() => getHeaderActions())
  const [activeTableId, setActiveTableId] = React.useState(tables[0]?.id || '')

  if (window.logReact) {
    window.logReact('OtherTables rendering. pageName=' + pageName + ' tablesLength=' + tables.length)
  }
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
    if (legacyHeader) {
      legacyHeader.style.display = legacyHeader.classList.contains('emp-hero') ? '' : 'none'
    }
  }, [tables])

  const emptyTable = React.useMemo(() => ({
    id: 'empty',
    title: trans.noRecords,
    headers: [],
    rows: [],
    bulkActions: [],
    selectable: false,
  }), [trans])

  const activeTable = tables.find((table) => table.id === activeTableId) || tables[0] || emptyTable
  const selectedRows = selectedRowsByTable[activeTable.id] || []

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
    const isActionColumn = legacyMappers.colAction.test(header.text) ||
      activeTable.rows.some((row) => row.cells.find((cell) => cell.index === header.index)?.isAction)

    return {
      accessor: `col_${header.index}`,
      title: header.text,
      sortable: !isActionColumn,
      width: columnWidth(header, activeTable.rows, headerPosition, isActionColumn),
      textAlign: isActionColumn ? 'left' : 'right',
      noWrap: isActionColumn,
      render: (row) => {
        const cell = row.cells.find((item) => item.index === header.index)
        if (!cell) return null
        if (isActionColumn || cell.isAction) {
          return <ActionCell html={cell.html} isAction={true} />
        }
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
    <main className="saas-page" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      <PageHeader
        eyebrow={trans.dataEyebrow}
        title={getPageTitle(pageName, activeTable.title)}
        description={getPageDescription(pageName, trans)}
        metrics={[
          { label: trans.tablesCount, value: tables.length },
          { label: trans.recordsCount, value: activeTable.rows.length },
          { label: trans.afterFilter, value: filteredRows.length },
        ]}
        actions={(
          <>
            {headerActions.map((action) => (
              <ToolbarButton
                key={action.id}
                href={action.href || undefined}
                onClick={action.onClick || undefined}
                icon={action.variant === 'filled' ? IconPlus : IconPlus}
                variant={action.variant === 'filled' ? 'filled' : 'default'}
              >
                {action.label}
              </ToolbarButton>
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
            placeholder={trans.searchPlaceholderTable}
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
              data={[{ value: 'comfortable', label: trans.densityComfortable }]}
              readOnly
            />
            <Button variant="default" radius="md" leftSection={<IconFilterOff size={15} />} onClick={() => setSearchQuery('')}>
              {trans.resetFilter}
            </Button>
          </Group>
        </Group>

        {selectedRows.length ? (
          <Group className="saas-bulkbar" justify="space-between" mb="md">
            <Text size="sm" fw={800}>{selectedRows.length} {trans.selectedCount}</Text>
            <Group gap="xs">
              {activeTable.bulkActions.length ? activeTable.bulkActions.map((action) => (
                <Button size="xs" radius="md" variant="light" key={action.id} onClick={() => action.button.click()}>
                  {action.label}
                </Button>
              )) : (
                <Badge variant="light" color="gray">{trans.noBulkActions}</Badge>
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
          pinLastColumn
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
          emptyState={<EmptyState title={trans.noRecords} description={trans.noFilteredResults} />}
          paginationText={({ from, to, totalRecords }) => `${from}-${to} ${trans.paginationText} ${totalRecords}`}
        />
      </Card>
    </main>
  )
}

import React from 'react'
import {
  ActionIcon,
  Avatar,
  Badge,
  Burger,
  Group,
  Kbd,
  Menu,
  ScrollArea,
  Text,
  TextInput,
  Tooltip,
  UnstyledButton,
} from '@mantine/core'
import { useDisclosure, useHotkeys } from '@mantine/hooks'
import {
  IconBell,
  IconChevronDown,
  IconChevronLeft,
  IconCommand,
  IconExternalLink,
  IconLogout,
  IconMenu2,
  IconSearch,
  IconSettings,
  IconUserCircle,
} from '@tabler/icons-react'

import {
  getIconForFile,
  getPageTitle,
  getSectionForFile,
  quickActions,
  sectionIcons,
  sectionLabels,
} from '../lib/pageMeta.js'
import { cleanText, currentFile, decodeText, getInitials, normalizeHref } from '../lib/text.js'

function readMenu() {
  const legacySidebar = document.querySelector('.main-sidebar .sidebar-menu')
  if (!legacySidebar) return []
  const seenStandaloneRoutes = new Set()

  return Array.from(legacySidebar.children)
    .filter((item) => item.tagName?.toLowerCase() === 'li')
    .map((item, index) => {
      const link = item.querySelector(':scope > a')
      const href = link?.getAttribute('href') || '#'
      const file = normalizeHref(href)

      const children = Array.from(item.querySelectorAll(':scope > .treeview-menu > li > a'))
        .map((child, childIndex) => {
          const childHref = child.getAttribute('href') || '#'
          const childFile = normalizeHref(childHref)
          const section = getSectionForFile(childFile)
          return {
            id: `nav-${index}-${childIndex}`,
            href: childHref,
            file: childFile,
            title: decodeText(getPageTitle(childFile, cleanText(child.textContent))),
            section,
            active: childFile === currentFile(),
          }
        })

      const section = getSectionForFile(file)
      const titleNode = link?.querySelector('span:not(.pull-right-container)')
      const fallbackTitle = titleNode ? titleNode.textContent : link?.textContent
      const isStandaloneRoute = href !== '#' && !children.length
      if (isStandaloneRoute && seenStandaloneRoutes.has(file)) return null
      if (isStandaloneRoute) seenStandaloneRoutes.add(file)
      if (file === 'settings.php' && isStandaloneRoute) return null

      return {
        id: `nav-${index}`,
        href,
        file,
        title: decodeText(getPageTitle(file, cleanText(fallbackTitle))),
        section,
        active: item.classList.contains('active') || file === currentFile(),
        children,
      }
    })
    .filter(Boolean)
}

function groupMenu(menu) {
  const buckets = {}
  menu.forEach((item) => {
    const key = item.section || 'system'
    if (!buckets[key]) buckets[key] = []
    buckets[key].push(item)
  })

  return ['overview', 'sales', 'catalog', 'people', 'content', 'finance', 'automation', 'system']
    .filter((key) => buckets[key]?.length)
    .map((key) => ({
      id: key,
      label: sectionLabels[key],
      Icon: sectionIcons[key],
      items: buckets[key],
    }))
}

function flattenMenu(menu) {
  return menu.flatMap((item) => {
    const parent = item.href && item.href !== '#' ? [item] : []
    return parent.concat(item.children || [])
  })
}

function findPageTitle(file) {
  const selectors = [
    '.content-header h1',
    '.admin-dashboard-header h1',
    '.store-dash .sd-title',
    '.dr-dash .dr-title',
    '.box-title',
    'h1',
    'h2',
  ]

  for (const selector of selectors) {
    const node = document.querySelector(selector)
    const text = cleanText(node?.textContent || '')
    if (text) return decodeText(getPageTitle(file, text))
  }

  return decodeText(getPageTitle(file))
}

function getNotifications() {
  const orderRoot = document.getElementById('orders-react-root')
  const pendingOrders = Number(orderRoot?.dataset.pending || 0)
  const pendingNoCalls = Number(orderRoot?.dataset.pendingNoCalls || 0)
  const incomplete = Number(orderRoot?.dataset.pendingFollowup || 0)
  const dashboardAttention = cleanText(document.querySelector('.admin-dashboard-hero-main p strong')?.textContent || '')
  const lowStock = cleanText(document.querySelector('.admin-mini-card.is-danger strong')?.textContent || '')

  return [
    pendingOrders
      ? { label: 'طلبات تنتظر التأكيد', value: pendingOrders, href: 'order.php', tone: 'warning' }
      : null,
    pendingNoCalls
      ? { label: 'طلبات بلا اتصال', value: pendingNoCalls, href: 'order.php', tone: 'danger' }
      : null,
    incomplete
      ? { label: 'متابعات مطلوبة', value: incomplete, href: 'incomplete-orders.php', tone: 'warning' }
      : null,
    dashboardAttention
      ? { label: 'عناصر تحتاج متابعة', value: dashboardAttention, href: 'index.php', tone: 'warning' }
      : null,
    lowStock
      ? { label: 'منتجات منخفضة المخزون', value: lowStock, href: 'product.php', tone: 'danger' }
      : null,
  ].filter(Boolean)
}

function NavLink({ item, collapsed, opened, toggle }) {
  const hasChildren = item.children?.length > 0
  const isActive = item.active || item.children?.some((child) => child.active)
  const Icon = getIconForFile(item.file, item.section)

  return (
    <div className={`saas-nav-item${opened ? ' is-open' : ''}`}>
      <Tooltip label={item.title} position="left" disabled={!collapsed} withArrow>
        <UnstyledButton
          component={hasChildren ? 'button' : 'a'}
          href={hasChildren ? undefined : item.href}
          type={hasChildren ? 'button' : undefined}
          role={hasChildren ? 'button' : undefined}
          aria-expanded={hasChildren ? opened : undefined}
          className={`saas-nav-link${isActive ? ' is-active' : ''}`}
          onClick={hasChildren ? (event) => {
            event.preventDefault()
            toggle()
          } : undefined}
        >
          <Icon size={19} stroke={1.8} />
          {!collapsed ? <span>{item.title}</span> : null}
          {!collapsed && hasChildren ? (
            <IconChevronLeft className={opened ? 'is-open' : ''} size={15} stroke={1.8} />
          ) : null}
        </UnstyledButton>
      </Tooltip>

      {!collapsed && hasChildren ? (
        <div
          className="saas-subnav-wrapper"
          style={{
            display: 'grid',
            gridTemplateRows: opened ? '1fr' : '0fr',
            transition: 'grid-template-rows 200ms ease',
            overflow: 'hidden',
          }}
        >
          <div className="saas-subnav" style={{ overflow: 'hidden' }}>
            {item.children.map((child) => {
              const ChildIcon = getIconForFile(child.file, child.section)
              return (
                <a
                  href={child.href}
                  className={`saas-subnav-link${child.active ? ' is-active' : ''}`}
                  key={child.id}
                >
                  <ChildIcon size={15} stroke={1.8} />
                  <span>{child.title}</span>
                </a>
              )
            })}
          </div>
        </div>
      ) : null}
    </div>
  )
}

export default function AdminShell() {
  const [mobileOpened, mobileHandlers] = useDisclosure(false)
  const [collapsed, setCollapsed] = React.useState(() => window.localStorage.getItem('adminShellCollapsed') === '1')
  const [openSubMenus, setOpenSubMenus] = React.useState({})
  const [searchQuery, setSearchQuery] = React.useState('')
  const [searchFocused, setSearchFocused] = React.useState(false)

  const file = currentFile()
  const menu = React.useMemo(() => readMenu(), [])
  const groupedMenu = React.useMemo(() => groupMenu(menu), [menu])
  const flatMenu = React.useMemo(() => flattenMenu(menu), [menu])
  const notifications = React.useMemo(() => getNotifications(), [])
  const pageTitle = React.useMemo(() => findPageTitle(file), [file])
  const activeItem = flatMenu.find((item) => item.file === file)
  const activeSection = activeItem?.section ? sectionLabels[activeItem.section] : sectionLabels[getSectionForFile(file)]
  const adminName = decodeText(
    document.getElementById('admin-react-shell')?.getAttribute('data-admin-name') || 'المدير',
  )

  const filteredCommands = React.useMemo(() => {
    const query = searchQuery.toLowerCase()
    if (!query) return flatMenu.slice(0, 8)
    return flatMenu
      .filter((item) => `${item.title} ${item.file} ${sectionLabels[item.section] || ''}`.toLowerCase().includes(query))
      .slice(0, 8)
  }, [flatMenu, searchQuery])

  useHotkeys([
    ['mod+K', () => setSearchFocused(true)],
    ['Escape', () => setSearchFocused(false)],
  ])

  React.useEffect(() => {
    document.body.classList.remove('admin-react-pending')
    document.body.classList.add('admin-react-ready')
    document.body.classList.toggle('admin-react-collapsed', collapsed)
    document.body.classList.toggle('admin-react-mobile-open', mobileOpened)
    document.documentElement.style.setProperty('--admin-shell-sidebar-width', collapsed ? '84px' : '288px')
    window.localStorage.setItem('adminShellCollapsed', collapsed ? '1' : '0')
  }, [collapsed, mobileOpened])

  React.useEffect(() => {
    const title = pageTitle ? `${pageTitle} | متجر الثقة` : 'لوحة التحكم | متجر الثقة'
    document.title = title
  }, [pageTitle])

  const toggleSubMenu = (id) => {
    setOpenSubMenus((prev) => ({ ...prev, [id]: !prev[id] }))
  }

  return (
    <div className="saas-shell" dir="rtl">
      <aside className="saas-sidebar" aria-label="Admin navigation">
        <a className="saas-brand" href="index.php" title="متجر الثقة">
          <span className="saas-brand-mark">MT</span>
          {!collapsed ? (
            <span className="saas-brand-copy">
              <strong>متجر الثقة</strong>
              <small>Enterprise Console</small>
            </span>
          ) : null}
        </a>

        <ScrollArea className="saas-nav-scroll" type="hover" scrollbarSize={5}>
          {groupedMenu.map((group) => (
            <section className="saas-nav-group" key={group.id}>
              {!collapsed ? (
                <div className="saas-nav-heading">
                  <group.Icon size={15} stroke={1.8} />
                  <span>{group.label}</span>
                </div>
              ) : null}
              {group.items.map((item) => (
                <NavLink
                  item={item}
                  collapsed={collapsed}
                  opened={openSubMenus[item.id] || item.active || item.children?.some((child) => child.active)}
                  toggle={() => toggleSubMenu(item.id)}
                  key={item.id}
                />
              ))}
            </section>
          ))}
        </ScrollArea>
      </aside>

      <button
        type="button"
        aria-label="إغلاق القائمة"
        className="saas-mobile-backdrop"
        onClick={mobileHandlers.close}
      />

      <header className="saas-topbar">
        <Group gap="sm" wrap="nowrap" className="saas-topbar-title-group">
          <Burger opened={mobileOpened} onClick={mobileHandlers.toggle} hiddenFrom="md" size="sm" />
          <Tooltip label={collapsed ? 'توسيع القائمة' : 'تصغير القائمة'} withArrow>
            <ActionIcon
              className="saas-collapse-button"
              variant="subtle"
              color="gray"
              radius="md"
              visibleFrom="md"
              onClick={() => setCollapsed((value) => !value)}
              aria-label="تصغير القائمة"
            >
              <IconMenu2 size={19} stroke={1.8} />
            </ActionIcon>
          </Tooltip>
          <div className="saas-breadcrumb">
            <span>{activeSection}</span>
            <strong>{pageTitle}</strong>
          </div>
        </Group>

        <div className={`saas-command${searchFocused ? ' is-focused' : ''}`}>
          <TextInput
            aria-label="البحث السريع"
            leftSection={<IconSearch size={16} stroke={1.8} />}
            rightSection={<Kbd>⌘K</Kbd>}
            placeholder="ابحث عن صفحة أو إجراء..."
            value={searchQuery}
            onFocus={() => setSearchFocused(true)}
            onChange={(event) => setSearchQuery(event.currentTarget.value)}
          />
          {searchFocused ? (
            <div className="saas-command-panel">
              {filteredCommands.length ? (
                filteredCommands.map((item) => {
                  const Icon = getIconForFile(item.file, item.section)
                  return (
                    <a className="saas-command-result" href={item.href} key={item.id}>
                      <Icon size={17} stroke={1.8} />
                      <span>
                        <strong>{item.title}</strong>
                        <small>{sectionLabels[item.section] || 'النظام'}</small>
                      </span>
                      <IconChevronLeft size={14} stroke={1.8} />
                    </a>
                  )
                })
              ) : (
                <Text className="saas-command-empty">لا توجد نتائج مطابقة</Text>
              )}
            </div>
          ) : null}
        </div>

        <Group gap="xs" wrap="nowrap" className="saas-topbar-actions">
          <Menu width={240} position="bottom-end" shadow="lg">
            <Menu.Target>
              <ActionIcon variant="subtle" color="gray" radius="md" aria-label="الإجراءات السريعة">
                <IconCommand size={19} stroke={1.8} />
              </ActionIcon>
            </Menu.Target>
            <Menu.Dropdown>
              <Menu.Label>إجراءات سريعة</Menu.Label>
              {quickActions.map((action) => (
                <Menu.Item
                  component="a"
                  href={action.href}
                  leftSection={<action.icon size={15} stroke={1.8} />}
                  key={action.href}
                >
                  {action.label}
                </Menu.Item>
              ))}
            </Menu.Dropdown>
          </Menu>

          <Menu width={280} position="bottom-end" shadow="lg">
            <Menu.Target>
              <ActionIcon variant="subtle" color="gray" radius="md" aria-label="التنبيهات">
                <IconBell size={19} stroke={1.8} />
                {notifications.length ? <span className="saas-notification-dot" /> : null}
              </ActionIcon>
            </Menu.Target>
            <Menu.Dropdown>
              <Menu.Label>التنبيهات</Menu.Label>
              {notifications.length ? (
                notifications.map((item) => (
                  <Menu.Item component="a" href={item.href} key={`${item.label}-${item.href}`}>
                    <Group justify="space-between" wrap="nowrap">
                      <Text size="sm" fw={700}>{item.label}</Text>
                      <Badge color={item.tone === 'danger' ? 'red' : 'orange'} variant="light">{item.value}</Badge>
                    </Group>
                  </Menu.Item>
                ))
              ) : (
                <Text size="sm" c="dimmed" p="sm">لا توجد تنبيهات نشطة</Text>
              )}
            </Menu.Dropdown>
          </Menu>

          <Tooltip label="عرض المتجر" withArrow>
            <ActionIcon
              component="a"
              href="../index.php"
              target="_blank"
              rel="noopener"
              variant="subtle"
              color="gray"
              radius="md"
              aria-label="عرض المتجر"
            >
              <IconExternalLink size={19} stroke={1.8} />
            </ActionIcon>
          </Tooltip>

          <Menu width={230} position="bottom-end" shadow="lg">
            <Menu.Target>
              <UnstyledButton className="saas-profile-button">
                <Avatar size={30} radius="md" color="indigo">
                  {getInitials(adminName)}
                </Avatar>
                <span>{adminName}</span>
                <IconChevronDown size={14} stroke={1.8} />
              </UnstyledButton>
            </Menu.Target>
            <Menu.Dropdown>
              <Menu.Label>{adminName}</Menu.Label>
              <Menu.Item component="a" href="profile-edit.php" leftSection={<IconUserCircle size={15} stroke={1.8} />}>
                الملف الشخصي
              </Menu.Item>
              <Menu.Item component="a" href="settings.php" leftSection={<IconSettings size={15} stroke={1.8} />}>
                الإعدادات
              </Menu.Item>
              <Menu.Divider />
              <Menu.Item color="red" component="a" href="logout.php" leftSection={<IconLogout size={15} stroke={1.8} />}>
                تسجيل الخروج
              </Menu.Item>
            </Menu.Dropdown>
          </Menu>
        </Group>
      </header>
    </div>
  )
}

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
  sharedTrans,
  langLabels,
  langFlags,
  uiTranslations,
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

      const titleNode = link?.querySelector('span:not(.pull-right-container)')
      const fallbackTitle = titleNode ? titleNode.textContent : link?.textContent
      const cleanFallback = cleanText(fallbackTitle)
      const section = getSectionForFile(file, cleanFallback)
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

function getNotifications(currentLang) {
  const orderRoot = document.getElementById('orders-react-root')
  const pendingOrders = Number(orderRoot?.dataset.pending || 0)
  const pendingNoCalls = Number(orderRoot?.dataset.pendingNoCalls || 0)
  const incomplete = Number(orderRoot?.dataset.pendingFollowup || 0)
  const dashboardAttention = cleanText(document.querySelector('.admin-dashboard-hero-main p strong')?.textContent || '')
  const lowStock = cleanText(document.querySelector('.admin-mini-card.is-danger strong')?.textContent || '')

  const trans = uiTranslations[currentLang] || uiTranslations['ar']

  return [
    pendingOrders
      ? { label: trans.pendingConfirm, value: pendingOrders, href: 'order.php', tone: 'warning' }
      : null,
    pendingNoCalls
      ? { label: trans.noCalls, value: pendingNoCalls, href: 'order.php', tone: 'danger' }
      : null,
    incomplete
      ? { label: trans.followups, value: incomplete, href: 'incomplete-orders.php', tone: 'warning' }
      : null,
    dashboardAttention
      ? { label: trans.attentionRequired, value: dashboardAttention, href: 'index.php', tone: 'warning' }
      : null,
    lowStock
      ? { label: trans.lowStock, value: lowStock, href: 'product.php', tone: 'danger' }
      : null,
  ].filter(Boolean)
}

function NavLink({ item, collapsed, opened, toggle }) {
  const hasChildren = item.children?.length > 0
  const isActive = item.active || item.children?.some((child) => child.active)
  const isOpen = hasChildren && (opened || isActive)
  const Icon = getIconForFile(item.file, item.section)

  return (
    <div className={`saas-nav-item${isOpen ? ' is-open' : ''}`}>
      <Tooltip label={item.title} position="left" disabled={!collapsed} withArrow>
        <UnstyledButton
          component={hasChildren ? 'button' : 'a'}
          href={hasChildren ? undefined : item.href}
          type={hasChildren ? 'button' : undefined}
          role={hasChildren ? 'button' : undefined}
          aria-expanded={hasChildren ? isOpen : undefined}
          className={`saas-nav-link${isActive ? ' is-active' : ''}`}
          onClick={hasChildren ? (event) => {
            event.preventDefault()
            toggle()
          } : undefined}
        >
          <Icon size={19} stroke={1.8} />
          {!collapsed ? <span>{item.title}</span> : null}
          {!collapsed && hasChildren ? (
            <IconChevronLeft className={isOpen ? 'is-open' : ''} size={15} stroke={1.8} />
          ) : null}
        </UnstyledButton>
      </Tooltip>

      {!collapsed && hasChildren ? (
        <div
          className="saas-subnav-wrapper"
          style={{
            display: 'grid',
            gridTemplateRows: isOpen ? '1fr' : '0fr',
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

// Localized maps moved to pageMeta.js

export default function AdminShell() {
  const [currentPath, setCurrentPath] = React.useState(() => currentFile())
  const [mobileOpened, mobileHandlers] = useDisclosure(false)
  const [collapsed, setCollapsed] = React.useState(() => window.localStorage.getItem('adminShellCollapsed') === '1')
  const [openSubMenus, setOpenSubMenus] = React.useState({})
  const [searchQuery, setSearchQuery] = React.useState('')
  const [searchFocused, setSearchFocused] = React.useState(false)

  React.useEffect(() => {
    const handlePageLoad = () => {
      setCurrentPath(currentFile())
    }
    document.addEventListener('spa:pageLoaded', handlePageLoad)
    window.addEventListener('popstate', handlePageLoad)
    return () => {
      document.removeEventListener('spa:pageLoaded', handlePageLoad)
      window.removeEventListener('popstate', handlePageLoad)
    }
  }, [])

  const currentLang = document.getElementById('admin-react-shell')?.getAttribute('data-current-lang') || 'ar'
  const t = (key) => uiTranslations[currentLang]?.[key] || uiTranslations['ar'][key] || key

  const handleLangChange = (event, lang) => {
    event.preventDefault()
    const url = new URL(window.location.href, window.location.origin)
    url.searchParams.set('lang', lang)
    window.location.href = url.toString()
  }

  const file = currentPath
  const menu = React.useMemo(() => readMenu(), [currentPath])
  const groupedMenu = React.useMemo(() => groupMenu(menu), [menu])
  const flatMenu = React.useMemo(() => flattenMenu(menu), [menu])
  const notifications = React.useMemo(() => getNotifications(currentLang), [currentLang])
  const pageTitle = React.useMemo(() => findPageTitle(file), [file])
  const activeItem = flatMenu.find((item) => item.file === file)
  const activeSection = activeItem?.section ? sectionLabels[activeItem.section] : sectionLabels[getSectionForFile(file)]
  const adminName = decodeText(
    document.getElementById('admin-react-shell')?.getAttribute('data-admin-name') || sharedTrans.adminFallback,
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

    const direction = currentLang === 'ar' ? 'rtl' : 'ltr'
    document.body.dir = direction
    document.body.style.direction = direction
    document.documentElement.dir = direction
  }, [collapsed, mobileOpened, currentLang])

  React.useEffect(() => {
    const title = pageTitle ? `${pageTitle} | ${sharedTrans.brandName}` : `${sharedTrans.controlPanel} | ${sharedTrans.brandName}`
    document.title = title
  }, [pageTitle, currentLang])

  const toggleSubMenu = (id) => {
    setOpenSubMenus((prev) => ({ ...prev, [id]: !prev[id] }))
  }

  const isSubMenuOpen = (item) => {
    if (Object.prototype.hasOwnProperty.call(openSubMenus, item.id)) {
      return openSubMenus[item.id]
    }

    return item.active || item.children?.some((child) => child.active)
  }

  const handleSubMenuToggle = (item) => {
    if (collapsed) {
      setCollapsed(false)
    }

    toggleSubMenu(item.id)
  }

  return (
    <div className="saas-shell" dir={currentLang === 'ar' ? 'rtl' : 'ltr'}>
      <aside className="saas-sidebar" aria-label="Admin navigation">
        <a className="saas-brand" href="index.php" title={sharedTrans.brandName}>
          <span className="saas-brand-mark">MT</span>
          {!collapsed ? (
            <span className="saas-brand-copy">
              <strong>{sharedTrans.brandName}</strong>
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
                  opened={isSubMenuOpen(item)}
                  toggle={() => handleSubMenuToggle(item)}
                  key={item.id}
                />
              ))}
            </section>
          ))}
        </ScrollArea>
      </aside>

      <button
        type="button"
        aria-label={t('closeMenu')}
        className="saas-mobile-backdrop"
        onClick={mobileHandlers.close}
      />

      <header className="saas-topbar">
        <Group gap="sm" wrap="nowrap" className="saas-topbar-title-group">
          <Burger opened={mobileOpened} onClick={mobileHandlers.toggle} hiddenFrom="md" size="sm" />
          <Tooltip label={collapsed ? t('expandMenu') : t('collapseMenu')} withArrow>
            <ActionIcon
              className="saas-collapse-button"
              variant="subtle"
              color="gray"
              radius="md"
              visibleFrom="md"
              onClick={() => setCollapsed((value) => !value)}
              aria-label={t('collapseMenu')}
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
            aria-label={t('searchPlaceholder')}
            leftSection={<IconSearch size={16} stroke={1.8} />}
            rightSection={<Kbd>⌘K</Kbd>}
            placeholder={t('searchPlaceholder')}
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
                        <small>{sectionLabels[item.section] || t('system')}</small>
                      </span>
                      <IconChevronLeft size={14} stroke={1.8} />
                    </a>
                  )
                })
              ) : (
                <Text className="saas-command-empty">{t('noResults')}</Text>
              )}
            </div>
          ) : null}
        </div>

        <Group gap="xs" wrap="nowrap" className="saas-topbar-actions">
          <Menu width={240} position="bottom-end" shadow="lg">
            <Menu.Target>
              <ActionIcon variant="subtle" color="gray" radius="md" aria-label={t('quickActions')}>
                <IconCommand size={19} stroke={1.8} />
              </ActionIcon>
            </Menu.Target>
            <Menu.Dropdown>
              <Menu.Label>{t('quickActions')}</Menu.Label>
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
              <ActionIcon variant="subtle" color="gray" radius="md" aria-label={t('notifications')}>
                <IconBell size={19} stroke={1.8} />
                {notifications.length ? <span className="saas-notification-dot" /> : null}
              </ActionIcon>
            </Menu.Target>
            <Menu.Dropdown>
              <Menu.Label>{t('notifications')}</Menu.Label>
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
                <Text size="sm" c="dimmed" p="sm">{t('noActiveNotifications')}</Text>
              )}
            </Menu.Dropdown>
          </Menu>

          <Tooltip label={t('viewStore')} withArrow>
            <ActionIcon
              component="a"
              href="../index.php"
              target="_blank"
              rel="noopener"
              variant="subtle"
              color="gray"
              radius="md"
              aria-label={t('viewStore')}
            >
              <IconExternalLink size={19} stroke={1.8} />
            </ActionIcon>
          </Tooltip>

          <Menu width={160} position="bottom-end" shadow="lg">
            <Menu.Target>
              <UnstyledButton className="saas-lang-button" style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                padding: '6px 10px',
                borderRadius: 'var(--admin-radius)',
                color: 'var(--admin-text)',
                cursor: 'pointer',
                transition: 'background 0.2s',
              }}
              onMouseEnter={(e) => e.currentTarget.style.backgroundColor = '#f1f5f9'}
              onMouseLeave={(e) => e.currentTarget.style.backgroundColor = 'transparent'}
              >
                <span style={{ fontSize: 16 }}>{langFlags[currentLang]}</span>
                <span style={{ fontSize: 13, fontWeight: 750 }}>{langLabels[currentLang]}</span>
                <IconChevronDown size={14} stroke={1.8} />
              </UnstyledButton>
            </Menu.Target>
            <Menu.Dropdown>
              <Menu.Item onClick={(e) => handleLangChange(e, 'ar')} leftSection={<span style={{ fontSize: 16 }}>🇩🇿</span>} className={currentLang === 'ar' ? 'is-active' : ''}>
                {langLabels.ar}
              </Menu.Item>
              <Menu.Item onClick={(e) => handleLangChange(e, 'fr')} leftSection={<span style={{ fontSize: 16 }}>🇫🇷</span>} className={currentLang === 'fr' ? 'is-active' : ''}>
                Français
              </Menu.Item>
              <Menu.Item onClick={(e) => handleLangChange(e, 'en')} leftSection={<span style={{ fontSize: 16 }}>🇺🇸</span>} className={currentLang === 'en' ? 'is-active' : ''}>
                English
              </Menu.Item>
            </Menu.Dropdown>
          </Menu>

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
                {t('profile')}
              </Menu.Item>
              <Menu.Item component="a" href="settings.php" leftSection={<IconSettings size={15} stroke={1.8} />}>
                {t('settings')}
              </Menu.Item>
              <Menu.Divider />
              <Menu.Item color="red" component="a" href="logout.php" leftSection={<IconLogout size={15} stroke={1.8} />}>
                {t('logout')}
              </Menu.Item>
            </Menu.Dropdown>
          </Menu>
        </Group>
      </header>
    </div>
  )
}

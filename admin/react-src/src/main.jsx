import React, { Suspense, lazy } from 'react'
import ReactDOM from 'react-dom/client'
import { MantineProvider, createTheme } from '@mantine/core'
import { ModalsProvider } from '@mantine/modals'
import { Notifications } from '@mantine/notifications'

import '@mantine/core/styles.css'
import '@mantine/notifications/styles.css'
import './index.css'

import AdminShell from './components/AdminShell.jsx'
import { SkeletonPage } from './components/Enterprise.jsx'
import { currentFile, decodeText } from './lib/text.js'
import { getPageTitle, getSectionForFile, sectionLabels, sharedTrans } from './lib/pageMeta.js'

window.addEventListener('error', (event) => {
  fetch('log_error.php?err=' + encodeURIComponent(event.message + ' at ' + event.filename + ':' + event.lineno))
})
window.addEventListener('unhandledrejection', (event) => {
  fetch('log_error.php?err=' + encodeURIComponent('Promise Rejection: ' + event.reason))
})
window.logReact = (msg) => {
  fetch('log_error.php?err=' + encodeURIComponent('[ReactLog] ' + msg))
}

const pageLoaders = {
  dashboard: () => import('./pages/Dashboard.jsx'),
  orders: () => import('./pages/Orders.jsx'),
  productForm: () => import('./pages/ProductForm.jsx'),
  otherTables: () => import('./pages/OtherTables.jsx'),
  settings: () => import('./pages/SettingsPage.jsx'),
  content: () => import('./pages/ContentPage.jsx'),
  aiKnowledge: () => import('./pages/AiKnowledge.jsx'),
  marketingAi: () => import('./pages/MarketingAi.jsx'),
}

const Dashboard = lazy(pageLoaders.dashboard)
const Orders = lazy(pageLoaders.orders)
const ProductForm = lazy(pageLoaders.productForm)
const OtherTables = lazy(pageLoaders.otherTables)
const SettingsPage = lazy(pageLoaders.settings)
const ContentPage = lazy(pageLoaders.content)
const AiKnowledge = lazy(pageLoaders.aiKnowledge)
const MarketingAi = lazy(pageLoaders.marketingAi)

const theme = createTheme({
  primaryColor: 'indigo',
  fontFamily: 'CairoLocal, InterLocal, Cairo, Inter, system-ui, sans-serif',
  headings: {
    fontFamily: 'CairoLocal, InterLocal, Cairo, Inter, system-ui, sans-serif',
    fontWeight: '800',
  },
  defaultRadius: 'md',
  colors: {
    indigo: [
      '#eef2ff',
      '#e0e7ff',
      '#c7d2fe',
      '#a5b4fc',
      '#818cf8',
      '#6366f1',
      '#4f46e5',
      '#4338ca',
      '#3730a3',
      '#312e81',
    ],
    teal: [
      '#f0fdfa',
      '#ccfbf1',
      '#99f6e4',
      '#5eead4',
      '#2dd4bf',
      '#14b8a6',
      '#0d9488',
      '#0f766e',
      '#115e59',
      '#134e4a',
    ],
    orange: [
      '#fffbeb',
      '#fef3c7',
      '#fde68a',
      '#fcd34d',
      '#f59e0b',
      '#d97706',
      '#b45309',
      '#92400e',
      '#78350f',
      '#451a03',
    ],
    red: [
      '#fef2f2',
      '#fee2e2',
      '#fecaca',
      '#fca5a5',
      '#f87171',
      '#ef4444',
      '#dc2626',
      '#b91c1c',
      '#991b1b',
      '#7f1d1d',
    ],
  },
})

function routeLoader(path) {
  if (path === 'order.php') return pageLoaders.orders
  if (path === 'settings.php') return pageLoaders.settings
  if (path === 'ai-knowledge.php') return pageLoaders.aiKnowledge
  if (['index.php', '', 'store-dashboard.php', 'store.php'].includes(path)) return pageLoaders.dashboard
  if (shouldUseGenericForm(path)) return pageLoaders.productForm
  if (['system-health.php', 'my-earnings.php', 'employee-performance.php', 'employee-payments.php'].includes(path)) return pageLoaders.content
  return pageLoaders.otherTables
}

routeLoader(currentFile())?.()

function Providers({ children }) {
  return (
    <MantineProvider theme={theme} defaultColorScheme="light">
      <Notifications position="bottom-left" />
      <ModalsProvider>{children}</ModalsProvider>
    </MantineProvider>
  )
}

function mountReact(container, children) {
  if (!container || container.dataset.reactMounted === '1') return
  container.dataset.reactMounted = '1'
  ReactDOM.createRoot(container).render(
    <React.StrictMode>
      <Providers>{children}</Providers>
    </React.StrictMode>,
  )
}

function mountShell() {
  const shellRoot = document.getElementById('admin-react-shell')
  mountReact(shellRoot, <AdminShell />)
}

function insertBefore(node, id, reference) {
  const existing = document.getElementById(id)
  if (existing) return existing

  const container = document.createElement('div')
  container.id = id
  reference.parentNode.insertBefore(container, reference)
  return container
}

function findDashboardAnchor() {
  return (
    document.querySelector('.admin-dashboard') ||
    document.querySelector('.admin-dashboard-hero') ||
    document.querySelector('.store-dash') ||
    document.querySelector('.premium-dashboard') ||
    document.querySelector('.dashboard-wrapper')
  )
}

function findPrimaryTable() {
  return (
    document.querySelector('.content-wrapper table.table') ||
    document.querySelector('.content-wrapper table.bu-table') ||
    document.querySelector('.content-wrapper table.ak-table') ||
    document.querySelector('.content-wrapper table.b-table') ||
    document.querySelector('.content-wrapper table.qd-table') ||
    document.querySelector('.content-wrapper table.dr-table') ||
    document.querySelector('.content-wrapper table')
  )
}

function findPrimaryForm(path) {
  const forms = Array.from(document.querySelectorAll('.content-wrapper form')).filter((form) => {
    if (form.closest('.modal, .orders-status-tab-content, .bulkbar, .navbar, .main-sidebar')) return false
    if (form.classList.contains('bulk-form') || form.classList.contains('form-inline')) return false
    const controls = form.querySelectorAll('input:not([type="hidden"]), select, textarea')
    return controls.length >= 1
  })

  if (path === 'product-add.php' || path === 'product-edit.php') {
    return document.querySelector('.admin-product-form') || forms[0] || null
  }

  if (forms.length === 1) return forms[0]
  if (/(?:-add|-edit)\.php$/.test(path)) return forms[0] || null
  if (['profile-edit.php', 'performance-settings.php', 'integrations.php'].includes(path)) return forms[0] || null
  return null
}

function findPrimaryContent() {
  return Array.from(document.querySelectorAll('.content-wrapper > section.content, .content-wrapper > .content'))
    .find((section) => section.textContent.trim().length > 0 && !section.querySelector('#orders-react-root'))
}

function shouldUseGenericForm(path) {
  if (path === 'product-add.php' || path === 'product-edit.php') return true
  if (['settings.php', 'order.php', 'store-dashboard.php', 'store.php', 'index.php'].includes(path)) return false
  return /(?:-add|-edit)\.php$/.test(path) || ['profile-edit.php', 'performance-settings.php', 'integrations.php'].includes(path)
}

function getPageType(path) {
  if (path === 'order.php') return 'orders'
  if (path === 'settings.php') return 'settings'
  if (path === 'ai-knowledge.php') return 'aiKnowledge'
  if (path === 'marketing-ai.php') return 'marketingAi'
  if (['index.php', '', 'store-dashboard.php', 'store.php'].includes(path)) return 'dashboard'
  if (shouldUseGenericForm(path)) return 'productForm'
  if (['system-health.php', 'my-earnings.php', 'employee-performance.php', 'employee-payments.php'].includes(path)) return 'content'
  return 'otherTables'
}

function mountPageSpecific() {
  const path = currentFile()
  routeLoader(path)?.()

  const pageType = getPageType(path)
  
  const expectedRootId = {
    orders: 'orders-react-root',
    settings: 'settings-react-root',
    aiKnowledge: 'ai-knowledge-react-root',
    marketingAi: 'marketing-ai-react-root',
    dashboard: 'dashboard-react-root',
    productForm: 'admin-form-react-root',
    content: 'content-react-root',
    otherTables: 'other-tables-react-root'
  }[pageType]

  if (window.logReact) {
    window.logReact('mountPageSpecific path: ' + path + ', pageType: ' + pageType + ', expectedRootId: ' + expectedRootId + ', exists: ' + !!document.getElementById(expectedRootId))
  }

  if (expectedRootId && document.getElementById(expectedRootId)) {
    // Already mounted the correct component
    return
  }

  // Clean up any incorrect roots that might have mounted
  const allRoots = [
    'orders-react-root',
    'settings-react-root',
    'ai-knowledge-react-root',
    'marketing-ai-react-root',
    'dashboard-react-root',
    'admin-form-react-root',
    'other-tables-react-root',
    'content-react-root'
  ]
  allRoots.forEach(id => {
    if (id !== expectedRootId) {
      const el = document.getElementById(id)
      if (el) el.remove()
    }
  })

  if (pageType === 'orders') {
    const root = document.getElementById('orders-react-root')
    if (root) {
      mountReact(root, (
        <Suspense fallback={<SkeletonPage />}>
          <Orders />
        </Suspense>
      ))
    }
    return
  }

  if (pageType === 'settings') {
    const sourceTabs = document.querySelector('.nav-tabs-custom')
    if (sourceTabs) {
      const root = insertBefore(sourceTabs.parentNode, 'settings-react-root', sourceTabs)
      mountReact(root, (
        <Suspense fallback={<SkeletonPage />}>
          <SettingsPage sourceTabs={sourceTabs} />
        </Suspense>
      ))
    }
    return
  }

  if (pageType === 'aiKnowledge') {
    const contentHeader = document.querySelector('.content-header')
    const content = document.querySelector('.content')
    if (contentHeader) contentHeader.style.display = 'none'
    if (content) content.style.display = 'none'
    
    const mountReference = contentHeader || content
    if (mountReference) {
      const root = insertBefore(mountReference.parentNode, 'ai-knowledge-react-root', mountReference)
      mountReact(root, (
        <Suspense fallback={<SkeletonPage />}>
          <AiKnowledge />
        </Suspense>
      ))
    }
    return
  }

  if (pageType === 'marketingAi') {
    const legacyWrapper = document.querySelector('.content-wrapper')
    const root = insertBefore(legacyWrapper || document.body, 'marketing-ai-react-root', legacyWrapper ? legacyWrapper.firstChild : null)
    mountReact(root, (
      <Suspense fallback={<SkeletonPage />}>
        <MarketingAi />
      </Suspense>
    ))
    return
  }

  if (pageType === 'dashboard') {
    const anchor = findDashboardAnchor()
    if (anchor) {
      const root = insertBefore(anchor.parentNode, 'dashboard-react-root', anchor)
      mountReact(root, (
        <Suspense fallback={<SkeletonPage />}>
          <Dashboard legacyContainer={anchor} />
        </Suspense>
      ))
    }
    return
  }

  if (pageType === 'productForm') {
    const form = findPrimaryForm(path)
    if (form) {
      const root = insertBefore(form.parentNode, 'admin-form-react-root', form)
      const section = sectionLabels[getSectionForFile(path)] || sharedTrans.administration
      mountReact(root, (
        <Suspense fallback={<SkeletonPage />}>
          <ProductForm
            sourceForm={form}
            isEdit={/edit/.test(path)}
            pageName={path}
            titleOverride={decodeText(getPageTitle(path))}
            eyebrow={section}
          />
        </Suspense>
      ))
    }
    return
  }

  if (pageType === 'otherTables') {
    const table = findPrimaryTable()
    if (window.logReact) {
      window.logReact('otherTables block. table: ' + (table ? 'found id=' + table.id + ' class=' + table.className : 'null'))
    }
    if (table) {
      const mountReference = table.closest('.box, .card, .panel, .table-responsive, .dataTables_wrapper') || table
      const root = insertBefore(mountReference.parentNode, 'other-tables-react-root', mountReference)
      mountReact(root, (
        <Suspense fallback={<SkeletonPage />}>
          <OtherTables legacyTable={table} pageName={path} />
        </Suspense>
      ))
    }
    return
  }

  if (pageType === 'content') {
    const content = findPrimaryContent()
    if (content) {
      const root = insertBefore(content.parentNode, 'content-react-root', content)
      mountReact(root, (
        <Suspense fallback={<SkeletonPage />}>
          <ContentPage sourceContent={content} pageName={path} />
        </Suspense>
      ))
    }
    return
  }
}

try {
  mountShell()
  window.logReact('mountShell OK')
} catch (e) {
  window.logReact('mountShell ERROR: ' + e.message + ' ' + e.stack)
  document.body.classList.remove('admin-react-pending')
}

try {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountPageSpecific)
  } else {
    mountPageSpecific()
  }
  window.logReact('mountPageSpecific OK')
} catch (e) {
  window.logReact('mountPageSpecific ERROR: ' + e.message + ' ' + e.stack)
}

document.addEventListener('spa:pageLoaded', mountPageSpecific)

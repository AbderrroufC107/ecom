import React, { useState, useEffect } from 'react'
import { Card, Group, Select, Text, TextInput, Button, Badge } from '@mantine/core'
import { DataTable } from 'mantine-datatable'
import { IconSearch, IconPlus, IconEdit } from '@tabler/icons-react'
import { PageHeader, EmptyState, ToolbarButton } from '../components/Enterprise.jsx'

export default function AiKnowledge() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [category, setCategory] = useState('')
  const [page, setPage] = useState(1)
  const PAGE_SIZE = 14

  const loadData = async () => {
    setLoading(true)
    try {
      const url = new URL(window.location.origin + '/admin/ajax-knowledge-search.php')
      if (searchQuery) url.searchParams.set('q', searchQuery)
      if (category) url.searchParams.set('category', category)

      const res = await fetch(url.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      const json = await res.json()
      if (json.status === 'success') {
        setData(json.data || [])
      } else {
        console.error('Failed to load knowledge', json)
      }
    } catch (e) {
      console.error(e)
    } finally {
      setLoading(false)
    }
  }

  // Load initial data
  useEffect(() => {
    loadData()
  }, [])

  // Handle URL category sync if needed
  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search)
    const cat = urlParams.get('category')
    if (cat && cat !== category) {
      setCategory(cat)
    }
  }, [])

  const handleSearch = () => {
    setPage(1)
    loadData()
  }

  const columns = [
    { accessor: 'id', title: 'ID', width: 80, sortable: true },
    { accessor: 'title', title: 'العنوان', sortable: true },
    { accessor: 'category_name', title: 'التصنيف', sortable: true },
    { 
      accessor: 'knowledge_type', 
      title: 'النوع',
      render: (record) => <Badge variant="light" color="gray">{record.knowledge_type}</Badge> 
    },
    { accessor: 'language', title: 'اللغة', sortable: true },
    { accessor: 'priority', title: 'الأولوية', sortable: true, width: 100, textAlign: 'center' },
    { 
      accessor: 'is_active', 
      title: 'الحالة',
      render: (record) => record.is_active == 1 
        ? <Badge color="green" variant="light">نشط</Badge> 
        : <Badge color="red" variant="light">معطل</Badge> 
    },
    {
      accessor: 'actions',
      title: 'الإجراءات',
      width: 120,
      textAlign: 'center',
      render: (record) => (
        <Button 
          variant="light" 
          size="xs" 
          leftSection={<IconEdit size={14} />}
          component="a"
          href="#"
        >
          تعديل
        </Button>
      )
    }
  ]

  const paginatedData = data.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)
  const tableHeight = Math.min(720, Math.max(420, paginatedData.length * 70 + 150))

  return (
    <main className="saas-page" dir="rtl">
      <PageHeader
        eyebrow="الذكاء الاصطناعي"
        title="مركز المعرفة (AI)"
        description="إدارة محرك البحث الشامل وقواعد بيانات الذكاء الاصطناعي"
        actions={
          <ToolbarButton
            href="ai-knowledge-add.php"
            icon={IconPlus}
            variant="filled"
          >
            إضافة عنصر معرفة جديد
          </ToolbarButton>
        }
      />

      <Card className="saas-surface saas-table-shell" withBorder mb="xl">
        <Group align="flex-end" mb="md" gap="sm">
          <TextInput
            placeholder="ابحث في العنوان أو المحتوى..."
            leftSection={<IconSearch size={16} />}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.currentTarget.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            style={{ flex: 1 }}
            label="البحث"
          />
          <Select
            data={[
              { value: '', label: 'كل التصنيفات' },
              { value: 'sales', label: 'المبيعات' },
              { value: 'company', label: 'الشركة' },
              { value: 'shipping', label: 'الشحن' },
              { value: 'returns', label: 'الاسترجاع' },
              { value: 'payments', label: 'الدفع' },
              { value: 'products', label: 'المنتجات' },
              { value: 'marketing', label: 'العلامة التجارية' },
              { value: 'style', label: 'أسلوب الكتابة' },
              { value: 'variables', label: 'متغيرات التلقين' }
            ]}
            value={category}
            onChange={setCategory}
            style={{ width: 220 }}
            label="التصنيف"
          />
          <Button onClick={handleSearch} leftSection={<IconSearch size={16} />}>بحث</Button>
        </Group>

        <DataTable
          className="saas-data-table"
          minHeight={320}
          height={tableHeight}
          withTableBorder={false}
          withColumnBorders={false}
          striped
          highlightOnHover
          fetching={loading}
          records={paginatedData}
          columns={columns}
          totalRecords={data.length}
          recordsPerPage={PAGE_SIZE}
          page={page}
          onPageChange={setPage}
          emptyState={<EmptyState title="لا توجد بيانات" description="جرب البحث بكلمات أخرى" />}
          paginationText={({ from, to, totalRecords }) => `${from}-${to} من ${totalRecords}`}
        />
      </Card>
    </main>
  )
}

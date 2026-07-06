import React, { useState, useEffect } from 'react'
import {
  Box,
  Card,
  Title,
  Text,
  Group,
  Stack,
  Badge,
  Button,
  Progress,
  ActionIcon,
  Tooltip,
} from '@mantine/core'
import { IconCheck, IconX, IconClock, IconBrain } from '@tabler/icons-react'
import { DataTable } from 'mantine-datatable'

export default function MarketingAi() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [stats, setStats] = useState({ pending: 0, applied: 0 })

  const loadData = async () => {
    setLoading(true)
    try {
      const res = await fetch('api/marketing-ai.php?action=list')
      const json = await res.json()
      if (json.success) {
        setData(json.data)
        
        let p = 0, a = 0
        json.data.forEach(item => {
          if (item.status === 'PENDING') p++
          if (item.status === 'APPLIED' || item.status === 'VERIFIED') a++
        })
        setStats({ pending: p, applied: a })
      }
    } catch (e) {
      console.error(e)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const handleApprove = async (id) => {
    if (!window.confirm('هل أنت متأكد من الموافقة على هذا التوصية؟')) return
    const fd = new FormData()
    fd.append('id', id)
    try {
      const res = await fetch('api/marketing-ai.php?action=approve', { method: 'POST', body: fd })
      const json = await res.json()
      if (json.success) loadData()
    } catch (e) {
      console.error(e)
    }
  }

  const handleReject = async (id) => {
    if (!window.confirm('هل أنت متأكد من رفض هذه التوصية؟')) return
    const fd = new FormData()
    fd.append('id', id)
    try {
      const res = await fetch('api/marketing-ai.php?action=reject', { method: 'POST', body: fd })
      const json = await res.json()
      if (json.success) loadData()
    } catch (e) {
      console.error(e)
    }
  }

  const columns = [
    {
      accessor: 'id',
      title: '#',
      width: 60,
      render: (r) => <Text size="sm" c="dimmed">{r.id}</Text>
    },
    {
      accessor: 'recommendation_type',
      title: 'النوع',
      render: (r) => <Badge variant="light" color="blue">{r.recommendation_type}</Badge>
    },
    {
      accessor: 'recommendation',
      title: 'التوصية',
      render: (r) => {
        try {
          const payload = JSON.parse(r.recommendation)
          return (
            <Stack gap={2}>
              <Text size="sm" fw={600}>{payload.action || 'إجراء'}</Text>
              <Text size="xs" c="dimmed">{r.reasoning}</Text>
            </Stack>
          )
        } catch(e) {
          return <Text size="sm">{r.recommendation}</Text>
        }
      }
    },
    {
      accessor: 'confidence_score',
      title: 'نسبة الثقة',
      width: 140,
      render: (r) => (
        <Stack gap={4}>
          <Progress 
            value={r.confidence_score} 
            color={r.confidence_score > 90 ? 'teal' : 'yellow'} 
            size="sm"
          />
          <Text size="xs" c="dimmed" ta="right">{r.confidence_score}%</Text>
        </Stack>
      )
    },
    {
      accessor: 'expected_impact',
      title: 'التأثير المتوقع',
      render: (r) => {
        try {
          const impact = JSON.parse(r.expected_impact)
          return <Text size="sm">{impact ? JSON.stringify(impact).replace(/[{}]/g, '') : '-'}</Text>
        } catch(e) {
          return <Text size="sm">-</Text>
        }
      }
    },
    {
      accessor: 'status',
      title: 'الحالة',
      render: (r) => (
        <Badge 
          color={
            r.status === 'PENDING' ? 'yellow' : 
            (r.status === 'APPLIED' || r.status === 'VERIFIED') ? 'teal' : 'red'
          }
        >
          {r.status}
        </Badge>
      )
    },
    {
      accessor: 'actions',
      title: 'الإجراءات',
      render: (r) => (
        r.status === 'PENDING' && (
          <Group gap="xs" wrap="nowrap">
            <Tooltip label="موافقة">
              <ActionIcon color="teal" variant="light" onClick={() => handleApprove(r.id)}>
                <IconCheck size={16} />
              </ActionIcon>
            </Tooltip>
            <Tooltip label="رفض">
              <ActionIcon color="red" variant="light" onClick={() => handleReject(r.id)}>
                <IconX size={16} />
              </ActionIcon>
            </Tooltip>
          </Group>
        )
      )
    }
  ]

  return (
    <main className="saas-page" dir="rtl">
      <Group justify="space-between" align="flex-start" mb="xl">
        <Group>
          <Box bg="blue.1" c="blue.7" p="md" style={{ borderRadius: '12px' }}>
            <IconBrain size={32} />
          </Box>
          <Stack gap={4}>
            <Title order={2} fw={800} c="dark.8">منسق الذكاء الاصطناعي للتسويق</Title>
            <Text c="dimmed">توصيات وموافقات الذكاء الاصطناعي للمؤسسات</Text>
          </Stack>
        </Group>
      </Group>

      <Group grow mb="xl" align="flex-start">
        <Card shadow="sm" radius="md" withBorder padding="lg">
          <Group justify="space-between">
            <Stack gap={4}>
              <Text size="xs" fw={700} c="dimmed" tt="uppercase">توصيات بانتظار الموافقة</Text>
              <Title order={2} c="dark.8">{stats.pending}</Title>
            </Stack>
            <Box bg="gray.1" c="gray.6" p="sm" style={{ borderRadius: '50%' }}>
              <IconClock size={28} />
            </Box>
          </Group>
        </Card>
        
        <Card shadow="sm" radius="md" withBorder padding="lg">
          <Group justify="space-between">
            <Stack gap={4}>
              <Text size="xs" fw={700} c="dimmed" tt="uppercase">التوصيات المطبقة</Text>
              <Title order={2} c="dark.8">{stats.applied}</Title>
            </Stack>
            <Box bg="teal.1" c="teal.6" p="sm" style={{ borderRadius: '50%' }}>
              <IconCheck size={28} />
            </Box>
          </Group>
        </Card>
      </Group>

      <Card shadow="sm" radius="md" withBorder>
        <Card.Section withBorder inheritPadding py="md">
          <Title order={5} fw={700} c="dark.8">سجل توصيات الذكاء الاصطناعي</Title>
        </Card.Section>
        <Card.Section>
          <DataTable
            minHeight={200}
            fetching={loading}
            columns={columns}
            records={data}
            noRecordsText="لا توجد بيانات متاحة"
            highlightOnHover
            verticalSpacing="md"
            horizontalSpacing="md"
          />
        </Card.Section>
      </Card>
    </main>
  )
}

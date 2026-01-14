<template>
    <div class="bot-statistics space-y-6">
        <h2 class="text-2xl font-semibold">Статистика</h2>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12">
            <p class="text-muted-foreground">Загрузка статистики...</p>
        </div>

        <!-- Statistics -->
        <div v-if="!loading && statistics" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-card rounded-lg border border-border p-6">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">Всего пользователей</h3>
                <p class="text-3xl font-semibold">{{ statistics.total_users || 0 }}</p>
            </div>

            <div class="bg-card rounded-lg border border-border p-6">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">Активных за 30 дней</h3>
                <p class="text-3xl font-semibold">{{ statistics.active_users_30d || 0 }}</p>
            </div>

            <div class="bg-card rounded-lg border border-border p-6">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">Всего заявок</h3>
                <p class="text-3xl font-semibold">{{ statistics.total_consultations || 0 }}</p>
            </div>

            <div class="bg-card rounded-lg border border-border p-6">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">Скачиваний материалов</h3>
                <p class="text-3xl font-semibold">{{ statistics.materials_downloads || 0 }}</p>
            </div>
        </div>

        <!-- Consultations by Status -->
        <div v-if="!loading && statistics" class="bg-card rounded-lg border border-border p-6">
            <h3 class="text-lg font-semibold mb-4">Заявки по статусам</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">Новые</p>
                    <p class="text-2xl font-semibold">{{ statistics.consultations_by_status?.new || 0 }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">В работе</p>
                    <p class="text-2xl font-semibold">{{ statistics.consultations_by_status?.in_progress || 0 }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Закрытые</p>
                    <p class="text-2xl font-semibold">{{ statistics.consultations_by_status?.closed || 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Popular Materials -->
        <div v-if="!loading && statistics && statistics.popular_materials?.length > 0" class="bg-card rounded-lg border border-border p-6">
            <h3 class="text-lg font-semibold mb-4">Популярные материалы</h3>
            <div class="space-y-2">
                <div
                    v-for="material in statistics.popular_materials"
                    :key="material.id"
                    class="flex items-center justify-between p-3 bg-muted/30 rounded-lg"
                >
                    <p class="font-medium">{{ material.title }}</p>
                    <p class="text-sm text-muted-foreground">{{ material.download_count || 0 }} скачиваний</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet } from '../../utils/api'

export default {
    name: 'BotStatistics',
    props: {
        botId: {
            type: [String, Number],
            required: true,
        },
    },
    setup(props) {
        const loading = ref(false)
        const statistics = ref(null)

        const fetchStatistics = async () => {
            loading.value = true
            try {
                const response = await apiGet(`/bot-management/${props.botId}/statistics`)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки статистики')
                }

                const data = await response.json()
                if (data.success && data.data) {
                    statistics.value = data.data
                }
            } catch (err) {
                console.error('Error fetching statistics:', err)
            } finally {
                loading.value = false
            }
        }

        onMounted(() => {
            fetchStatistics()
        })

        return {
            loading,
            statistics,
        }
    },
}
</script>


